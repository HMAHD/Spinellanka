<?php

namespace App\Policies;

use App\Domain\Identity\Permission;
use App\Models\User;
use App\Models\VehicleMedia;

class VehicleMediaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::FleetView->value);
    }

    public function view(User $user, VehicleMedia $media): bool
    {
        return $user->can(Permission::FleetView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::FleetManage->value);
    }

    public function update(User $user, VehicleMedia $media): bool
    {
        return $user->can(Permission::FleetManage->value);
    }

    public function delete(User $user, VehicleMedia $media): bool
    {
        return $user->can(Permission::FleetManage->value);
    }
}

