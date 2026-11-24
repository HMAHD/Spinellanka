<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Identity\Permission;
use App\Models\PricingRule;
use App\Models\User;

class PricingRulePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(Permission::ConfigManage->value);
    }

    public function view(User $user, PricingRule $pricingRule): bool
    {
        return $user->hasPermissionTo(Permission::ConfigManage->value);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(Permission::ConfigManage->value);
    }

    public function update(User $user, PricingRule $pricingRule): bool
    {
        return $user->hasPermissionTo(Permission::ConfigManage->value);
    }

    public function delete(User $user, PricingRule $pricingRule): bool
    {
        return $user->hasPermissionTo(Permission::ConfigManage->value);
    }
}
