<?php

declare(strict_types=1);

namespace App\Domain\Booking\Services;

use App\Domain\Audit\Services\AuditService;
use App\Domain\Availability\Enums\ScheduleBlockKind;
use App\Domain\Availability\Services\AvailabilityService;
use App\Domain\Booking\DTOs\CreateProposalData;
use App\Domain\Booking\Enums\BookingStatus;
use App\Domain\Booking\Enums\ProposalStatus;
use App\Domain\Messaging\Services\OutboxService;
use App\Models\Booking;
use App\Models\GuestAccessToken;
use App\Models\Proposal;
use App\Models\ProposalItem;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ProposalService
{
    public function __construct(
        protected AvailabilityService $availabilityService,
        protected BookingLifecycleService $lifecycleService,
        protected AuditService $auditService,
        protected OutboxService $outboxService,
    ) {}

    /**
     * Create a new draft proposal for a booking.
     */
    public function createDraft(Booking $booking, CreateProposalData $data, ?User $staff = null): Proposal
    {
        if (! in_array($booking->status, [BookingStatus::UnderReview, BookingStatus::ProposalSent, BookingStatus::Confirmed], true)) {
            throw new InvalidArgumentException("Cannot create a proposal for booking {$booking->reference} with status {$booking->status->value}.");
        }

        return DB::transaction(function () use ($booking, $data, $staff) {
            $nextVersion = ((int) $booking->proposals()->max('version')) + 1;
            $reference = Proposal::generateReference($booking, $nextVersion);

            /** @var Proposal $proposal */
            $proposal = Proposal::create([
                'booking_id' => $booking->id,
                'version' => $nextVersion,
                'reference' => $reference,
                'status' => ProposalStatus::Draft,
                'vehicle_id' => $data->vehicleId,
                'service_type' => $data->serviceType,
                'schedule_block_id' => null,
                'starts_at' => $data->startsAt->setTimezone('Asia/Colombo'),
                'ends_at' => $data->endsAt->setTimezone('Asia/Colombo'),
                'currency' => $data->currency,
                'total_amount_minor_units' => $data->totalAmountMinorUnits,
                'approximate_lkr_amount_minor_units' => $data->approximateLkrAmountMinorUnits,
                'deposit_required' => $data->depositRequired,
                'deposit_amount_minor_units' => $data->depositAmountMinorUnits,
                'payment_choice' => $data->paymentChoice,
                'selected_payment_choice' => null,
                'expires_at' => $data->expiresAt->setTimezone('Asia/Colombo'),
                'staff_notes' => $data->staffNotes,
                'internal_notes' => $data->internalNotes,
                'sent_by_user_id' => $staff?->id,
                'snapshot_data' => $data->snapshotData,
            ]);

            foreach ($data->items as $index => $itemData) {
                ProposalItem::create([
                    'proposal_id' => $proposal->id,
                    'component_type' => $itemData->componentType,
                    'description' => $itemData->description,
                    'quantity' => $itemData->quantity,
                    'unit' => $itemData->unit,
                    'unit_price_minor_units' => $itemData->unitPrice->amountMinorUnits,
                    'total_amount_minor_units' => $itemData->totalAmount->amountMinorUnits,
                    'is_deposit' => $itemData->isDeposit,
                    'is_tax' => $itemData->isTax,
                    'is_discount' => $itemData->isDiscount,
                    'sort_order' => $index + 1,
                ]);
            }

            $this->auditService->record(
                action: 'proposal.draft_created',
                actor: $staff,
                summary: "Created draft proposal {$proposal->reference} for booking {$booking->reference}",
                metadata: [
                    'booking_id' => $booking->id,
                    'proposal_id' => $proposal->id,
                    'reference' => $proposal->reference,
                    'version' => $proposal->version,
                    'total_amount_minor_units' => $proposal->total_amount_minor_units,
                ],
                auditable: $proposal,
                actorType: $staff ? 'staff' : 'system'
            );

            return $proposal;
        });
    }

    /**
     * Send a proposal to the guest: acquires schedule hold block, updates statuses, dispatches outbox event.
     */
    public function sendProposal(Proposal $proposal, ?User $staff = null): Proposal
    {
        if ($proposal->status !== ProposalStatus::Draft) {
            throw new InvalidArgumentException("Cannot send proposal {$proposal->reference} with status {$proposal->status->value}. Only draft proposals can be sent.");
        }

        return DB::transaction(function () use ($proposal, $staff) {
            $proposal->refresh();
            /** @var Booking $booking */
            $booking = Booking::where('id', $proposal->booking_id)->lockForUpdate()->firstOrFail();

            if (! in_array($booking->status, [BookingStatus::UnderReview, BookingStatus::ProposalSent, BookingStatus::Confirmed], true)) {
                throw new InvalidArgumentException("Cannot send proposal for booking in status {$booking->status->value}.");
            }

            /** @var Vehicle $vehicle */
            $vehicle = Vehicle::where('id', $proposal->vehicle_id)->lockForUpdate()->firstOrFail();

            // Self-conflict guard: if this booking already has an active confirmed schedule block on this vehicle,
            // ignore that block so the proposal hold can overlap safely during revision!
            $ignoreBlockId = null;
            if ($booking->status === BookingStatus::Confirmed && $booking->schedule_block_id) {
                $ignoreBlockId = $booking->schedule_block_id;
            }

            $holdBlock = $this->availabilityService->createProposalHold(
                vehicle: $vehicle,
                start: $proposal->starts_at,
                end: $proposal->ends_at,
                service: $proposal->service_type,
                expiresAt: $proposal->expires_at,
                ignoreBlockId: $ignoreBlockId,
                actor: $staff
            );

            $now = Carbon::now('Asia/Colombo');
            $proposal->update([
                'status' => ProposalStatus::Sent,
                'schedule_block_id' => $holdBlock->id,
                'sent_at' => $now,
                'sent_by_user_id' => $staff ? $staff->id : $proposal->sent_by_user_id,
            ]);

            $this->lifecycleService->markProposalSent($booking, $proposal, $staff);

            // Ensure active guest access token exists
            /** @var GuestAccessToken|null $existingToken */
            $existingToken = $booking->guestAccessTokens()
                ->whereNull('revoked_at')
                ->where('expires_at', '>', $now)
                ->latest()
                ->first();

            $plainToken = null;
            if (! $existingToken) {
                $plainToken = GuestAccessToken::generateToken();
                GuestAccessToken::create([
                    'booking_id' => $booking->id,
                    'token_hash' => GuestAccessToken::hashToken($plainToken),
                    'purpose' => 'guest_access',
                    'expires_at' => Carbon::now('Asia/Colombo')->addDays(30),
                ]);
            }

            $statusUrl = $plainToken
                ? route('booking.status', ['reference' => $booking->reference, 'token' => $plainToken])
                : url("/booking/{$booking->reference}/manage");

            $this->auditService->record(
                action: 'proposal.sent',
                actor: $staff,
                summary: "Sent proposal {$proposal->reference} (v{$proposal->version}) for booking {$booking->reference}",
                metadata: [
                    'booking_id' => $booking->id,
                    'proposal_id' => $proposal->id,
                    'reference' => $proposal->reference,
                    'version' => $proposal->version,
                    'hold_block_id' => $holdBlock->id,
                    'expires_at' => $proposal->expires_at->toIso8601String(),
                ],
                auditable: $proposal,
                actorType: $staff ? 'staff' : 'system'
            );

            $this->outboxService->record(
                eventType: 'booking.proposal_sent',
                aggregate: $booking,
                payload: [
                    'booking_id' => $booking->id,
                    'proposal_id' => $proposal->id,
                    'reference' => $proposal->reference,
                    'version' => $proposal->version,
                    'expires_at' => $proposal->expires_at->toIso8601String(),
                    'total_amount_minor_units' => $proposal->total_amount_minor_units,
                    'currency' => $proposal->currency,
                    'status_url' => $statusUrl,
                ],
                idempotencyKey: "evt_booking_{$booking->id}_prop_v{$proposal->version}_sent"
            );

            return $proposal;
        });
    }

    /**
     * Revise an existing proposal: creates a new incremented version and marks previous proposals superseded.
     */
    public function reviseProposal(Booking $booking, CreateProposalData $data, ?User $staff = null): Proposal
    {
        return DB::transaction(function () use ($booking, $data, $staff) {
            $lockedBooking = Booking::where('id', $booking->id)->lockForUpdate()->firstOrFail();

            // Supersede any active sent proposal and release its temporary hold block
            $activeProposals = $lockedBooking->proposals()
                ->where('status', ProposalStatus::Sent->value)
                ->get();

            foreach ($activeProposals as $activeProposal) {
                if ($activeProposal->schedule_block_id && $activeProposal->scheduleBlock?->kind === ScheduleBlockKind::ProposalHold) {
                    $this->availabilityService->releaseBlock(
                        block: $activeProposal->scheduleBlock,
                        reason: 'superseded_by_revision_proposal',
                        actor: $staff
                    );
                }

                $activeProposal->update([
                    'status' => ProposalStatus::Superseded,
                ]);
            }

            $draft = $this->createDraft($lockedBooking, $data, $staff);

            return $this->sendProposal($draft, $staff);
        });
    }

    /**
     * Expire an active proposal after response deadline passes.
     */
    public function expireProposal(Proposal $proposal): void
    {
        DB::transaction(function () use ($proposal) {
            $lockedProposal = Proposal::where('id', $proposal->id)->lockForUpdate()->firstOrFail();

            if ($lockedProposal->status !== ProposalStatus::Sent) {
                return;
            }

            /** @var Booking $booking */
            $booking = Booking::where('id', $lockedProposal->booking_id)->lockForUpdate()->firstOrFail();

            // Release ProposalHold block
            if ($lockedProposal->schedule_block_id && $lockedProposal->scheduleBlock) {
                if ($lockedProposal->scheduleBlock->kind === ScheduleBlockKind::ProposalHold) {
                    $this->availabilityService->releaseBlock(
                        block: $lockedProposal->scheduleBlock,
                        reason: 'proposal_response_deadline_elapsed',
                        actor: null
                    );
                }
            }

            $lockedProposal->update([
                'status' => ProposalStatus::Expired,
            ]);

            // If initial proposal on submitted/review booking: transition booking to Expired
            if ($booking->status === BookingStatus::ProposalSent) {
                $booking->update([
                    'status' => BookingStatus::Expired,
                ]);

                $this->auditService->record(
                    action: 'booking.expired',
                    actor: null,
                    summary: "Booking {$booking->reference} expired following proposal {$lockedProposal->reference} expiration",
                    metadata: [
                        'booking_id' => $booking->id,
                        'proposal_id' => $lockedProposal->id,
                    ],
                    auditable: $booking,
                    actorType: 'system'
                );
            } elseif ($booking->status === BookingStatus::Confirmed) {
                // Confirmed booking remains Confirmed; simply clear pending_revision flag!
                $booking->update([
                    'pending_revision' => false,
                ]);

                $this->auditService->record(
                    action: 'booking.revision_expired',
                    actor: null,
                    summary: "Revision proposal {$lockedProposal->reference} expired. Confirmed booking {$booking->reference} remains active",
                    metadata: [
                        'booking_id' => $booking->id,
                        'proposal_id' => $lockedProposal->id,
                    ],
                    auditable: $booking,
                    actorType: 'system'
                );
            }

            $this->auditService->record(
                action: 'proposal.expired',
                actor: null,
                summary: "Proposal {$lockedProposal->reference} expired automatically",
                metadata: [
                    'proposal_id' => $lockedProposal->id,
                    'booking_id' => $booking->id,
                ],
                auditable: $lockedProposal,
                actorType: 'system'
            );

            $this->outboxService->record(
                eventType: 'booking.proposal_expired',
                aggregate: $booking,
                payload: [
                    'booking_id' => $booking->id,
                    'proposal_id' => $lockedProposal->id,
                    'reference' => $lockedProposal->reference,
                ],
                idempotencyKey: "evt_booking_{$booking->id}_prop_v{$lockedProposal->version}_expired"
            );
        });
    }

    /**
     * Compute side-by-side comparison between initial booking request and a proposal.
     *
     * @return array<string, array{label: string, original: string, proposed: string, changed: bool}>
     */
    public function computeComparison(Booking $booking, ?Proposal $proposal = null): array
    {
        $proposal = $proposal ?? $booking->currentProposal;
        if (! $proposal) {
            return [];
        }

        $proposal->loadMissing('vehicle');
        $booking->loadMissing('vehicle');

        $comparison = [];

        // Vehicle comparison
        $originalVehicle = $booking->vehicle ? ($booking->vehicle->public_name ?: "{$booking->vehicle->make} {$booking->vehicle->model}") : 'Vehicle from request';
        $proposedVehicle = $proposal->vehicle ? ($proposal->vehicle->public_name ?: "{$proposal->vehicle->make} {$proposal->vehicle->model}") : 'Proposed vehicle';
        $comparison['vehicle'] = [
            'label' => 'Allocated Vehicle',
            'original' => $originalVehicle,
            'proposed' => $proposedVehicle,
            'changed' => $originalVehicle !== $proposedVehicle,
        ];

        // Total Amount comparison
        $originalAmount = $booking->total_amount_minor_units !== null
            ? sprintf('%s %.2f', $booking->currency ?: 'USD', $booking->total_amount_minor_units / 100)
            : 'Pending Calculation';
        $proposedAmount = sprintf('%s %.2f', $proposal->currency, $proposal->total_amount_minor_units / 100);
        $comparison['total_amount'] = [
            'label' => 'Total Price',
            'original' => $originalAmount,
            'proposed' => $proposedAmount,
            'changed' => $booking->total_amount_minor_units !== $proposal->total_amount_minor_units,
        ];

        // Deposit comparison
        $proposedDeposit = $proposal->deposit_required
            ? sprintf('%s %.2f', $proposal->currency, $proposal->deposit_amount_minor_units / 100)
            : 'None required';
        $comparison['deposit'] = [
            'label' => 'Security Deposit',
            'original' => 'Subject to terms',
            'proposed' => $proposedDeposit,
            'changed' => $proposal->deposit_required,
        ];

        return $comparison;
    }
}
