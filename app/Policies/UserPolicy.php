<?php

namespace App\Policies;

use App\Domain\Identity\Permission;
use App\Domain\Identity\Role;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::UsersView->value);
    }

    public function view(User $user, User $model): bool
    {
        return $user->can(Permission::UsersView->value) || $user->id === $model->id;
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::UsersManage->value);
    }

    public function update(User $user, User $model): bool
    {
        return $user->can(Permission::UsersManage->value) || $user->id === $model->id;
    }

    public function deactivate(User $user, User $model): bool
    {
        if (! $user->can(Permission::UsersManage->value)) {
            return false;
        }

        // Cannot deactivate self
        if ($user->id === $model->id) {
            return false;
        }

        // Cannot deactivate last active owner
        if ($model->hasRole(Role::OwnerAdmin->value)) {
            $activeOwnerCount = User::role(Role::OwnerAdmin->value)
                ->where('is_active', true)
                ->count();

            if ($activeOwnerCount <= 1 && $model->is_active) {
                return false;
            }
        }

        return true;
    }

    public function delete(User $user, User $model): bool
    {
        // Staff accounts are deactivated, never deleted, to preserve relational audit integrity
        return false;
    }
}

