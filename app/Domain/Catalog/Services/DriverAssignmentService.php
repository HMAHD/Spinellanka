<?php

namespace App\Domain\Catalog\Services;

use App\Domain\Audit\Services\AuditService;
use App\Domain\Catalog\Enums\DriverStatus;
use App\Domain\Catalog\Exceptions\DriverAssignmentConflictException;
use App\Models\Driver;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleDriverAssignment;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class DriverAssignmentService
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    public function assignPermanentDriver(
        Vehicle $vehicle,
        Driver $driver,
        CarbonInterface $startsAt,
        ?string $reason = null,
        ?User $assignedBy = null
    ): VehicleDriverAssignment {
        return DB::transaction(function () use ($vehicle, $driver, $startsAt, $reason, $assignedBy) {
            // 1. Canonical Lock Ordering: deterministic locking to prevent AB-BA deadlocks
            $this->lockEntitiesCanonical($vehicle->id, $driver->id);

            $lockedVehicle = Vehicle::findOrFail($vehicle->id);
            $lockedDriver = Driver::findOrFail($driver->id);

            if ($lockedDriver->status !== DriverStatus::Active) {
                throw new DriverAssignmentConflictException("Driver '{$lockedDriver->public_name}' is not active.");
            }

            $startsAtLocal = Carbon::parse($startsAt)->setTimezone('Asia/Colombo');

            // 2. Auto-close any existing open-ended permanent assignment for this vehicle
            $activePerm = VehicleDriverAssignment::query()
                ->where('vehicle_id', $lockedVehicle->id)
                ->where('is_permanent', true)
                ->whereNull('ends_at')
                ->lockForUpdate()
                ->first();

            if ($activePerm) {
                if (Carbon::parse($activePerm->starts_at)->greaterThanOrEqualTo($startsAtLocal)) {
                    throw new DriverAssignmentConflictException('New permanent assignment starts before or at the same time as prior assignment.');
                }
                $activePerm->update(['ends_at' => $startsAtLocal]);
                $this->auditService->record(
                    action: 'fleet.driver_assignment.closed',
                    actor: $assignedBy,
                    summary: "Closed permanent driver assignment for vehicle {$lockedVehicle->public_name}",
                    metadata: ['assignment_id' => $activePerm->id, 'closed_at' => $startsAtLocal->toIso8601String()],
                    auditable: $activePerm
                );
            }

            // 3. Validate Overlap using Locking Reads (lockForUpdate)
            $this->validateDriverNoOverlap($lockedDriver->id, $startsAtLocal, null);
            $this->validateVehiclePermanentNoOverlap($lockedVehicle->id, $startsAtLocal, null);

            // 4. Create new permanent assignment
            $assignment = VehicleDriverAssignment::create([
                'vehicle_id' => $lockedVehicle->id,
                'driver_id' => $lockedDriver->id,
                'starts_at' => $startsAtLocal,
                'ends_at' => null,
                'is_permanent' => true,
                'reason' => $reason ?? 'Primary tied driver',
                'assigned_by_user_id' => $assignedBy?->id,
            ]);

            $this->auditService->record(
                action: 'fleet.driver_assignment.created',
                actor: $assignedBy,
                summary: "Assigned permanent driver {$lockedDriver->public_name} to vehicle {$lockedVehicle->public_name}",
                metadata: [
                    'vehicle_id' => $lockedVehicle->id,
                    'driver_id' => $lockedDriver->id,
                    'is_permanent' => true,
                    'starts_at' => $startsAtLocal->toIso8601String(),
                ],
                auditable: $assignment
            );

            return $assignment;
        });
    }

    public function assignTemporaryDriver(
        Vehicle $vehicle,
        Driver $driver,
        CarbonInterface $startsAt,
        CarbonInterface $endsAt,
        ?string $reason = null,
        ?User $assignedBy = null,
        ?int $excludeAssignmentId = null
    ): VehicleDriverAssignment {
        return DB::transaction(function () use ($vehicle, $driver, $startsAt, $endsAt, $reason, $assignedBy, $excludeAssignmentId) {
            $this->lockEntitiesCanonical($vehicle->id, $driver->id);

            $lockedVehicle = Vehicle::findOrFail($vehicle->id);
            $lockedDriver = Driver::findOrFail($driver->id);

            if ($lockedDriver->status !== DriverStatus::Active) {
                throw new DriverAssignmentConflictException("Driver '{$lockedDriver->public_name}' is not active.");
            }

            $startsAtLocal = Carbon::parse($startsAt)->setTimezone('Asia/Colombo');
            $endsAtLocal = Carbon::parse($endsAt)->setTimezone('Asia/Colombo');

            if ($endsAtLocal->lessThanOrEqualTo($startsAtLocal)) {
                throw new DriverAssignmentConflictException('Temporary assignment ends_at must be strictly after starts_at.');
            }

            // Universal Physical Driver Exclusivity: Driver cannot be double-booked anywhere
            $this->validateDriverNoOverlap($lockedDriver->id, $startsAtLocal, $endsAtLocal, $excludeAssignmentId);

            // Vehicle Temporary Exclusivity: Vehicle cannot have two overlapping temporary drivers
            $this->validateVehicleTemporaryNoOverlap($lockedVehicle->id, $startsAtLocal, $endsAtLocal, $excludeAssignmentId);

            $assignment = VehicleDriverAssignment::create([
                'vehicle_id' => $lockedVehicle->id,
                'driver_id' => $lockedDriver->id,
                'starts_at' => $startsAtLocal,
                'ends_at' => $endsAtLocal,
                'is_permanent' => false,
                'reason' => $reason,
                'assigned_by_user_id' => $assignedBy?->id,
            ]);

            $this->auditService->record(
                action: 'fleet.driver_assignment.created',
                actor: $assignedBy,
                summary: "Assigned temporary driver {$lockedDriver->public_name} to vehicle {$lockedVehicle->public_name}",
                metadata: [
                    'vehicle_id' => $lockedVehicle->id,
                    'driver_id' => $lockedDriver->id,
                    'is_permanent' => false,
                    'starts_at' => $startsAtLocal->toIso8601String(),
                    'ends_at' => $endsAtLocal->toIso8601String(),
                ],
                auditable: $assignment
            );

            return $assignment;
        });
    }

    public function endAssignment(
        VehicleDriverAssignment $assignment,
        CarbonInterface $endsAt,
        ?User $closedBy = null
    ): VehicleDriverAssignment {
        return DB::transaction(function () use ($assignment, $endsAt, $closedBy) {
            $locked = VehicleDriverAssignment::where('id', $assignment->id)->lockForUpdate()->firstOrFail();
            $endsAtLocal = Carbon::parse($endsAt)->setTimezone('Asia/Colombo');

            if ($endsAtLocal->lessThanOrEqualTo(Carbon::parse($locked->starts_at))) {
                throw new DriverAssignmentConflictException('Assignment ends_at must be strictly after starts_at.');
            }

            $locked->update(['ends_at' => $endsAtLocal]);

            $this->auditService->record(
                action: 'fleet.driver_assignment.closed',
                actor: $closedBy,
                summary: "Ended driver assignment {$locked->id}",
                metadata: ['assignment_id' => $locked->id, 'closed_at' => $endsAtLocal->toIso8601String()],
                auditable: $locked
            );

            return $locked;
        });
    }

    public function getEffectiveDriver(Vehicle $vehicle, CarbonInterface $at): ?Driver
    {
        $atLocal = Carbon::parse($at)->setTimezone('Asia/Colombo');

        // Precedence 1: Active temporary replacement assignment
        $tempAssignment = VehicleDriverAssignment::query()
            ->where('vehicle_id', $vehicle->id)
            ->where('is_permanent', false)
            ->where('starts_at', '<=', $atLocal)
            ->where('ends_at', '>', $atLocal)
            ->with('driver')
            ->first();

        if ($tempAssignment && $tempAssignment->driver instanceof Driver && $tempAssignment->driver->status === DriverStatus::Active) {
            return $tempAssignment->driver;
        }

        // Precedence 2: Base permanent tied driver
        $permAssignment = VehicleDriverAssignment::query()
            ->where('vehicle_id', $vehicle->id)
            ->where('is_permanent', true)
            ->where('starts_at', '<=', $atLocal)
            ->where(function ($q) use ($atLocal) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>', $atLocal);
            })
            ->with('driver')
            ->first();

        if ($permAssignment && $permAssignment->driver instanceof Driver && $permAssignment->driver->status === DriverStatus::Active) {
            return $permAssignment->driver;
        }

        return null;
    }

    protected function lockEntitiesCanonical(int $vehicleId, int $driverId): void
    {
        // Enforce strict lock acquisition order to avoid AB-BA deadlocks
        Vehicle::where('id', $vehicleId)->lockForUpdate()->firstOrFail();
        Driver::where('id', $driverId)->lockForUpdate()->firstOrFail();
    }

    protected function validateDriverNoOverlap(int $driverId, Carbon $start, ?Carbon $end, ?int $excludeId = null): void
    {
        $conflict = VehicleDriverAssignment::query()
            ->where('driver_id', $driverId)
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->overlapping($start, $end)
            ->lockForUpdate()
            ->exists();

        if ($conflict) {
            throw new DriverAssignmentConflictException('Driver is already scheduled on an overlapping assignment.');
        }
    }

    protected function validateVehiclePermanentNoOverlap(int $vehicleId, Carbon $start, ?Carbon $end, ?int $excludeId = null): void
    {
        $conflict = VehicleDriverAssignment::query()
            ->where('vehicle_id', $vehicleId)
            ->where('is_permanent', true)
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->overlapping($start, $end)
            ->lockForUpdate()
            ->exists();

        if ($conflict) {
            throw new DriverAssignmentConflictException('Vehicle already has an overlapping permanent assignment.');
        }
    }

    protected function validateVehicleTemporaryNoOverlap(int $vehicleId, Carbon $start, Carbon $end, ?int $excludeId = null): void
    {
        $conflict = VehicleDriverAssignment::query()
            ->where('vehicle_id', $vehicleId)
            ->where('is_permanent', false)
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->where('starts_at', '<', $end)
            ->where('ends_at', '>', $start)
            ->lockForUpdate()
            ->exists();

        if ($conflict) {
            throw new DriverAssignmentConflictException('Vehicle already has a temporary driver covering this window.');
        }
    }
}
