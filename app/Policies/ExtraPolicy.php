<?php

namespace App\Policies;

use App\Domain\Identity\Permission;
use App\Models\Extra;
use App\Models\User;

class ExtraPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::FleetView->value);
    }

    public function view(User $user, Extra $extra): bool
    {
        return $user->can(Permission::FleetView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::FleetManage->value);
    }

    public function update(User $user, Extra $extra): bool
    {
        return $user->can(Permission::FleetManage->value);
    }

    public function delete(User $user, Extra $extra): bool
    {
        return $user->can(Permission::FleetManage->value);
    }
}
