<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Identity\Permission;
use App\Models\ExchangeRateBasis;
use App\Models\User;

class ExchangeRateBasisPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(Permission::ConfigManage->value);
    }

    public function view(User $user, ExchangeRateBasis $exchangeRateBasis): bool
    {
        return $user->hasPermissionTo(Permission::ConfigManage->value);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(Permission::ConfigManage->value);
    }

    public function update(User $user, ExchangeRateBasis $exchangeRateBasis): bool
    {
        return false;
    }

    public function delete(User $user, ExchangeRateBasis $exchangeRateBasis): bool
    {
        return false;
    }
}
