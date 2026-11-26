<?php

declare(strict_types=1);

namespace App\Domain\Operations\Services;

use App\Domain\Audit\Services\AuditService;
use App\Domain\Availability\Enums\ScheduleBlockStatus;
use App\Domain\Catalog\Enums\DriverStatus;
use App\Domain\Identity\Permission;
use App\Domain\Messaging\Services\OutboxService;
use App\Domain\Operations\Enums\DriverTripStatus;
use App\Models\Booking;
use App\Models\Driver;
use App\Models\DriverAccessToken;
use App\Models\DriverAssignment;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleDriverAssignment;
use App\Models\VehicleScheduleBlock;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DriverAssignmentService
{
    public function __construct(
        protected AuditService $auditService,
        protected OutboxService $outboxService,
    ) {}

    /**
     * Resolve the effective tied driver for a vehicle on a given date.
     */
    public function resolveTiedDriver(Vehicle $vehicle, Carbon $serviceDate): ?Driver
    {
        $startOfDay = $serviceDate->copy()->setTimezone('Asia/Colombo')->startOfDay();
        $endOfDay = $serviceDate->copy()->setTimezone('Asia/Colombo')->endOfDay();

        /** @var VehicleDriverAssignment|null $assignment */
        $assignment = VehicleDriverAssignment::where('vehicle_id', $vehicle->id)
            ->overlapping($startOfDay, $endOfDay)
            ->whereHas('driver', fn ($q) => $q->where('status', DriverStatus::Active->value))
            ->orderByDesc('is_permanent')
            ->orderBy('starts_at')
            ->first();

        return $assignment?->driver;
    }

    /**
     * Assign a driver to a booking with governance, conflict check, and token generation.
     *
     * @return array{assignment: DriverAssignment, plainToken: string}
     */
    public function assignDriver(
        Booking $booking,
        Driver $driver,
        User $staff,
        bool $isReplacement = false,
        ?string $replacementReason = null,
    ): array {
        if ($driver->status !== DriverStatus::Active) {
            throw new InvalidArgumentException("Cannot assign driver {$driver->public_name}: driver is not active.");
        }

        if ($isReplacement) {
            if (! $staff->can(Permission::BookingReview->value) && ! $staff->can(Permission::BookingConfirm->value)) {
                throw new InvalidArgumentException('Actor does not have permission to authorize driver replacement.');
            }

            if ($replacementReason === null || trim($replacementReason) === '') {
                throw new InvalidArgumentException('Replacement reason is required when assigning a replacement driver.');
            }
        }

        // Conflict check: ensure driver is not assigned to an overlapping active schedule block on another vehicle
        $serviceStart = $booking->scheduleBlock ? $booking->scheduleBlock->effective_start : $booking->getServiceStartTime();
        $serviceEnd = $booking->scheduleBlock ? $booking->scheduleBlock->effective_end : ($serviceStart ? $serviceStart->copy()->addHours(6) : null);

        if ($serviceStart && $serviceEnd) {
            $conflictingBlockExists = VehicleScheduleBlock::query()
                ->active()
                ->blocking()
                ->where('driver_id', $driver->id)
                ->where('vehicle_id', '!=', $booking->vehicle_id)
                ->overlapping($serviceStart, $serviceEnd)
                ->exists();

            if ($conflictingBlockExists) {
                throw new InvalidArgumentException("Driver {$driver->public_name} is already assigned to another vehicle during this time window.");
            }
        }

        return DB::transaction(function () use ($booking, $driver, $staff, $isReplacement, $replacementReason, $serviceEnd) {
            // Row-level lock on booking
            /** @var Booking $lockedBooking */
            $lockedBooking = Booking::where('id', $booking->id)->lockForUpdate()->firstOrFail();

            // Supersede any existing active assignment for this booking
            DriverAssignment::where('booking_id', $lockedBooking->id)
                ->whereNotIn('status', [DriverTripStatus::Completed->value, DriverTripStatus::Superseded->value, DriverTripStatus::Cancelled->value])
                ->update(['status' => DriverTripStatus::Superseded->value]);

            $now = Carbon::now('Asia/Colombo');

            /** @var DriverAssignment $assignment */
            $assignment = DriverAssignment::create([
                'booking_id' => $lockedBooking->id,
                'driver_id' => $driver->id,
                'vehicle_id' => $lockedBooking->vehicle_id,
                'is_replacement' => $isReplacement,
                'replacement_reason' => $isReplacement ? trim((string) $replacementReason) : null,
                'replacement_authorized_by_user_id' => $isReplacement ? $staff->id : null,
                'assigned_by_user_id' => $staff->id,
                'status' => DriverTripStatus::Assigned,
                'assigned_at' => $now,
            ]);

            // Update grouped schedule blocks so all legs receive driver attribution
            if ($lockedBooking->scheduleBlock?->group_id) {
                VehicleScheduleBlock::where('group_id', $lockedBooking->scheduleBlock->group_id)
                    ->where('status', ScheduleBlockStatus::Active->value)
                    ->update(['driver_id' => $driver->id]);
            } elseif ($lockedBooking->scheduleBlock) {
                $lockedBooking->scheduleBlock->update(['driver_id' => $driver->id]);
            }

            // Revoke active driver tokens
            DriverAccessToken::where('booking_id', $lockedBooking->id)
                ->whereNull('revoked_at')
                ->update([
                    'revoked_at' => $now,
                    'revocation_reason' => 'driver_reassigned',
                ]);

            // Generate high-entropy 256-bit token
            $plainToken = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $plainToken);
            $expiresAt = ($serviceEnd ?? $now->copy()->addHours(24))->copy()->addHours(48);

            DriverAccessToken::create([
                'booking_id' => $lockedBooking->id,
                'driver_assignment_id' => $assignment->id,
                'driver_id' => $driver->id,
                'token_hash' => $tokenHash,
                'expires_at' => $expiresAt,
            ]);

            $lockedBooking->update([
                'assigned_driver_id' => $driver->id,
                'current_driver_assignment_id' => $assignment->id,
            ]);

            $this->auditService->record(
                action: 'driver.assigned',
                actor: $staff,
                summary: "Assigned driver {$driver->public_name} to booking {$lockedBooking->reference}".($isReplacement ? ' (Replacement)' : ' (Tied)'),
                metadata: [
                    'booking_id' => $lockedBooking->id,
                    'driver_id' => $driver->id,
                    'driver_assignment_id' => $assignment->id,
                    'is_replacement' => $isReplacement,
                    'replacement_reason' => $replacementReason,
                ],
                auditable: $assignment,
                actorType: 'staff'
            );

            $this->outboxService->record(
                eventType: 'driver.assigned',
                aggregate: $assignment,
                payload: [
                    'driver_assignment_id' => $assignment->id,
                    'booking_id' => $lockedBooking->id,
                    'driver_id' => $driver->id,
                    'is_replacement' => $isReplacement,
                ],
                idempotencyKey: "evt_driver_assign_{$assignment->id}_v1"
            );

            return [
                'assignment' => $assignment,
                'plainToken' => $plainToken,
            ];
        });
    }

    /**
     * Rotate driver trip token and invalidate existing active tokens.
     */
    public function rotateTripToken(Booking $booking, User $staff, string $reason): string
    {
        if (! $staff->can(Permission::BookingReview->value) && ! $staff->can(Permission::BookingConfirm->value)) {
            throw new InvalidArgumentException('Actor does not have permission to rotate driver trip token.');
        }

        if (! $booking->current_driver_assignment_id || ! $booking->assigned_driver_id) {
            throw new InvalidArgumentException("Booking {$booking->reference} has no active driver assignment to rotate.");
        }

        return DB::transaction(function () use ($booking, $staff, $reason) {
            $now = Carbon::now('Asia/Colombo');

            DriverAccessToken::where('booking_id', $booking->id)
                ->whereNull('revoked_at')
                ->update([
                    'revoked_at' => $now,
                    'revocation_reason' => "rotated: {$reason}",
                ]);

            $serviceEnd = $booking->scheduleBlock ? $booking->scheduleBlock->effective_end : ($booking->getServiceStartTime()?->copy()->addHours(6) ?? $now->copy()->addHours(24));
            $plainToken = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $plainToken);
            $expiresAt = $serviceEnd->copy()->addHours(48);

            DriverAccessToken::create([
                'booking_id' => $booking->id,
                'driver_assignment_id' => $booking->current_driver_assignment_id,
                'driver_id' => $booking->assigned_driver_id,
                'token_hash' => $tokenHash,
                'expires_at' => $expiresAt,
            ]);

            $this->auditService->record(
                action: 'driver.token_rotated',
                actor: $staff,
                summary: "Rotated driver trip link for booking {$booking->reference}",
                metadata: [
                    'booking_id' => $booking->id,
                    'driver_assignment_id' => $booking->current_driver_assignment_id,
                    'reason' => $reason,
                ],
                auditable: $booking,
                actorType: 'staff'
            );

            return $plainToken;
        });
    }

    /**
     * Revoke driver access and cancel active assignment.
     */
    public function revokeDriverAccess(Booking $booking, User $staff, string $reason): void
    {
        if (! $staff->can(Permission::BookingReview->value) && ! $staff->can(Permission::BookingConfirm->value)) {
            throw new InvalidArgumentException('Actor does not have permission to revoke driver access.');
        }

        DB::transaction(function () use ($booking, $staff, $reason) {
            $now = Carbon::now('Asia/Colombo');

            DriverAccessToken::where('booking_id', $booking->id)
                ->whereNull('revoked_at')
                ->update([
                    'revoked_at' => $now,
                    'revocation_reason' => "revoked: {$reason}",
                ]);

            if ($booking->currentDriverAssignment && ! $booking->currentDriverAssignment->status->isTerminal()) {
                $booking->currentDriverAssignment->update([
                    'status' => DriverTripStatus::Cancelled,
                ]);
            }

            // Remove driver from schedule block(s)
            if ($booking->scheduleBlock?->group_id) {
                VehicleScheduleBlock::where('group_id', $booking->scheduleBlock->group_id)
                    ->where('status', ScheduleBlockStatus::Active->value)
                    ->update(['driver_id' => null]);
            } elseif ($booking->scheduleBlock) {
                $booking->scheduleBlock->update(['driver_id' => null]);
            }

            $booking->update([
                'assigned_driver_id' => null,
                'current_driver_assignment_id' => null,
            ]);

            $this->auditService->record(
                action: 'driver.access_revoked',
                actor: $staff,
                summary: "Revoked driver access for booking {$booking->reference}",
                metadata: [
                    'booking_id' => $booking->id,
                    'reason' => $reason,
                ],
                auditable: $booking,
                actorType: 'staff'
            );
        });
    }
}
