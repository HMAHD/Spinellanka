<?php

declare(strict_types=1);

namespace App\Domain\Booking\Services;

use App\Domain\Audit\Services\AuditService;
use App\Domain\Availability\Enums\ScheduleBlockKind;
use App\Domain\Availability\Services\AvailabilityService;
use App\Domain\Booking\DTOs\BookingTransitionResult;
use App\Domain\Booking\Enums\BookingStatus;
use App\Domain\Booking\Enums\ConfirmationChannel;
use App\Domain\Booking\Enums\PaymentOption;
use App\Domain\Booking\Enums\ProposalStatus;
use App\Domain\Booking\Exceptions\InvalidPaymentChoiceException;
use App\Domain\Booking\Exceptions\ProposalNotActionableException;
use App\Domain\Booking\ServiceType;
use App\Domain\Messaging\Services\OutboxService;
use App\Models\Booking;
use App\Models\Proposal;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class GuestControlService
{
    public function __construct(
        protected AvailabilityService $availabilityService,
        protected BookingLifecycleService $lifecycleService,
        protected AuditService $auditService,
        protected OutboxService $outboxService,
    ) {}

    /**
     * Traveler accepts a proposal using their selected payment choice.
     */
    public function acceptProposal(
        Booking $booking,
        Proposal $proposal,
        PaymentOption $choice,
        ?string $notes = null
    ): BookingTransitionResult {
        return DB::transaction(function () use ($booking, $proposal, $choice, $notes) {
            // Deterministic row locking
            Vehicle::where('id', $proposal->vehicle_id)->lockForUpdate()->firstOrFail();
            /** @var Booking $lockedBooking */
            $lockedBooking = Booking::where('id', $booking->id)->lockForUpdate()->firstOrFail();
            /** @var Proposal $lockedProposal */
            $lockedProposal = Proposal::where('id', $proposal->id)->lockForUpdate()->firstOrFail();

            // Idempotency: if already accepted with this choice, return current result safely
            if ($lockedProposal->status === ProposalStatus::Accepted) {
                $requiresPayment = $lockedProposal->selected_payment_choice?->requiresOnlinePayment() ?? false;
                $amountDue = $requiresPayment
                    ? ($lockedProposal->deposit_required && $lockedProposal->selected_payment_choice === PaymentOption::Deposit
                        ? $lockedProposal->deposit_amount_minor_units
                        : $lockedProposal->total_amount_minor_units)
                    : 0;

                return new BookingTransitionResult(
                    booking: $lockedBooking,
                    proposal: $lockedProposal,
                    requiresPayment: $requiresPayment,
                    paymentOption: $lockedProposal->selected_payment_choice ?? $choice,
                    amountDueMinorUnits: $amountDue,
                    currency: $lockedProposal->currency,
                    targetStatus: $lockedBooking->status,
                    message: 'Proposal was already accepted.'
                );
            }

            // Invariant guards
            if ($lockedBooking->status->isTerminal()) {
                throw new ProposalNotActionableException("Booking {$lockedBooking->reference} is in a terminal state ({$lockedBooking->status->value}) and cannot accept proposals.");
            }

            if ($lockedProposal->id !== $lockedBooking->current_proposal_id || $lockedProposal->status !== ProposalStatus::Sent || $lockedProposal->isExpired()) {
                throw new ProposalNotActionableException('This proposal is no longer active or actionable.');
            }

            // Strict payment choice validation against proposal configuration
            if (! $lockedProposal->payment_choice->allows($choice)) {
                throw new InvalidPaymentChoiceException("Selected payment option '{$choice->value}' is not permitted for this proposal.");
            }

            $now = Carbon::now('Asia/Colombo');

            $lockedProposal->update([
                'status' => ProposalStatus::Accepted,
                'selected_payment_choice' => $choice,
                'responded_at' => $now,
                'response_action' => 'accepted',
                'response_notes' => $notes,
            ]);

            // Hydrate booking relational models and totals from proposal snapshot
            $this->lifecycleService->hydrateFromProposalSnapshot($lockedBooking, $lockedProposal);

            $this->auditService->record(
                action: 'proposal.accepted',
                actor: null,
                summary: "Guest accepted proposal {$lockedProposal->reference} via {$choice->label()}",
                metadata: [
                    'booking_id' => $lockedBooking->id,
                    'proposal_id' => $lockedProposal->id,
                    'payment_option' => $choice->value,
                    'notes' => $notes,
                ],
                auditable: $lockedProposal,
                actorType: 'guest'
            );

            $this->outboxService->record(
                eventType: 'booking.proposal_accepted',
                aggregate: $lockedBooking,
                payload: [
                    'booking_id' => $lockedBooking->id,
                    'proposal_id' => $lockedProposal->id,
                    'reference' => $lockedProposal->reference,
                    'payment_option' => $choice->value,
                ],
                idempotencyKey: "evt_booking_{$lockedBooking->id}_prop_v{$lockedProposal->version}_accepted"
            );

            if ($choice === PaymentOption::PayLater) {
                // Pay Later directly confirms the booking
                $confirmationNotes = 'Accepted proposal by guest via private link: '.($notes ?: 'Pay on arrival / cash to driver');
                $confirmedBooking = $this->lifecycleService->confirm(
                    booking: $lockedBooking,
                    staff: null,
                    channel: ConfirmationChannel::GuestPrivateLink,
                    notes: $confirmationNotes,
                    proposal: $lockedProposal
                );

                return new BookingTransitionResult(
                    booking: $confirmedBooking,
                    proposal: $lockedProposal,
                    requiresPayment: false,
                    paymentOption: $choice,
                    amountDueMinorUnits: 0,
                    currency: $lockedProposal->currency,
                    targetStatus: BookingStatus::Confirmed,
                    message: 'Your booking has been confirmed! Our dispatch desk will meet you at the scheduled location.'
                );
            }

            // Online payment required: transition to AwaitingPayment (Phase 13 boundary)
            $awaitingBooking = $this->lifecycleService->markAwaitingPayment(
                booking: $lockedBooking,
                proposal: $lockedProposal,
                paymentOption: $choice
            );

            $amountDue = $lockedProposal->deposit_required && $choice === PaymentOption::Deposit
                ? $lockedProposal->deposit_amount_minor_units
                : $lockedProposal->total_amount_minor_units;

            $targetStatus = $lockedBooking->status === BookingStatus::Confirmed
                ? BookingStatus::Confirmed
                : BookingStatus::AwaitingPayment;

            return new BookingTransitionResult(
                booking: $awaitingBooking,
                proposal: $lockedProposal,
                requiresPayment: true,
                paymentOption: $choice,
                amountDueMinorUnits: $amountDue,
                currency: $lockedProposal->currency,
                targetStatus: $targetStatus,
                message: 'Proposal accepted! Please proceed with payment settlement.'
            );
        });
    }

    /**
     * Traveler declines a proposal.
     */
    public function declineProposal(Booking $booking, Proposal $proposal, ?string $reason = null): void
    {
        DB::transaction(function () use ($booking, $proposal, $reason) {
            Vehicle::where('id', $proposal->vehicle_id)->lockForUpdate()->firstOrFail();
            /** @var Booking $lockedBooking */
            $lockedBooking = Booking::where('id', $booking->id)->lockForUpdate()->firstOrFail();
            /** @var Proposal $lockedProposal */
            $lockedProposal = Proposal::where('id', $proposal->id)->lockForUpdate()->firstOrFail();

            if ($lockedProposal->id !== $lockedBooking->current_proposal_id || $lockedProposal->status !== ProposalStatus::Sent) {
                throw new ProposalNotActionableException('This proposal is not active or actionable.');
            }

            // Release temporary proposal hold
            if ($lockedProposal->schedule_block_id && $lockedProposal->scheduleBlock) {
                if ($lockedProposal->scheduleBlock->kind === ScheduleBlockKind::ProposalHold) {
                    $this->availabilityService->releaseBlock(
                        block: $lockedProposal->scheduleBlock,
                        reason: 'proposal_declined_by_guest',
                        actor: null
                    );
                }
            }

            $now = Carbon::now('Asia/Colombo');
            $lockedProposal->update([
                'status' => ProposalStatus::Declined,
                'responded_at' => $now,
                'response_action' => 'declined',
                'response_notes' => $reason,
            ]);

            $this->auditService->record(
                action: 'proposal.declined',
                actor: null,
                summary: "Guest declined proposal {$lockedProposal->reference}",
                metadata: [
                    'booking_id' => $lockedBooking->id,
                    'proposal_id' => $lockedProposal->id,
                    'reason' => $reason,
                ],
                auditable: $lockedProposal,
                actorType: 'guest'
            );

            $this->outboxService->record(
                eventType: 'booking.proposal_declined',
                aggregate: $lockedBooking,
                payload: [
                    'booking_id' => $lockedBooking->id,
                    'proposal_id' => $lockedProposal->id,
                    'reference' => $lockedProposal->reference,
                    'decline_reason' => $reason,
                ],
                idempotencyKey: "evt_booking_{$lockedBooking->id}_prop_v{$lockedProposal->version}_declined"
            );

            if ($lockedBooking->status === BookingStatus::Confirmed) {
                // Confirmed booking remains Confirmed; simply clear pending_revision flag!
                $lockedBooking->update([
                    'pending_revision' => false,
                ]);

                $this->auditService->record(
                    action: 'booking.revision_declined',
                    actor: null,
                    summary: "Guest declined revision proposal {$lockedProposal->reference}. Confirmed booking {$lockedBooking->reference} remains active",
                    metadata: [
                        'booking_id' => $lockedBooking->id,
                        'proposal_id' => $lockedProposal->id,
                    ],
                    auditable: $lockedBooking,
                    actorType: 'guest'
                );

                $this->outboxService->record(
                    eventType: 'booking.revision_declined',
                    aggregate: $lockedBooking,
                    payload: [
                        'booking_id' => $lockedBooking->id,
                        'proposal_id' => $lockedProposal->id,
                    ],
                    idempotencyKey: "evt_booking_{$lockedBooking->id}_rev_declined_prop_v{$lockedProposal->version}"
                );
            } elseif ($lockedBooking->status === BookingStatus::ProposalSent) {
                $lockedBooking->update([
                    'status' => BookingStatus::Declined,
                    'declined_at' => $now,
                    'decline_reason' => $reason ?: 'Declined by guest via private link',
                ]);

                $this->auditService->record(
                    action: 'booking.declined',
                    actor: null,
                    summary: "Booking {$lockedBooking->reference} marked declined after proposal rejected by guest",
                    metadata: [
                        'booking_id' => $lockedBooking->id,
                        'proposal_id' => $lockedProposal->id,
                    ],
                    auditable: $lockedBooking,
                    actorType: 'guest'
                );

                $this->outboxService->record(
                    eventType: 'booking.declined',
                    aggregate: $lockedBooking,
                    payload: [
                        'booking_id' => $lockedBooking->id,
                        'reference' => $lockedBooking->reference,
                        'decline_reason' => $reason ?: 'Proposal declined by guest',
                    ],
                    idempotencyKey: "evt_booking_{$lockedBooking->id}_declined_v1"
                );
            }
        });
    }

    /**
     * Traveler requests adjustments or changes to the current proposal or booking.
     */
    public function requestChange(Booking $booking, string $changeNotes): void
    {
        if (trim($changeNotes) === '') {
            throw new InvalidArgumentException('Change request notes cannot be empty.');
        }

        DB::transaction(function () use ($booking, $changeNotes) {
            /** @var Booking $lockedBooking */
            $lockedBooking = Booking::where('id', $booking->id)->lockForUpdate()->firstOrFail();

            if ($lockedBooking->status->isTerminal()) {
                throw new InvalidArgumentException("Cannot request changes for booking {$lockedBooking->reference} in terminal state ({$lockedBooking->status->value}).");
            }

            $lockedBooking->update([
                'pending_revision' => true,
            ]);

            $this->auditService->record(
                action: 'booking.change_requested',
                actor: null,
                summary: "Guest requested changes for booking {$lockedBooking->reference}",
                metadata: [
                    'booking_id' => $lockedBooking->id,
                    'notes' => trim($changeNotes),
                ],
                auditable: $lockedBooking,
                actorType: 'guest'
            );

            $this->outboxService->record(
                eventType: 'booking.change_requested',
                aggregate: $lockedBooking,
                payload: [
                    'booking_id' => $lockedBooking->id,
                    'reference' => $lockedBooking->reference,
                    'change_notes' => trim($changeNotes),
                ],
                idempotencyKey: "evt_booking_{$lockedBooking->id}_change_req_".hash('sha256', trim($changeNotes).(string) $lockedBooking->updated_at->timestamp)
            );
        });
    }

    /**
     * Traveler requests a rental extension.
     */
    public function requestRentExtension(Booking $booking, int $extraDays, ?string $notes = null): void
    {
        if ($booking->service_type !== ServiceType::Rent) {
            throw new InvalidArgumentException('Extensions are only supported for self-drive Rent bookings.');
        }

        if ($extraDays < 1 || $extraDays > 30) {
            throw new InvalidArgumentException('Extension duration must be between 1 and 30 days.');
        }

        DB::transaction(function () use ($booking, $extraDays, $notes) {
            /** @var Booking $lockedBooking */
            $lockedBooking = Booking::where('id', $booking->id)->lockForUpdate()->firstOrFail();

            if ($lockedBooking->status->isTerminal()) {
                throw new InvalidArgumentException("Cannot request extension for booking {$lockedBooking->reference} in terminal state ({$lockedBooking->status->value}).");
            }

            if ($lockedBooking->rentDetail) {
                $currentState = $lockedBooking->rentDetail->extension_state ?? [];
                $currentState[] = [
                    'status' => 'requested',
                    'extra_days' => $extraDays,
                    'notes' => $notes,
                    'requested_at' => Carbon::now('Asia/Colombo')->toIso8601String(),
                ];

                $lockedBooking->rentDetail->update([
                    'extension_state' => $currentState,
                ]);
            }

            $lockedBooking->update([
                'pending_revision' => true,
            ]);

            $this->auditService->record(
                action: 'booking.rent_extension_requested',
                actor: null,
                summary: "Guest requested Rent extension of {$extraDays} day(s) for booking {$lockedBooking->reference}",
                metadata: [
                    'booking_id' => $lockedBooking->id,
                    'extra_days' => $extraDays,
                    'notes' => $notes,
                ],
                auditable: $lockedBooking,
                actorType: 'guest'
            );

            $this->outboxService->record(
                eventType: 'booking.rent_extension_requested',
                aggregate: $lockedBooking,
                payload: [
                    'booking_id' => $lockedBooking->id,
                    'reference' => $lockedBooking->reference,
                    'extra_days' => $extraDays,
                    'notes' => $notes,
                ],
                idempotencyKey: "evt_booking_{$lockedBooking->id}_rent_ext_{$extraDays}_".hash('sha256', ($notes ?? '').(string) $lockedBooking->updated_at->timestamp)
            );
        });
    }

    /**
     * Traveler cancels their booking.
     */
    public function cancelBooking(Booking $booking, string $reason): Booking
    {
        return $this->lifecycleService->cancel(
            booking: $booking,
            reason: $reason,
            actor: null
        );
    }
}
