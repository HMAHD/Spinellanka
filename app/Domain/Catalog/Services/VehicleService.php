<?php

namespace App\Domain\Catalog\Services;

use App\Domain\Audit\Services\AuditService;
use App\Domain\Catalog\Enums\VehicleStatus;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleDriverAssignment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class VehicleService
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createVehicle(array $attributes, ?User $actor = null): Vehicle
    {
        $buffer = isset($attributes['turnaround_buffer_minutes']) ? (int) $attributes['turnaround_buffer_minutes'] : 90;
        $this->validateTurnaroundBuffer($buffer);

        return DB::transaction(function () use ($attributes, $actor) {
            $vehicle = Vehicle::create($attributes);

            $this->auditService->record(
                action: 'fleet.vehicle.created',
                actor: $actor,
                summary: "Created vehicle {$vehicle->public_name} ({$vehicle->internal_fleet_id})",
                metadata: [
                    'vehicle_id' => $vehicle->id,
                    'internal_fleet_id' => $vehicle->internal_fleet_id,
                    'public_name' => $vehicle->public_name,
                ],
                auditable: $vehicle
            );

            return $vehicle;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateVehicle(Vehicle $vehicle, array $attributes, ?User $actor = null): Vehicle
    {
        if (isset($attributes['turnaround_buffer_minutes'])) {
            $this->validateTurnaroundBuffer((int) $attributes['turnaround_buffer_minutes']);
        }

        return DB::transaction(function () use ($vehicle, $attributes, $actor) {
            $oldAttributes = $vehicle->only(array_keys($attributes));
            $vehicle->update($attributes);

            $this->auditService->record(
                action: 'fleet.vehicle.updated',
                actor: $actor,
                summary: "Updated vehicle {$vehicle->public_name} ({$vehicle->internal_fleet_id})",
                metadata: [
                    'vehicle_id' => $vehicle->id,
                    'changes' => array_diff_assoc($attributes, $oldAttributes),
                ],
                auditable: $vehicle
            );

            return $vehicle;
        });
    }

    public function updateStatus(
        Vehicle $vehicle,
        VehicleStatus $newStatus,
        ?string $reason = null,
        ?User $actor = null
    ): Vehicle {
        return DB::transaction(function () use ($vehicle, $newStatus, $reason, $actor) {
            $oldStatus = $vehicle->status;
            $vehicle->update(['status' => $newStatus]);

            // When a vehicle is retired, close any open-ended driver assignments
            if ($newStatus === VehicleStatus::Retired) {
                $now = Carbon::now('Asia/Colombo');
                $activeAssignments = $vehicle->driverAssignments()->whereNull('ends_at')->get();
                foreach ($activeAssignments as $assignment) {
                    if ($assignment instanceof VehicleDriverAssignment) {
                        $assignment->update(['ends_at' => $now]);
                        $this->auditService->record(
                            action: 'fleet.driver_assignment.closed',
                            actor: $actor,
                            summary: "Auto-closed driver assignment for retired vehicle {$vehicle->public_name}",
                            metadata: ['assignment_id' => $assignment->id, 'closed_at' => $now->toIso8601String()],
                            auditable: $assignment
                        );
                    }
                }
            }

            $oldStatusValue = $oldStatus instanceof VehicleStatus ? $oldStatus->value : (string) $oldStatus;
            $newStatusValue = $newStatus->value;

            $this->auditService->record(
                action: 'fleet.vehicle.status_changed',
                actor: $actor,
                summary: "Changed status of vehicle {$vehicle->public_name} from {$oldStatusValue} to {$newStatusValue}",
                metadata: [
                    'vehicle_id' => $vehicle->id,
                    'old_status' => $oldStatusValue,
                    'new_status' => $newStatusValue,
                    'reason' => $reason,
                ],
                auditable: $vehicle
            );

            return $vehicle;
        });
    }

    protected function validateTurnaroundBuffer(int $minutes): void
    {
        if ($minutes < 30 || $minutes > 720) {
            throw new InvalidArgumentException("Turnaround buffer minutes must be between 30 and 720 minutes. Got {$minutes}.");
        }
    }
}
