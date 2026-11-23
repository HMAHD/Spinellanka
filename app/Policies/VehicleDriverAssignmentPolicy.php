<?php

namespace App\Policies;

use App\Domain\Identity\Permission;
use App\Models\User;
use App\Models\VehicleDriverAssignment;

class VehicleDriverAssignmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::FleetView->value);
    }

    public function view(User $user, VehicleDriverAssignment $assignment): bool
    {
        return $user->can(Permission::FleetView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::FleetManage->value);
    }

    public function update(User $user, VehicleDriverAssignment $assignment): bool
    {
        return $user->can(Permission::FleetManage->value);
    }

    public function delete(User $user, VehicleDriverAssignment $assignment): bool
    {
        return $user->can(Permission::FleetManage->value);
    }
}

