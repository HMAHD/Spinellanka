<?php

declare(strict_types=1);

namespace App\Domain\Booking\Services;

use App\Domain\Audit\Services\AuditService;
use App\Domain\Availability\Enums\ScheduleBlockKind;
use App\Domain\Availability\Enums\ScheduleBlockStatus;
use App\Domain\Availability\Services\AvailabilityService;
use App\Domain\Booking\Enums\BookingStatus;
use App\Domain\Booking\Enums\ConfirmationChannel;
use App\Domain\Identity\Permission;
use App\Domain\Messaging\Services\OutboxService;
use App\Models\Booking;
use App\Models\GuestAccessToken;
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

    public function confirm(
        Booking $booking,
        User $staff,
        ConfirmationChannel $channel,
        string $notes,
        ?string $overrideReason = null,
    ): Booking {
        if (! $booking->canBeConfirmed()) {
            throw new InvalidArgumentException("Booking {$booking->reference} cannot be confirmed from status {$booking->status->value}.");
        }

        if ($booking->expires_at && $booking->expires_at->isPast() && $booking->status !== BookingStatus::UnderReview) {
            throw new InvalidArgumentException("Booking {$booking->reference} has expired and was not placed under review. Re-estimate required.");
        }

        if (! $staff->can(Permission::BookingConfirm->value) && ! $staff->hasPermissionTo(Permission::BookingConfirm->value)) {
            throw new InvalidArgumentException('Actor does not have booking.confirm permission.');
        }

        if (trim($notes) === '') {
            throw new InvalidArgumentException('Confirmation customer-communication notes cannot be empty.');
        }

        return DB::transaction(function () use ($booking, $staff, $channel, $notes, $overrideReason) {
            // Lock vehicle row
            Vehicle::where('id', $booking->vehicle_id)->lockForUpdate()->firstOrFail();

            $booking->refresh();

            // Unchanged guard: verify request payload hash matches
            if ($booking->request_hash && ($booking->airportPickupDetail || $booking->airportTransferDetail || $booking->rideDetail || $booking->rentDetail)) {
                $currentHash = $booking->computeRequestHash();
                if (! hash_equals($booking->request_hash, $currentHash)) {
                    throw new InvalidArgumentException('Booking request payload has changed since submission. Re-estimate required.');
                }
            }

            // Confirm availability schedule block
            if ($booking->scheduleBlock) {
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
                'confirmed_at' => $now,
                'confirmation_channel' => $channel,
                'confirmation_notes' => trim($notes),
                'assigned_staff_id' => $staff->id,
            ]);

            $this->auditService->record(
                action: 'booking.confirmed',
                actor: $staff,
                summary: "Confirmed booking {$booking->reference} via {$channel->label()}",
                metadata: [
                    'booking_id' => $booking->id,
                    'reference' => $booking->reference,
                    'channel' => $channel->value,
                    'notes' => trim($notes),
                    'staff_id' => $staff->id,
                    'override_reason' => $overrideReason,
                    'policy_version' => $policyVersion,
                ],
                auditable: $booking,
                actorType: 'staff'
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

            $booking->update([
                'status' => BookingStatus::Cancelled,
                'cancelled_at' => Carbon::now('Asia/Colombo'),
                'cancellation_reason' => trim($reason),
            ]);

            $this->auditService->record(
                action: 'booking.cancelled',
                actor: $actor,
                summary: "Cancelled booking {$booking->reference} (reason: {$reason})",
                metadata: [
                    'booking_id' => $booking->id,
                    'reference' => $booking->reference,
                    'reason' => trim($reason),
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
}
