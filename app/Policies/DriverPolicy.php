<?php

namespace App\Policies;

use App\Domain\Identity\Permission;
use App\Models\Driver;
use App\Models\User;

class DriverPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::FleetView->value);
    }

    public function view(User $user, Driver $driver): bool
    {
        return $user->can(Permission::FleetView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::FleetManage->value);
    }

    public function update(User $user, Driver $driver): bool
    {
        return $user->can(Permission::FleetManage->value);
    }

    public function delete(User $user, Driver $driver): bool
    {
        if (! $user->can(Permission::FleetManage->value)) {
            return false;
        }

        // Prevent hard-deleting driver if assigned history exists
        return $driver->assignments()->doesntExist();
    }
}

