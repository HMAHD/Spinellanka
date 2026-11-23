<?php

namespace App\Domain\Catalog\Services;

use App\Domain\Audit\Services\AuditService;
use App\Domain\Catalog\Enums\DriverStatus;
use App\Models\Driver;
use App\Models\User;
use App\Models\VehicleDriverAssignment;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DriverService
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createDriver(array $attributes, ?User $actor = null): Driver
    {
        return DB::transaction(function () use ($attributes, $actor) {
            $driver = Driver::create($attributes);

            $this->auditService->record(
                action: 'fleet.driver.created',
                actor: $actor,
                summary: "Created driver {$driver->public_name} ({$driver->phone})",
                metadata: [
                    'driver_id' => $driver->id,
                    'public_name' => $driver->public_name,
                    'phone' => $driver->phone,
                    'status' => $driver->status->value,
                ],
                auditable: $driver
            );

            return $driver;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateDriver(Driver $driver, array $attributes, ?User $actor = null): Driver
    {
        return DB::transaction(function () use ($driver, $attributes, $actor) {
            $oldStatus = $driver->status;
            $oldAttributes = $driver->only(array_keys($attributes));

            $newStatus = isset($attributes['status'])
                ? ($attributes['status'] instanceof DriverStatus ? $attributes['status'] : DriverStatus::from((string) $attributes['status']))
                : null;

            $driver->update($attributes);

            // Handle status change side-effects
            if ($newStatus !== null && $newStatus !== $oldStatus) {
                $this->handleStatusChange($driver, $oldStatus, $newStatus, $actor);
            }

            $diff = array_diff_assoc(
                $this->normalizeAttributesForDiff($attributes),
                $this->normalizeAttributesForDiff($oldAttributes)
            );
            if (! empty($diff)) {
                $this->auditService->record(
                    action: 'fleet.driver.updated',
                    actor: $actor,
                    summary: "Updated driver {$driver->public_name}",
                    metadata: [
                        'driver_id' => $driver->id,
                        'changes' => $diff,
                    ],
                    auditable: $driver
                );
            }

            return $driver;
        });
    }

    public function updateStatus(
        Driver $driver,
        DriverStatus $newStatus,
        ?string $reason = null,
        ?User $actor = null
    ): Driver {
        return DB::transaction(function () use ($driver, $newStatus, $reason, $actor) {
            $oldStatus = $driver->status;
            $driver->update(['status' => $newStatus]);

            $this->handleStatusChange($driver, $oldStatus, $newStatus, $actor, $reason);

            return $driver;
        });
    }

    public function deleteDriver(Driver $driver, ?User $actor = null): void
    {
        DB::transaction(function () use ($driver, $actor) {
            if ($driver->assignments()->exists()) {
                throw new InvalidArgumentException('Cannot delete driver with assignment history.');
            }

            $driverId = $driver->id;
            $name = $driver->public_name;

            $driver->delete();

            $this->auditService->record(
                action: 'fleet.driver.deleted',
                actor: $actor,
                summary: "Deleted driver {$name} (#{$driverId})",
                metadata: [
                    'driver_id' => $driverId,
                    'public_name' => $name,
                ],
                auditable: null
            );
        });
    }

    protected function handleStatusChange(
        Driver $driver,
        DriverStatus $oldStatus,
        DriverStatus $newStatus,
        ?User $actor = null,
        ?string $reason = null
    ): void {
        // If driver is deactivated or put on leave, auto-close open-ended vehicle assignments
        if ($newStatus !== DriverStatus::Active) {
            $now = Carbon::now('Asia/Colombo');
            $activeAssignments = $driver->assignments()->whereNull('ends_at')->get();
            foreach ($activeAssignments as $assignment) {
                if ($assignment instanceof VehicleDriverAssignment) {
                    $assignment->update(['ends_at' => $now]);
                    $this->auditService->record(
                        action: 'fleet.driver_assignment.closed',
                        actor: $actor,
                        summary: "Auto-closed assignment for deactivated driver {$driver->public_name}",
                        metadata: ['assignment_id' => $assignment->id, 'closed_at' => $now->toIso8601String()],
                        auditable: $assignment
                    );
                }
            }
        }

        $this->auditService->record(
            action: 'fleet.driver.status_changed',
            actor: $actor,
            summary: "Changed status of driver {$driver->public_name} from {$oldStatus->value} to {$newStatus->value}",
            metadata: [
                'driver_id' => $driver->id,
                'old_status' => $oldStatus->value,
                'new_status' => $newStatus->value,
                'reason' => $reason,
            ],
            auditable: $driver
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
}
