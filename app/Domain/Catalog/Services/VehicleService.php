<?php

namespace App\Domain\Catalog\Services;

use App\Domain\Audit\Services\AuditService;
use App\Domain\Catalog\Enums\VehicleStatus;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleDriverAssignment;
use Carbon\Carbon;
use Carbon\CarbonInterface;
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
            $oldStatus = $vehicle->status;
            $oldAttributes = $vehicle->only(array_keys($attributes));

            $newStatus = isset($attributes['status'])
                ? ($attributes['status'] instanceof VehicleStatus ? $attributes['status'] : VehicleStatus::from((string) $attributes['status']))
                : null;

            $vehicle->update($attributes);

            if ($newStatus !== null && $newStatus !== $oldStatus) {
                $this->handleStatusChange($vehicle, $oldStatus, $newStatus, $actor);
            }

            $diff = array_diff_assoc(
                $this->normalizeAttributesForDiff($attributes),
                $this->normalizeAttributesForDiff($oldAttributes)
            );
            if (! empty($diff)) {
                $this->auditService->record(
                    action: 'fleet.vehicle.updated',
                    actor: $actor,
                    summary: "Updated vehicle {$vehicle->public_name} ({$vehicle->internal_fleet_id})",
                    metadata: [
                        'vehicle_id' => $vehicle->id,
                        'changes' => $diff,
                    ],
                    auditable: $vehicle
                );
            }

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

            $this->handleStatusChange($vehicle, $oldStatus, $newStatus, $actor, $reason);

            return $vehicle;
        });
    }

    public function deleteVehicle(Vehicle $vehicle, ?User $actor = null): void
    {
        DB::transaction(function () use ($vehicle, $actor) {
            if ($vehicle->driverAssignments()->exists()) {
                throw new InvalidArgumentException('Cannot delete vehicle with assignment history.');
            }

            $vehicleId = $vehicle->id;
            $name = $vehicle->public_name;
            $fleetId = $vehicle->internal_fleet_id;

            $vehicle->delete();

            $this->auditService->record(
                action: 'fleet.vehicle.deleted',
                actor: $actor,
                summary: "Deleted vehicle {$name} ({$fleetId})",
                metadata: [
                    'vehicle_id' => $vehicleId,
                    'internal_fleet_id' => $fleetId,
                    'public_name' => $name,
                ],
                auditable: null
            );
        });
    }

    protected function handleStatusChange(
        Vehicle $vehicle,
        VehicleStatus $oldStatus,
        VehicleStatus $newStatus,
        ?User $actor = null,
        ?string $reason = null
    ): void {
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

        $oldStatusValue = $oldStatus->value;
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
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function normalizeAttributesForDiff(array $attributes): array
    {
        $normalized = [];
        foreach ($attributes as $key => $val) {
            if ($val instanceof \BackedEnum) {
                $normalized[$key] = $val->value;
            } elseif ($val instanceof \UnitEnum) {
                $normalized[$key] = $val->name;
            } elseif ($val instanceof CarbonInterface) {
                $normalized[$key] = $val->toIso8601String();
            } elseif (is_array($val)) {
                $normalized[$key] = json_encode($val);
            } else {
                $normalized[$key] = (string) $val;
            }
        }

        return $normalized;
    }

    protected function validateTurnaroundBuffer(int $minutes): void
    {
        if ($minutes < 30 || $minutes > 720) {
            throw new InvalidArgumentException("Turnaround buffer minutes must be between 30 and 720 minutes. Got {$minutes}.");
        }
    }
}
