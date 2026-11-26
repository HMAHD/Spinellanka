<?php

declare(strict_types=1);

namespace App\Domain\Booking\Services;

use App\Domain\Audit\Services\AuditService;
use App\Domain\Availability\Enums\ScheduleBlockKind;
use App\Domain\Availability\Enums\ScheduleBlockStatus;
use App\Domain\Availability\Services\AvailabilityService;
use App\Domain\Booking\Enums\BookingStatus;
use App\Domain\Booking\Enums\ConfirmationChannel;
use App\Domain\Booking\Enums\PaymentOption;
use App\Domain\Booking\Enums\ProposalStatus;
use App\Domain\Booking\ServiceType;
use App\Domain\Identity\Permission;
use App\Domain\Messaging\Services\OutboxService;
use App\Domain\Operations\Enums\DriverTripStatus;
use App\Models\Booking;
use App\Models\DriverAccessToken;
use App\Models\GuestAccessToken;
use App\Models\Proposal;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleScheduleBlock;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class BookingLifecycleService
{
    public function __construct(
        protected AvailabilityService $availabilityService,
        protected AuditService $auditService,
        protected OutboxService $outboxService,
    ) {}

    public function markUnderReview(Booking $booking, User $staff): Booking
    {
        if ($booking->status !== BookingStatus::Submitted) {
            throw new InvalidArgumentException("Cannot mark booking under review when status is {$booking->status->value}.");
        }

        $booking->update([
            'status' => BookingStatus::UnderReview,
            'assigned_staff_id' => $staff->id,
        ]);

        $this->auditService->record(
            action: 'booking.marked_under_review',
            actor: $staff,
            summary: "Booking {$booking->reference} marked under review by {$staff->name}",
            metadata: [
                'booking_id' => $booking->id,
                'reference' => $booking->reference,
                'staff_id' => $staff->id,
            ],
            auditable: $booking,
            actorType: 'staff'
        );

        return $booking;
    }

    public function markProposalSent(Booking $booking, Proposal $proposal, ?User $staff = null): Booking
    {
        return DB::transaction(function () use ($booking, $proposal, $staff) {
            $lockedBooking = Booking::where('id', $booking->id)->lockForUpdate()->firstOrFail();

            if ($lockedBooking->status === BookingStatus::Confirmed) {
                // Confirmed booking remains Confirmed; mark pending_revision = true
                $lockedBooking->update([
                    'pending_revision' => true,
                    'current_proposal_id' => $proposal->id,
                    'assigned_staff_id' => $staff ? $staff->id : $lockedBooking->assigned_staff_id,
                ]);
            } else {
                $lockedBooking->update([
                    'status' => BookingStatus::ProposalSent,
                    'current_proposal_id' => $proposal->id,
                    'assigned_staff_id' => $staff ? $staff->id : $lockedBooking->assigned_staff_id,
                ]);
            }

            $this->auditService->record(
                action: 'booking.proposal_sent',
                actor: $staff,
                summary: "Proposal {$proposal->reference} sent for booking {$lockedBooking->reference}",
                metadata: [
                    'booking_id' => $lockedBooking->id,
                    'proposal_id' => $proposal->id,
                    'version' => $proposal->version,
                    'reference' => $proposal->reference,
                ],
                auditable: $lockedBooking,
                actorType: $staff ? 'staff' : 'system'
            );

            return $lockedBooking;
        });
    }

    public function markAwaitingPayment(Booking $booking, Proposal $proposal, PaymentOption $paymentOption): Booking
    {
        return DB::transaction(function () use ($booking, $proposal, $paymentOption) {
            $lockedBooking = Booking::where('id', $booking->id)->lockForUpdate()->firstOrFail();

            // Pillar B preservation: if booking is already Confirmed, keep status as Confirmed while marking pending_revision = true
            if ($lockedBooking->status === BookingStatus::Confirmed) {
                $lockedBooking->update([
                    'pending_revision' => true,
                    'current_proposal_id' => $proposal->id,
                ]);
            } else {
                $lockedBooking->update([
                    'status' => BookingStatus::AwaitingPayment,
                    'pending_revision' => false,
                    'current_proposal_id' => $proposal->id,
                ]);
            }

            $this->auditService->record(
                action: 'booking.awaiting_payment',
                actor: null,
                summary: "Booking {$lockedBooking->reference} is awaiting payment ({$paymentOption->label()})",
                metadata: [
                    'booking_id' => $lockedBooking->id,
                    'proposal_id' => $proposal->id,
                    'payment_option' => $paymentOption->value,
                ],
                auditable: $lockedBooking,
                actorType: 'guest'
            );

            $this->outboxService->record(
                eventType: 'booking.awaiting_payment',
                aggregate: $lockedBooking,
                payload: [
                    'booking_id' => $lockedBooking->id,
                    'proposal_id' => $proposal->id,
                    'reference' => $lockedBooking->reference,
                    'payment_option' => $paymentOption->value,
                    'amount_due_minor_units' => $proposal->deposit_required && $paymentOption === PaymentOption::Deposit
                        ? $proposal->deposit_amount_minor_units
                        : $proposal->total_amount_minor_units,
                    'currency' => $proposal->currency,
                ],
                idempotencyKey: "evt_booking_{$lockedBooking->id}_awaiting_payment_v{$proposal->version}"
            );

            return $lockedBooking;
        });
    }

    public function hydrateFromProposalSnapshot(Booking $booking, Proposal $proposal): void
    {
        $booking->vehicle_id = $proposal->vehicle_id;
        $booking->total_amount_minor_units = $proposal->total_amount_minor_units;
        $booking->approximate_lkr_amount_minor_units = $proposal->approximate_lkr_amount_minor_units;
        $booking->currency = $proposal->currency;

        $snapshot = $proposal->snapshot_data;
        if (isset($snapshot['service_details']) && is_array($snapshot['service_details'])) {
            $details = $snapshot['service_details'];

            if ($booking->airportPickupDetail) {
                $booking->airportPickupDetail->update(array_intersect_key($details, array_flip([
                    'flight_number', 'arrival_time', 'destination_text', 'passenger_count', 'luggage_count',
                ])));
            } elseif ($booking->airportTransferDetail) {
                $booking->airportTransferDetail->update(array_intersect_key($details, array_flip([
                    'flight_number', 'departure_time', 'origin_text', 'chosen_pickup_time', 'passenger_count', 'luggage_count',
                ])));
            } elseif ($booking->rideDetail) {
                $booking->rideDetail->update(array_intersect_key($details, array_flip([
                    'pickup_time', 'origin_text', 'destination_text', 'has_return', 'return_pickup_time', 'passenger_count', 'luggage_count',
                ])));
            } elseif ($booking->rentDetail) {
                $booking->rentDetail->update(array_intersect_key($details, array_flip([
                    'pickup_datetime', 'return_datetime', 'duration_days', 'extra_hours', 'pickup_location_text', 'return_location_text',
                    'pickup_location_type', 'return_location_type', 'included_distance_km', 'security_deposit_minor_units', 'insurance_excess_minor_units',
                ])));
            }
        }

        $booking->request_hash = $booking->computeRequestHash();
        $booking->save();
    }

    public function confirm(
        Booking $booking,
        ?User $staff,
        ConfirmationChannel $channel,
        string $notes,
        ?string $overrideReason = null,
        ?Proposal $proposal = null,
    ): Booking {
        if (! $booking->canBeConfirmed()) {
            throw new InvalidArgumentException("Booking {$booking->reference} cannot be confirmed from status {$booking->status->value}.");
        }

        if ($proposal !== null) {
            if ($proposal->isExpired()) {
                throw new InvalidArgumentException("Cannot confirm booking from expired proposal {$proposal->reference}.");
            }
            if (! in_array($proposal->status, [ProposalStatus::Sent, ProposalStatus::Accepted], true)) {
                throw new InvalidArgumentException("Cannot confirm booking from proposal with status {$proposal->status->value}.");
            }
        }

        if ($booking->expires_at && $booking->expires_at->isPast() && $booking->status !== BookingStatus::UnderReview) {
            throw new InvalidArgumentException("Booking {$booking->reference} has expired and was not placed under review. Re-estimate required.");
        }

        if (! in_array($channel, [ConfirmationChannel::GuestPrivateLink, ConfirmationChannel::OnlinePayment], true)) {
            if (! $staff || (! $staff->can(Permission::BookingConfirm->value) && ! $staff->hasPermissionTo(Permission::BookingConfirm->value))) {
                throw new InvalidArgumentException('Actor does not have booking.confirm permission.');
            }
        }

        if (trim($notes) === '') {
            throw new InvalidArgumentException('Confirmation customer-communication notes cannot be empty.');
        }

        return DB::transaction(function () use ($booking, $staff, $channel, $notes, $overrideReason, $proposal) {
            // Lock vehicle row
            Vehicle::where('id', $booking->vehicle_id)->lockForUpdate()->firstOrFail();

            $booking->refresh();

            // Unchanged guard: verify request payload hash matches if no proposal override
            if ($proposal === null && $booking->request_hash && ($booking->airportPickupDetail || $booking->airportTransferDetail || $booking->rideDetail || $booking->rentDetail)) {
                $currentHash = $booking->computeRequestHash();
                if (! hash_equals($booking->request_hash, $currentHash)) {
                    throw new InvalidArgumentException('Booking request payload has changed since submission. Re-estimate required.');
                }
            }

            // Confirm availability schedule block
            if ($proposal && $proposal->scheduleBlock) {
                $this->availabilityService->confirmProposalBlock(
                    proposalBlock: $proposal->scheduleBlock,
                    priorConfirmedBlock: $booking->scheduleBlock,
                    actor: $staff,
                    overrideReason: $overrideReason,
                );
                $booking->schedule_block_id = $proposal->schedule_block_id;
            } elseif ($booking->scheduleBlock) {
                $this->availabilityService->confirmSubmittedBlock(
                    block: $booking->scheduleBlock,
                    actor: $staff,
                    overrideReason: $overrideReason,
                );

                if ($booking->scheduleBlock->group_id) {
                    VehicleScheduleBlock::where('group_id', $booking->scheduleBlock->group_id)
                        ->where('id', '!=', $booking->scheduleBlock->id)
                        ->where('status', ScheduleBlockStatus::Active->value)
                        ->update([
                            'kind' => ScheduleBlockKind::ConfirmedBooking->value,
                            'expires_at' => null,
                        ]);
                }
            }

            $policyVersion = (string) config('booking.policy_version', '2026.1');
            $now = Carbon::now('Asia/Colombo');
            $booking->update([
                'status' => BookingStatus::Confirmed,
                'pending_revision' => false,
                'confirmed_at' => $now,
                'confirmation_channel' => $channel,
                'confirmation_notes' => trim($notes),
                'assigned_staff_id' => $staff ? $staff->id : $booking->assigned_staff_id,
            ]);

            if ($proposal) {
                $this->hydrateFromProposalSnapshot($booking, $proposal);
            }

            $this->auditService->record(
                action: 'booking.confirmed',
                actor: $staff,
                summary: "Confirmed booking {$booking->reference} via {$channel->label()}",
                metadata: [
                    'booking_id' => $booking->id,
                    'reference' => $booking->reference,
                    'channel' => $channel->value,
                    'notes' => trim($notes),
                    'staff_id' => $staff?->id,
                    'override_reason' => $overrideReason,
                    'policy_version' => $policyVersion,
                    'proposal_id' => $proposal?->id,
                ],
                auditable: $booking,
                actorType: $staff ? 'staff' : ($channel === ConfirmationChannel::OnlinePayment ? 'system' : 'guest')
            );

            $plainGuestToken = bin2hex(random_bytes(32));
            GuestAccessToken::create([
                'booking_id' => $booking->id,
                'token_hash' => GuestAccessToken::hashToken($plainGuestToken),
                'purpose' => 'guest_access',
                'expires_at' => Carbon::now('Asia/Colombo')->addDays(30),
            ]);

            $statusUrl = route('booking.status', [
                'reference' => $booking->reference,
                'token' => $plainGuestToken,
            ]);

            $this->outboxService->record(
                eventType: 'booking.confirmed',
                aggregate: $booking,
                payload: [
                    'booking_id' => $booking->id,
                    'reference' => $booking->reference,
                    'channel' => $channel->value,
                    'status_url' => $statusUrl,
                ],
                idempotencyKey: "evt_booking_{$booking->id}_confirmed_v1"
            );

            return $booking;
        });
    }

    public function decline(Booking $booking, User $staff, string $reason): Booking
    {
        if (! $booking->canBeDeclined()) {
            throw new InvalidArgumentException("Booking {$booking->reference} cannot be declined from status {$booking->status->value}.");
        }

        if (! $staff->can(Permission::BookingReview->value) && ! $staff->hasPermissionTo(Permission::BookingReview->value)) {
            throw new InvalidArgumentException('Actor does not have booking.review permission.');
        }

        if (trim($reason) === '') {
            throw new InvalidArgumentException('Decline reason cannot be empty.');
        }

        return DB::transaction(function () use ($booking, $staff, $reason) {
            if ($booking->scheduleBlock) {
                if ($booking->scheduleBlock->group_id) {
                    $this->availabilityService->releaseGroup(
                        groupId: $booking->scheduleBlock->group_id,
                        reason: "Declined: {$reason}",
                        actor: $staff
                    );
                } else {
                    $this->availabilityService->releaseBlock(
                        block: $booking->scheduleBlock,
                        reason: "Declined: {$reason}",
                        actor: $staff
                    );
                }
            }

            $booking->update([
                'status' => BookingStatus::Declined,
                'declined_at' => Carbon::now('Asia/Colombo'),
                'decline_reason' => trim($reason),
                'assigned_staff_id' => $staff->id,
            ]);

            // Void any active proposal
            if ($booking->currentProposal && ! $booking->currentProposal->status->isTerminal()) {
                $booking->currentProposal->update([
                    'status' => ProposalStatus::Declined,
                ]);
            }

            $this->auditService->record(
                action: 'booking.declined',
                actor: $staff,
                summary: "Declined booking {$booking->reference} (reason: {$reason})",
                metadata: [
                    'booking_id' => $booking->id,
                    'reference' => $booking->reference,
                    'reason' => trim($reason),
                    'staff_id' => $staff->id,
                ],
                auditable: $booking,
                actorType: 'staff'
            );

            $this->outboxService->record(
                eventType: 'booking.declined',
                aggregate: $booking,
                payload: [
                    'booking_id' => $booking->id,
                    'reference' => $booking->reference,
                    'decline_reason' => trim($reason),
                ],
                idempotencyKey: "evt_booking_{$booking->id}_declined_v1"
            );

            return $booking;
        });
    }

    public function cancel(Booking $booking, string $reason, ?User $actor = null): Booking
    {
        if (! $booking->canBeCancelled()) {
            throw new InvalidArgumentException("Booking {$booking->reference} cannot be cancelled from status {$booking->status->value}.");
        }

        if ($actor && ! $actor->can(Permission::BookingCancel->value) && ! $actor->hasPermissionTo(Permission::BookingCancel->value)) {
            throw new InvalidArgumentException('Actor does not have booking.cancel permission.');
        }

        if (trim($reason) === '') {
            throw new InvalidArgumentException('Cancellation reason cannot be empty.');
        }

        if ($booking->currentDriverAssignment) {
            $activeStatus = $booking->currentDriverAssignment->status;
            if (in_array($activeStatus, [DriverTripStatus::EnRoute, DriverTripStatus::Arrived, DriverTripStatus::Started], true) && $actor === null) {
                throw new InvalidArgumentException("Cannot cancel booking {$booking->reference}: trip is already in progress ({$activeStatus->label()}). Staff assistance required.");
            }
        }

        return DB::transaction(function () use ($booking, $reason, $actor) {
            if ($booking->scheduleBlock) {
                if ($booking->scheduleBlock->group_id) {
                    $this->availabilityService->releaseGroup(
                        groupId: $booking->scheduleBlock->group_id,
                        reason: "Cancelled: {$reason}",
                        actor: $actor
                    );
                } else {
                    $this->availabilityService->releaseBlock(
                        block: $booking->scheduleBlock,
                        reason: "Cancelled: {$reason}",
                        actor: $actor
                    );
                }
            }

            // Also release any active proposal hold block
            if ($booking->currentProposal && $booking->currentProposal->scheduleBlock) {
                if ($booking->currentProposal->scheduleBlock->kind === ScheduleBlockKind::ProposalHold) {
                    $this->availabilityService->releaseBlock(
                        block: $booking->currentProposal->scheduleBlock,
                        reason: "Cancelled: {$reason}",
                        actor: $actor
                    );
                }
            }

            $isLate = false;
            $serviceStart = $booking->getServiceStartTime();
            if ($serviceStart && $actor === null && $booking->status === BookingStatus::Confirmed) {
                $cutoffHours = $booking->service_type === ServiceType::Rent ? 48 : 24;
                if (Carbon::now('Asia/Colombo')->diffInHours($serviceStart, false) < $cutoffHours) {
                    $isLate = true;
                }
            }

            $booking->update([
                'status' => BookingStatus::Cancelled,
                'cancelled_at' => Carbon::now('Asia/Colombo'),
                'cancellation_reason' => trim($reason),
            ]);

            // Void any active proposal
            if ($booking->currentProposal && ! $booking->currentProposal->status->isTerminal()) {
                $booking->currentProposal->update([
                    'status' => ProposalStatus::Superseded,
                ]);
            }

            if ($booking->currentDriverAssignment && ! $booking->currentDriverAssignment->status->isTerminal()) {
                $booking->currentDriverAssignment->update([
                    'status' => DriverTripStatus::Cancelled,
                ]);
            }

            DriverAccessToken::where('booking_id', $booking->id)
                ->whereNull('revoked_at')
                ->update([
                    'revoked_at' => Carbon::now('Asia/Colombo'),
                    'revocation_reason' => 'booking_cancelled',
                ]);

            $this->auditService->record(
                action: 'booking.cancelled',
                actor: $actor,
                summary: "Cancelled booking {$booking->reference} (reason: {$reason})",
                metadata: [
                    'booking_id' => $booking->id,
                    'reference' => $booking->reference,
                    'reason' => trim($reason),
                    'is_late_cancellation' => $isLate,
                ],
                auditable: $booking,
                actorType: $actor ? 'staff' : 'guest'
            );

            $this->outboxService->record(
                eventType: 'booking.cancelled',
                aggregate: $booking,
                payload: [
                    'booking_id' => $booking->id,
                    'reference' => $booking->reference,
                    'cancellation_reason' => trim($reason),
                    'is_late_cancellation' => $isLate,
                ],
                idempotencyKey: "evt_booking_{$booking->id}_cancelled_v1"
            );

            return $booking;
        });
    }

    public function expirePending(Booking $booking): Booking
    {
        if (! $booking->isPending()) {
            return $booking;
        }

        return DB::transaction(function () use ($booking) {
            if ($booking->vehicle_id) {
                Vehicle::where('id', $booking->vehicle_id)->lockForUpdate()->first();
            }

            $booking->refresh();

            if ($booking->status !== BookingStatus::Submitted) {
                return $booking;
            }

            if ($booking->scheduleBlock) {
                if ($booking->scheduleBlock->group_id) {
                    $this->availabilityService->releaseGroup(
                        groupId: $booking->scheduleBlock->group_id,
                        reason: 'pending_ttl_expired',
                        actor: null
                    );
                } else {
                    $this->availabilityService->releaseBlock(
                        block: $booking->scheduleBlock,
                        reason: 'pending_ttl_expired',
                        actor: null
                    );
                }
            }

            $booking->update([
                'status' => BookingStatus::Expired,
            ]);

            $this->auditService->record(
                action: 'booking.expired',
                actor: null,
                summary: "Pending request {$booking->reference} expired automatically after TTL elapsed",
                metadata: [
                    'booking_id' => $booking->id,
                    'reference' => $booking->reference,
                    'expired_at' => Carbon::now('Asia/Colombo')->toIso8601String(),
                ],
                auditable: $booking,
                actorType: 'system'
            );

            return $booking;
        });
    }

    public function complete(Booking $booking, ?User $actor = null): Booking
    {
        if ($booking->status !== BookingStatus::Confirmed) {
            throw new InvalidArgumentException("Booking {$booking->reference} cannot be completed from status {$booking->status->value}.");
        }

        return DB::transaction(function () use ($booking, $actor) {
            /** @var Booking $lockedBooking */
            $lockedBooking = Booking::where('id', $booking->id)->lockForUpdate()->firstOrFail();

            $now = Carbon::now('Asia/Colombo');
            $lockedBooking->update([
                'status' => BookingStatus::Completed,
                'completed_at' => $now,
            ]);

            $this->auditService->record(
                action: 'booking.completed',
                actor: $actor,
                summary: "Booking {$lockedBooking->reference} marked as completed",
                metadata: [
                    'booking_id' => $lockedBooking->id,
                    'reference' => $lockedBooking->reference,
                ],
                auditable: $lockedBooking,
                actorType: $actor ? 'staff' : 'driver'
            );

            $this->outboxService->record(
                eventType: 'booking.completed',
                aggregate: $lockedBooking,
                payload: [
                    'booking_id' => $lockedBooking->id,
                    'reference' => $lockedBooking->reference,
                    'completed_at' => $now->toIso8601String(),
                ],
                idempotencyKey: "evt_booking_{$lockedBooking->id}_completed_v1"
            );

            return $lockedBooking;
        });
    }
}
