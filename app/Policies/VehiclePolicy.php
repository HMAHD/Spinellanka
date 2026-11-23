<?php

namespace App\Policies;

use App\Domain\Identity\Permission;
use App\Models\User;
use App\Models\Vehicle;

class VehiclePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::FleetView->value);
    }

    public function view(User $user, Vehicle $vehicle): bool
    {
        return $user->can(Permission::FleetView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::FleetManage->value);
    }

    public function update(User $user, Vehicle $vehicle): bool
    {
        return $user->can(Permission::FleetManage->value);
    }

    public function delete(User $user, Vehicle $vehicle): bool
    {
        if (! $user->can(Permission::FleetManage->value)) {
            return false;
        }

        // Prevent hard-deleting vehicle if it has assignment history
        return $vehicle->driverAssignments()->doesntExist();
    }
}

