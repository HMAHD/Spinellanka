<?php

declare(strict_types=1);

namespace App\Domain\Availability\Services;

use App\Domain\Audit\Services\AuditService;
use App\Domain\Availability\DTOs\HoldResult;
use App\Domain\Availability\DTOs\ScheduleIntervalData;
use App\Domain\Availability\Enums\HoldStatus;
use App\Domain\Availability\Enums\ScheduleBlockKind;
use App\Domain\Availability\Enums\ScheduleBlockStatus;
use App\Domain\Availability\Exceptions\ScheduleConflictException;
use App\Domain\Availability\Exceptions\UnauthorizedOverrideException;
use App\Domain\Booking\ServiceType;
use App\Domain\Identity\Permission;
use App\Models\Booking;
use App\Models\BookingHold;
use App\Models\Driver;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleScheduleBlock;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class AvailabilityService
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Create a 15-minute checkout hold following the strict 7-step transactional sequence.
     */
    public function createHold(
        Vehicle $vehicle,
        Carbon $start,
        Carbon $end,
        ServiceType $service,
        ?string $sessionId = null,
        int $durationMinutes = 15,
        int $bufferBefore = 0,
        ?int $bufferAfter = null,
        ?string $groupId = null,
    ): HoldResult {
        if ($durationMinutes !== 15) {
            throw new InvalidArgumentException("Checkout hold duration is fixed at 15 minutes. Got {$durationMinutes}.");
        }

        if ($bufferBefore < 0 || ($bufferAfter !== null && $bufferAfter < 0)) {
            throw new InvalidArgumentException('Buffer minutes cannot be negative.');
        }

        $startColombo = (clone $start)->setTimezone('Asia/Colombo');
        $endColombo = (clone $end)->setTimezone('Asia/Colombo');

        if ($endColombo->lte($startColombo)) {
            throw new InvalidArgumentException('Schedule interval end time must be after start time.');
        }

        $turnaround = $bufferAfter ?? $vehicle->turnaround_buffer_minutes;
        $effectiveStart = (clone $startColombo)->subMinutes($bufferBefore);
        $effectiveEnd = (clone $endColombo)->addMinutes($turnaround);

        return DB::transaction(function () use (
            $vehicle,
            $startColombo,
            $endColombo,
            $bufferBefore,
            $turnaround,
            $effectiveStart,
            $effectiveEnd,
            $service,
            $sessionId,
            $durationMinutes,
            $groupId
        ) {
            // Step 2: Lock exact vehicle row
            /** @var Vehicle $lockedVehicle */
            $lockedVehicle = Vehicle::where('id', $vehicle->id)->lockForUpdate()->firstOrFail();

            // Step 3: Expire stale holds for that vehicle
            $this->expireStaleHoldsForVehicle($lockedVehicle->id);

            // Step 4: Query overlapping blocking intervals
            $conflicts = $this->getConflictingBlocks(
                $lockedVehicle,
                $effectiveStart,
                $effectiveEnd,
                $service
            );

            // Step 5: Reject if conflict exists (holds cannot be overridden)
            if ($conflicts->isNotEmpty()) {
                throw new ScheduleConflictException(
                    conflicts: $conflicts,
                    vehicleId: $lockedVehicle->id,
                    requestedStart: $effectiveStart,
                    requestedEnd: $effectiveEnd,
                    message: "Vehicle {$lockedVehicle->public_name} is not available for the requested interval."
                );
            }

            // Step 6: Insert authoritative schedule block & booking hold (consistent order: Block then Hold)
            $now = Carbon::now('Asia/Colombo');
            $expiresAt = (clone $now)->addMinutes($durationMinutes);
            $plainToken = Str::random(40);
            $tokenHash = BookingHold::hashToken($plainToken);

            $block = VehicleScheduleBlock::create([
                'vehicle_id' => $lockedVehicle->id,
                'kind' => ScheduleBlockKind::CheckoutHold,
                'group_id' => $groupId,
                'starts_at' => $startColombo,
                'ends_at' => $endColombo,
                'buffer_minutes_before' => $bufferBefore,
                'buffer_minutes_after' => $turnaround,
                'effective_start' => $effectiveStart,
                'effective_end' => $effectiveEnd,
                'blocks_availability' => true,
                'status' => ScheduleBlockStatus::Active,
                'is_chauffeured_only' => false,
                'expires_at' => $expiresAt,
            ]);

            $hold = BookingHold::create([
                'hold_token_hash' => $tokenHash,
                'vehicle_id' => $lockedVehicle->id,
                'schedule_block_id' => $block->id,
                'group_id' => $groupId,
                'session_id' => $sessionId,
                'service_type' => $service,
                'hold_duration_minutes' => $durationMinutes,
                'status' => HoldStatus::Active,
                'expires_at' => $expiresAt,
            ]);

            $this->auditService->record(
                action: 'availability.hold.created',
                actor: null,
                summary: "Created 15-minute checkout hold for vehicle {$lockedVehicle->public_name}",
                metadata: [
                    'vehicle_id' => $lockedVehicle->id,
                    'schedule_block_id' => $block->id,
                    'service_type' => $service->value,
                    'expires_at' => $expiresAt->toIso8601String(),
                ],
                auditable: $hold
            );

            return new HoldResult($hold, $plainToken);
        });
    }

    /**
     * Create an authoritative schedule block following the strict 7-step sequence.
     *
     * @param  array<string, mixed>  $options
     */
    public function createBlock(
        Vehicle $vehicle,
        ScheduleBlockKind $kind,
        Carbon $start,
        Carbon $end,
        int $bufferBefore = 0,
        ?int $bufferAfter = null,
        array $options = [],
        ?User $actor = null,
    ): VehicleScheduleBlock {
        if ($bufferBefore < 0 || ($bufferAfter !== null && $bufferAfter < 0)) {
            throw new InvalidArgumentException('Buffer minutes cannot be negative.');
        }

        $startColombo = (clone $start)->setTimezone('Asia/Colombo');
        $endColombo = (clone $end)->setTimezone('Asia/Colombo');

        if ($endColombo->lte($startColombo)) {
            throw new InvalidArgumentException('Schedule interval end time must be after start time.');
        }

        $turnaround = $bufferAfter ?? ($kind === ScheduleBlockKind::Buffer ? 0 : $vehicle->turnaround_buffer_minutes);
        $effectiveStart = (clone $startColombo)->subMinutes($bufferBefore);
        $effectiveEnd = (clone $endColombo)->addMinutes($turnaround);

        return DB::transaction(function () use (
            $vehicle,
            $kind,
            $startColombo,
            $endColombo,
            $bufferBefore,
            $turnaround,
            $effectiveStart,
            $effectiveEnd,
            $options,
            $actor
        ) {
            // Step 2: Lock exact vehicle row (and Driver if tied, ordered deterministically)
            /** @var Vehicle $lockedVehicle */
            $lockedVehicle = Vehicle::where('id', $vehicle->id)->lockForUpdate()->firstOrFail();

            $driverId = isset($options['driver_id']) ? (int) $options['driver_id'] : null;
            if ($driverId !== null) {
                Driver::where('id', $driverId)->lockForUpdate()->firstOrFail();
            }

            // Step 3: Expire stale holds for that vehicle
            $this->expireStaleHoldsForVehicle($lockedVehicle->id);

            // Step 4: Query overlapping blocking intervals
            /** @var ServiceType|null $serviceType */
            $serviceType = $options['service_type'] ?? null;
            /** @var int|null $replacementDriverId */
            $replacementDriverId = $options['approved_replacement_driver_id'] ?? null;

            $conflicts = $this->getConflictingBlocks(
                $lockedVehicle,
                $effectiveStart,
                $effectiveEnd,
                $serviceType,
                ignoreBlockId: $options['ignore_block_id'] ?? null,
                approvedReplacementDriverId: $replacementDriverId
            );

            $isOverride = (bool) ($options['is_override'] ?? false);
            $overrideReason = isset($options['override_reason']) ? trim((string) $options['override_reason']) : null;

            // Step 5: Check conflict & authorization
            if ($conflicts->isNotEmpty()) {
                if (! $isOverride) {
                    throw new ScheduleConflictException(
                        conflicts: $conflicts,
                        vehicleId: $lockedVehicle->id,
                        requestedStart: $effectiveStart,
                        requestedEnd: $effectiveEnd,
                        message: "Conflict detected on vehicle {$lockedVehicle->public_name} for requested interval."
                    );
                }

                $this->validateOverrideAuthorization($actor, $overrideReason);
            }

            // Step 6: Insert authoritative schedule block
            $isChauffeuredOnly = (bool) ($options['is_chauffeured_only'] ?? ($kind === ScheduleBlockKind::DriverUnavailability));
            $expiresAt = isset($options['expires_at']) ? Carbon::parse($options['expires_at'])->setTimezone('Asia/Colombo') : null;

            $block = VehicleScheduleBlock::create([
                'vehicle_id' => $lockedVehicle->id,
                'kind' => $kind,
                'source_type' => $options['source_type'] ?? null,
                'source_id' => $options['source_id'] ?? null,
                'group_id' => $options['group_id'] ?? null,
                'starts_at' => $startColombo,
                'ends_at' => $endColombo,
                'buffer_minutes_before' => $bufferBefore,
                'buffer_minutes_after' => $turnaround,
                'effective_start' => $effectiveStart,
                'effective_end' => $effectiveEnd,
                'blocks_availability' => (bool) ($options['blocks_availability'] ?? true),
                'status' => ScheduleBlockStatus::Active,
                'is_chauffeured_only' => $isChauffeuredOnly,
                'driver_id' => $driverId,
                'is_override' => $isOverride && $conflicts->isNotEmpty(),
                'override_reason' => ($isOverride && $conflicts->isNotEmpty()) ? $overrideReason : null,
                'override_authorized_by' => ($isOverride && $conflicts->isNotEmpty()) ? $actor?->id : null,
                'created_by' => $actor?->id,
                'expires_at' => $expiresAt,
                'notes' => $options['notes'] ?? null,
            ]);

            // Step 7: Commit & Audit
            $action = ($isOverride && $conflicts->isNotEmpty())
                ? 'availability.schedule_block.override_created'
                : 'availability.schedule_block.created';

            $this->auditService->record(
                action: $action,
                actor: $actor,
                summary: "Created {$kind->label()} block for vehicle {$lockedVehicle->public_name}".(($isOverride && $conflicts->isNotEmpty()) ? " with override: {$overrideReason}" : ''),
                metadata: [
                    'vehicle_id' => $lockedVehicle->id,
                    'schedule_block_id' => $block->id,
                    'kind' => $kind->value,
                    'starts_at' => $startColombo->toIso8601String(),
                    'ends_at' => $endColombo->toIso8601String(),
                    'effective_start' => $effectiveStart->toIso8601String(),
                    'effective_end' => $effectiveEnd->toIso8601String(),
                    'is_override' => $block->is_override,
                    'override_reason' => $block->override_reason,
                    'conflicts_count' => $conflicts->count(),
                ],
                auditable: $block
            );

            return $block;
        });
    }

    /**
     * All-or-nothing multi-interval command (Phase 09 Ride requirement).
     * Locks the single vehicle once, validates all legs/buffers, and commits all or none.
     *
     * @param  array<int, ScheduleIntervalData|array<string, mixed>>  $intervals
     * @param  array<string, mixed>  $options
     * @return Collection<int, VehicleScheduleBlock>
     */
    public function createMultiIntervalBlocks(
        Vehicle $vehicle,
        ScheduleBlockKind $kind,
        array $intervals,
        array $options = [],
        ?User $actor = null,
    ): Collection {
        if (empty($intervals)) {
            throw new InvalidArgumentException('At least one interval must be provided for multi-interval block creation.');
        }

        if ($kind->isHold()) {
            throw new InvalidArgumentException('Multi-interval block creation does not support hold blocks. Use dedicated hold workflows.');
        }

        $parsedIntervals = [];
        foreach ($intervals as $idx => $interval) {
            if ($interval instanceof ScheduleIntervalData) {
                $parsedIntervals[] = $interval;
            } elseif (is_array($interval)) {
                $parsedIntervals[] = new ScheduleIntervalData(
                    startsAt: Carbon::parse($interval['starts_at']),
                    endsAt: Carbon::parse($interval['ends_at']),
                    bufferMinutesBefore: (int) ($interval['buffer_minutes_before'] ?? 0),
                    bufferMinutesAfter: (int) ($interval['buffer_minutes_after'] ?? $vehicle->turnaround_buffer_minutes),
                    notes: $interval['notes'] ?? null,
                );
            } else {
                throw new InvalidArgumentException("Interval at index {$idx} must be ScheduleIntervalData or array.");
            }
        }

        // Sort intervals chronologically
        usort($parsedIntervals, fn (ScheduleIntervalData $a, ScheduleIntervalData $b) => $a->startsAt <=> $b->startsAt);

        // Validate intra-batch non-overlap
        $count = count($parsedIntervals);
        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                $a = $parsedIntervals[$i];
                $b = $parsedIntervals[$j];
                if ($a->effectiveStart()->lt($b->effectiveEnd()) && $a->effectiveEnd()->gt($b->effectiveStart())) {
                    throw new InvalidArgumentException("Intervals at index {$i} and {$j} overlap with each other.");
                }
            }
        }

        return DB::transaction(function () use (
            $vehicle,
            $kind,
            $parsedIntervals,
            $options,
            $actor
        ) {
            // Step 2: Lock exact vehicle row once deterministically
            /** @var Vehicle $lockedVehicle */
            $lockedVehicle = Vehicle::where('id', $vehicle->id)->lockForUpdate()->firstOrFail();

            // Step 3: Expire stale holds for that vehicle
            $this->expireStaleHoldsForVehicle($lockedVehicle->id);

            $groupId = $options['group_id'] ?? (string) Str::uuid();
            $isOverride = (bool) ($options['is_override'] ?? false);
            $overrideReason = isset($options['override_reason']) ? trim((string) $options['override_reason']) : null;

            // Step 4 & 5: Check every interval against existing blocks
            $allConflictsList = [];
            foreach ($parsedIntervals as $interval) {
                $conflicts = $this->getConflictingBlocks(
                    $lockedVehicle,
                    $interval->effectiveStart(),
                    $interval->effectiveEnd(),
                    $options['service_type'] ?? null,
                    ignoreBlockId: $options['ignore_block_id'] ?? null,
                    approvedReplacementDriverId: $options['approved_replacement_driver_id'] ?? null
                );

                foreach ($conflicts as $conflict) {
                    $allConflictsList[$conflict->id] = $conflict;
                }
            }
            /** @var Collection<int, VehicleScheduleBlock> $allConflicts */
            $allConflicts = new Collection(array_values($allConflictsList));

            if ($allConflicts->isNotEmpty()) {
                if (! $isOverride) {
                    throw new ScheduleConflictException(
                        conflicts: $allConflicts,
                        vehicleId: $lockedVehicle->id,
                        requestedStart: $parsedIntervals[0]->effectiveStart(),
                        requestedEnd: $parsedIntervals[count($parsedIntervals) - 1]->effectiveEnd(),
                        message: "Multi-interval command failed: one or more legs conflict on vehicle {$lockedVehicle->public_name}."
                    );
                }

                $this->validateOverrideAuthorization($actor, $overrideReason);
            }

            // Step 6: Insert all schedule blocks atomically under common group_id
            $createdBlocks = new Collection;
            $hasOverride = $isOverride && $allConflicts->isNotEmpty();
            $isChauffeuredOnly = (bool) ($options['is_chauffeured_only'] ?? ($kind === ScheduleBlockKind::DriverUnavailability));

            foreach ($parsedIntervals as $interval) {
                $block = VehicleScheduleBlock::create([
                    'vehicle_id' => $lockedVehicle->id,
                    'kind' => $kind,
                    'source_type' => $options['source_type'] ?? null,
                    'source_id' => $options['source_id'] ?? null,
                    'group_id' => $groupId,
                    'starts_at' => $interval->startsAt,
                    'ends_at' => $interval->endsAt,
                    'buffer_minutes_before' => $interval->bufferMinutesBefore,
                    'buffer_minutes_after' => $interval->bufferMinutesAfter,
                    'effective_start' => $interval->effectiveStart(),
                    'effective_end' => $interval->effectiveEnd(),
                    'blocks_availability' => (bool) ($options['blocks_availability'] ?? true),
                    'status' => ScheduleBlockStatus::Active,
                    'is_chauffeured_only' => $isChauffeuredOnly,
                    'driver_id' => $options['driver_id'] ?? null,
                    'is_override' => $hasOverride,
                    'override_reason' => $hasOverride ? $overrideReason : null,
                    'override_authorized_by' => $hasOverride ? $actor?->id : null,
                    'created_by' => $actor?->id,
                    'expires_at' => isset($options['expires_at']) ? Carbon::parse($options['expires_at'])->setTimezone('Asia/Colombo') : null,
                    'notes' => $interval->notes ?? ($options['notes'] ?? null),
                ]);

                $createdBlocks->push($block);
            }

            $this->auditService->record(
                action: $hasOverride ? 'availability.schedule_group.override_created' : 'availability.schedule_group.created',
                actor: $actor,
                summary: "Created {$createdBlocks->count()} grouped {$kind->label()} blocks for vehicle {$lockedVehicle->public_name} (group: {$groupId})",
                metadata: [
                    'vehicle_id' => $lockedVehicle->id,
                    'group_id' => $groupId,
                    'block_count' => $createdBlocks->count(),
                    'block_ids' => $createdBlocks->pluck('id')->all(),
                    'is_override' => $hasOverride,
                ],
                auditable: $createdBlocks->first()
            );

            return $createdBlocks;
        });
    }

    /**
     * Create a driver-unavailability block.
     * Blocks tied vehicle for chauffeured services, leaving Rent (self-drive) available,
     * unless an approved replacement satisfies recorded policy.
     */
    public function createDriverUnavailabilityBlock(
        Driver $driver,
        Carbon $start,
        Carbon $end,
        ?Vehicle $vehicle = null,
        ?string $reason = null,
        ?User $actor = null,
        bool $isOverride = false,
        ?string $overrideReason = null,
    ): VehicleScheduleBlock {
        $resolvedVehicle = $vehicle;

        if ($resolvedVehicle === null) {
            $assignment = $driver->assignments()
                ->overlapping($start, $end)
                ->where('is_permanent', true)
                ->first() ?? $driver->assignments()->overlapping($start, $end)->first();

            if (! $assignment) {
                throw new InvalidArgumentException("Driver {$driver->public_name} has no vehicle assignment for the requested interval.");
            }

            $resolvedVehicle = $assignment->vehicle;
        }

        return $this->createBlock(
            vehicle: $resolvedVehicle,
            kind: ScheduleBlockKind::DriverUnavailability,
            start: $start,
            end: $end,
            bufferBefore: 0,
            bufferAfter: 0,
            options: [
                'is_chauffeured_only' => true,
                'driver_id' => $driver->id,
                'notes' => $reason,
                'is_override' => $isOverride,
                'override_reason' => $overrideReason,
            ],
            actor: $actor
        );
    }

    /**
     * Read check for vehicle availability in a requested window.
     */
    public function checkAvailability(
        Vehicle $vehicle,
        Carbon $start,
        Carbon $end,
        ?ServiceType $service = null,
        int $bufferBefore = 0,
        ?int $bufferAfter = null,
        ?int $ignoreBlockId = null,
        ?int $approvedReplacementDriverId = null,
    ): bool {
        if ($bufferBefore < 0 || ($bufferAfter !== null && $bufferAfter < 0)) {
            throw new InvalidArgumentException('Buffer minutes cannot be negative.');
        }

        $startColombo = (clone $start)->setTimezone('Asia/Colombo');
        $endColombo = (clone $end)->setTimezone('Asia/Colombo');
        $turnaround = $bufferAfter ?? $vehicle->turnaround_buffer_minutes;

        $effectiveStart = (clone $startColombo)->subMinutes($bufferBefore);
        $effectiveEnd = (clone $endColombo)->addMinutes($turnaround);

        $conflicts = $this->getConflictingBlocks(
            $vehicle,
            $effectiveStart,
            $effectiveEnd,
            $service,
            $ignoreBlockId,
            $approvedReplacementDriverId
        );

        return $conflicts->isEmpty();
    }

    /**
     * Query overlapping blocking intervals including buffers and service-type semantics.
     * Enforces that permanent-driver unavailability blocks tied chauffeured service.
     *
     * @return Collection<int, VehicleScheduleBlock>
     */
    public function getConflictingBlocks(
        Vehicle $vehicle,
        Carbon $effectiveStart,
        Carbon $effectiveEnd,
        ?ServiceType $service = null,
        ?int $ignoreBlockId = null,
        ?int $approvedReplacementDriverId = null,
    ): Collection {
        $startColombo = (clone $effectiveStart)->setTimezone('Asia/Colombo');
        $endColombo = (clone $effectiveEnd)->setTimezone('Asia/Colombo');

        $query = VehicleScheduleBlock::where('vehicle_id', $vehicle->id)
            ->where('blocks_availability', true)
            ->where('status', ScheduleBlockStatus::Active->value)
            ->where('effective_start', '<', $endColombo)
            ->where('effective_end', '>', $startColombo);

        if ($ignoreBlockId !== null) {
            $query->where('id', '!=', $ignoreBlockId);
        }

        /** @var Collection<int, VehicleScheduleBlock> $candidates */
        $candidates = $query->get();

        // Cross-check: If this is a chauffeured service request without an approved replacement driver,
        // check whether the vehicle's assigned driver(s) have an active unavailability block on any vehicle!
        if (($service === null || $service->includesDriver()) && $approvedReplacementDriverId === null) {
            $assignedDriverIds = $vehicle->driverAssignments()
                ->overlapping($startColombo, $endColombo)
                ->pluck('driver_id')
                ->unique();

            if ($assignedDriverIds->isNotEmpty()) {
                $driverBlocksQuery = VehicleScheduleBlock::whereIn('driver_id', $assignedDriverIds)
                    ->where('is_chauffeured_only', true)
                    ->where('blocks_availability', true)
                    ->where('status', ScheduleBlockStatus::Active->value)
                    ->where('effective_start', '<', $endColombo)
                    ->where('effective_end', '>', $startColombo);

                if ($ignoreBlockId !== null) {
                    $driverBlocksQuery->where('id', '!=', $ignoreBlockId);
                }

                $driverBlocks = $driverBlocksQuery->get();
                $candidates = $candidates->merge($driverBlocks)->unique('id');
            }
        }

        return $candidates->filter(function (VehicleScheduleBlock $block) use ($service, $approvedReplacementDriverId) {
            // If block is driver-unavailability / chauffeured-only
            if ($block->is_chauffeured_only) {
                // Rent is self-drive; driver unavailability NEVER blocks Rent!
                if ($service === ServiceType::Rent) {
                    return false;
                }

                // If service is chauffeured (Pickup, Transfer, Ride), an approved replacement driver lifts the block
                if ($service !== null && $service->includesDriver() && $approvedReplacementDriverId !== null) {
                    return false;
                }

                return true;
            }

            // General blocks (maintenance, confirmed booking, holds) block all services
            return true;
        })->values();
    }

    /**
     * Atomically release a hold by its plaintext token.
     * Enforces consistent lock hierarchy (VehicleScheduleBlock then BookingHold)
     * and guards against read-then-lock race conditions.
     */
    public function releaseHold(string $plainToken, string $reason = 'manual_release', ?User $actor = null): bool
    {
        $tokenHash = BookingHold::hashToken($plainToken);

        return DB::transaction(function () use ($tokenHash, $reason, $actor) {
            /** @var BookingHold|null $hold */
            $hold = BookingHold::where('hold_token_hash', $tokenHash)->first();

            if (! $hold) {
                return false;
            }

            // Step 2: Lock vehicle row first
            Vehicle::where('id', $hold->vehicle_id)->lockForUpdate()->firstOrFail();

            // Re-verify hold status under lock to prevent overwriting expired/converted holds
            $hold->refresh();
            if ($hold->status !== HoldStatus::Active) {
                return false;
            }

            // Consistent update order: VehicleScheduleBlock FIRST, BookingHold SECOND
            $hold->scheduleBlock()->update([
                'status' => ScheduleBlockStatus::Released,
                'blocks_availability' => false,
            ]);

            $now = Carbon::now('Asia/Colombo');
            $hold->update([
                'status' => HoldStatus::Released,
                'released_at' => $now,
                'release_reason' => $reason,
            ]);

            $this->auditService->record(
                action: 'availability.hold.released',
                actor: $actor,
                summary: "Released checkout hold for vehicle ID {$hold->vehicle_id} (reason: {$reason})",
                metadata: [
                    'hold_id' => $hold->id,
                    'vehicle_id' => $hold->vehicle_id,
                    'reason' => $reason,
                ],
                auditable: $hold
            );

            return true;
        });
    }

    /**
     * Atomically convert an active checkout hold into a submitted request schedule block.
     * Preserves exact interval coverage under vehicle lock, avoiding any release-then-reacquire window.
     *
     * @throws ScheduleConflictException If hold is missing, expired, or inactive.
     */
    public function convertHoldToSubmittedBlock(
        string $plainHoldToken,
        Carbon $expiresAt,
        string $sourceType,
        int $sourceId,
        ?User $actor = null,
    ): VehicleScheduleBlock {
        $tokenHash = BookingHold::hashToken($plainHoldToken);

        return DB::transaction(function () use ($tokenHash, $expiresAt, $sourceType, $sourceId, $actor) {
            /** @var BookingHold|null $hold */
            $hold = BookingHold::where('hold_token_hash', $tokenHash)->first();

            if (! $hold) {
                $now = Carbon::now('Asia/Colombo');
                throw new ScheduleConflictException(
                    conflicts: new Collection,
                    vehicleId: 0,
                    requestedStart: $now,
                    requestedEnd: $now,
                    message: 'Checkout hold does not exist or invalid token.'
                );
            }

            // Lock vehicle row first (deterministic locking hierarchy)
            Vehicle::where('id', $hold->vehicle_id)->lockForUpdate()->firstOrFail();

            // Re-verify hold under lock
            $hold->refresh();
            $now = Carbon::now('Asia/Colombo');
            if ($hold->status !== HoldStatus::Active || $hold->expires_at->lte($now)) {
                $this->expireStaleHoldsForVehicle($hold->vehicle_id);
                /** @var VehicleScheduleBlock|null $existingBlock */
                $existingBlock = $hold->scheduleBlock;
                $start = $existingBlock ? $existingBlock->effective_start : $now;
                $end = $existingBlock ? $existingBlock->effective_end : $now;

                throw new ScheduleConflictException(
                    conflicts: new Collection,
                    vehicleId: $hold->vehicle_id,
                    requestedStart: $start,
                    requestedEnd: $end,
                    message: 'Checkout hold has expired or is no longer active.'
                );
            }

            /** @var VehicleScheduleBlock $block */
            $block = $hold->scheduleBlock()->lockForUpdate()->firstOrFail();

            // Update VehicleScheduleBlock FIRST, BookingHold SECOND
            $block->update([
                'kind' => ScheduleBlockKind::SubmittedRequest,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'blocks_availability' => true,
                'status' => ScheduleBlockStatus::Active,
                'expires_at' => $expiresAt,
            ]);

            $hold->update([
                'status' => HoldStatus::Converted,
                'released_at' => $now,
            ]);

            $this->auditService->record(
                action: 'availability.hold.converted_to_request',
                actor: $actor,
                summary: "Converted checkout hold to submitted request for vehicle ID {$hold->vehicle_id}",
                metadata: [
                    'hold_id' => $hold->id,
                    'schedule_block_id' => $block->id,
                    'source_type' => $sourceType,
                    'source_id' => $sourceId,
                    'expires_at' => $expiresAt->toIso8601String(),
                ],
                auditable: $block,
                actorType: $actor ? 'staff' : 'guest'
            );

            return $block;
        });
    }

    /**
     * Atomically transition a submitted request schedule block into a confirmed booking.
     * Rechecks vehicle and driver availability under lock, ignoring this block's ID.
     */
    public function confirmSubmittedBlock(
        VehicleScheduleBlock $block,
        ?User $actor = null,
        ?string $overrideReason = null,
    ): VehicleScheduleBlock {
        return DB::transaction(function () use ($block, $actor, $overrideReason) {
            // Lock Vehicle row first
            $vehicle = Vehicle::where('id', $block->vehicle_id)->lockForUpdate()->firstOrFail();

            $block->refresh();

            if ($block->status !== ScheduleBlockStatus::Active || $block->kind !== ScheduleBlockKind::SubmittedRequest) {
                throw new InvalidArgumentException("Schedule block ID {$block->id} cannot be confirmed from status {$block->status->value} and kind {$block->kind->value}.");
            }

            // Determine service type if available from hold or source
            $serviceType = $block->hold?->service_type;
            if ($serviceType === null && $block->source_type === Booking::class && $block->source_id) {
                $serviceType = Booking::where('id', $block->source_id)->value('service_type');
            }

            // Recheck availability ignoring this block
            $conflicts = $this->getConflictingBlocks(
                vehicle: $vehicle,
                effectiveStart: $block->effective_start,
                effectiveEnd: $block->effective_end,
                service: $serviceType,
                ignoreBlockId: $block->id,
            );

            if ($conflicts->isNotEmpty()) {
                $this->validateOverrideAuthorization($actor, $overrideReason);
            }

            // Update block to ConfirmedBooking; confirmed bookings do not expire automatically
            $block->update([
                'kind' => ScheduleBlockKind::ConfirmedBooking,
                'status' => ScheduleBlockStatus::Active,
                'blocks_availability' => true,
                'expires_at' => null,
            ]);

            $this->auditService->record(
                action: 'availability.schedule_block.confirmed',
                actor: $actor,
                summary: "Confirmed schedule block ID {$block->id} for vehicle ID {$block->vehicle_id}",
                metadata: [
                    'block_id' => $block->id,
                    'vehicle_id' => $block->vehicle_id,
                    'has_conflicts' => $conflicts->isNotEmpty(),
                    'override_reason' => $overrideReason,
                ],
                auditable: $block,
                actorType: 'staff'
            );

            return $block;
        });
    }

    /**
     * Atomically release an active schedule block.
     * Idempotent: early returns if block is not active.
     */
    public function releaseBlock(
        VehicleScheduleBlock $block,
        string $reason = 'manual_release',
        ?User $actor = null,
    ): VehicleScheduleBlock {
        if ($block->status !== ScheduleBlockStatus::Active) {
            return $block;
        }

        return DB::transaction(function () use ($block, $reason, $actor) {
            Vehicle::where('id', $block->vehicle_id)->lockForUpdate()->firstOrFail();

            $block->refresh();
            if ($block->status !== ScheduleBlockStatus::Active) {
                return $block;
            }

            // Consistent order: VehicleScheduleBlock FIRST, BookingHold SECOND
            $block->update([
                'status' => ScheduleBlockStatus::Released,
                'blocks_availability' => false,
                'notes' => trim(($block->notes ?? '')." | Released: {$reason}"),
            ]);

            if ($block->hold && $block->hold->status === HoldStatus::Active) {
                $block->hold->update([
                    'status' => HoldStatus::Released,
                    'released_at' => Carbon::now('Asia/Colombo'),
                    'release_reason' => $reason,
                ]);
            }

            $this->auditService->record(
                action: 'availability.schedule_block.released',
                actor: $actor,
                summary: "Released {$block->kind->label()} block for vehicle ID {$block->vehicle_id}",
                metadata: [
                    'block_id' => $block->id,
                    'vehicle_id' => $block->vehicle_id,
                    'reason' => $reason,
                ],
                auditable: $block
            );

            return $block;
        });
    }

    /**
     * Atomically release all blocks and holds under a group_id.
     */
    public function releaseGroup(string $groupId, string $reason = 'group_release', ?User $actor = null): int
    {
        return DB::transaction(function () use ($groupId, $reason, $actor) {
            $blocks = VehicleScheduleBlock::where('group_id', $groupId)
                ->where('status', ScheduleBlockStatus::Active->value)
                ->get();

            if ($blocks->isEmpty()) {
                return 0;
            }

            // Lock affected vehicles in sorted deterministic order to prevent deadlocks
            $vehicleIds = $blocks->pluck('vehicle_id')->unique()->sort()->values();
            foreach ($vehicleIds as $vId) {
                Vehicle::where('id', $vId)->lockForUpdate()->firstOrFail();
            }

            // Consistent order: VehicleScheduleBlock FIRST, BookingHold SECOND
            $count = VehicleScheduleBlock::where('group_id', $groupId)
                ->where('status', ScheduleBlockStatus::Active->value)
                ->update([
                    'status' => ScheduleBlockStatus::Released,
                    'blocks_availability' => false,
                ]);

            BookingHold::where('group_id', $groupId)
                ->where('status', HoldStatus::Active->value)
                ->update([
                    'status' => HoldStatus::Released,
                    'released_at' => Carbon::now('Asia/Colombo'),
                    'release_reason' => $reason,
                ]);

            $this->auditService->record(
                action: 'availability.schedule_group.released',
                actor: $actor,
                summary: "Released group {$groupId} containing {$count} blocks (reason: {$reason})",
                metadata: [
                    'group_id' => $groupId,
                    'released_count' => $count,
                    'reason' => $reason,
                ],
                auditable: null
            );

            return $count;
        });
    }

    /**
     * Expire stale holds for a specific vehicle within an active lock.
     */
    public function expireStaleHoldsForVehicle(int $vehicleId): int
    {
        $now = Carbon::now('Asia/Colombo');

        $staleHolds = BookingHold::where('vehicle_id', $vehicleId)
            ->where('status', HoldStatus::Active->value)
            ->where('expires_at', '<=', $now)
            ->get();

        if ($staleHolds->isEmpty()) {
            return 0;
        }

        $holdIds = $staleHolds->pluck('id')->all();
        $blockIds = $staleHolds->pluck('schedule_block_id')->all();

        // Consistent order: VehicleScheduleBlock FIRST, BookingHold SECOND
        VehicleScheduleBlock::whereIn('id', $blockIds)->update([
            'status' => ScheduleBlockStatus::Expired,
            'blocks_availability' => false,
        ]);

        BookingHold::whereIn('id', $holdIds)->update([
            'status' => HoldStatus::Expired,
        ]);

        $expiredCount = count($holdIds);

        $this->auditService->record(
            action: 'availability.hold.expired',
            actor: null,
            summary: "Expired {$expiredCount} stale checkout hold(s) for vehicle ID {$vehicleId}",
            metadata: [
                'vehicle_id' => $vehicleId,
                'expired_hold_ids' => $holdIds,
                'expired_block_ids' => $blockIds,
            ],
            auditable: null
        );

        return $expiredCount;
    }

    /**
     * Expire stale holds across all vehicles.
     * Uses independent per-vehicle transactions to prevent accumulating locks across the fleet.
     */
    public function expireStaleHolds(): int
    {
        $now = Carbon::now('Asia/Colombo');

        $staleVehicles = BookingHold::where('status', HoldStatus::Active->value)
            ->where('expires_at', '<=', $now)
            ->pluck('vehicle_id')
            ->unique()
            ->sort()
            ->values();

        $totalExpired = 0;
        foreach ($staleVehicles as $vId) {
            $totalExpired += DB::transaction(function () use ($vId) {
                Vehicle::where('id', $vId)->lockForUpdate()->firstOrFail();

                return $this->expireStaleHoldsForVehicle((int) $vId);
            });
        }

        return $totalExpired;
    }

    /**
     * Idempotently delete expired blocks and holds older than specified duration.
     */
    public function cleanupStaleBlocks(Carbon $olderThan): int
    {
        return DB::transaction(function () use ($olderThan) {
            $staleBlockIds = VehicleScheduleBlock::where('status', ScheduleBlockStatus::Expired->value)
                ->where('updated_at', '<=', $olderThan)
                ->pluck('id');

            if ($staleBlockIds->isEmpty()) {
                return 0;
            }

            BookingHold::whereIn('schedule_block_id', $staleBlockIds)->delete();

            return VehicleScheduleBlock::whereIn('id', $staleBlockIds)->delete();
        });
    }

    /**
     * Validate permission and reason for schedule conflict overrides.
     */
    protected function validateOverrideAuthorization(?User $actor, ?string $overrideReason): void
    {
        $canOverride = $actor !== null && (
            $actor->can(Permission::BookingOverrideConflict->value)
            || $actor->hasPermissionTo(Permission::BookingOverrideConflict->value)
        );

        if (! $canOverride || empty($overrideReason)) {
            throw new UnauthorizedOverrideException(
                'Overriding a schedule conflict requires the booking.override_conflict permission and a non-empty reason.'
            );
        }
    }
}
