<?php

namespace App\Policies;

use App\Domain\Identity\Permission;
use App\Models\User;
use Spatie\Permission\Models\Role;

class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::RolesManage->value);
    }

    public function view(User $user, Role $role): bool
    {
        return $user->can(Permission::RolesManage->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::RolesManage->value);
    }

    public function update(User $user, Role $role): bool
    {
        return $user->can(Permission::RolesManage->value);
    }

    public function delete(User $user, Role $role): bool
    {
        return false;
    }
}

