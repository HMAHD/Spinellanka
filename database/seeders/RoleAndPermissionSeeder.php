<?php

namespace Database\Seeders;

use App\Domain\Identity\Permission as AppPermission;
use App\Domain\Identity\Role as AppRole;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleAndPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Create all 20 canonical permissions
        foreach (AppPermission::cases() as $perm) {
            Permission::firstOrCreate(['name' => $perm->value, 'guard_name' => 'web']);
        }

        // 2. Owner/Admin: Granted all 20 permissions
        $ownerRole = Role::firstOrCreate(['name' => AppRole::OwnerAdmin->value, 'guard_name' => 'web']);
        $ownerRole->syncPermissions(Permission::all());

        // 3. Staff: Granted 8 least-privilege operational permissions
        $staffRole = Role::firstOrCreate(['name' => AppRole::Staff->value, 'guard_name' => 'web']);
        $staffRole->syncPermissions([
            AppPermission::AuditView->value,
            AppPermission::FleetView->value,
            AppPermission::BookingView->value,
            AppPermission::BookingReview->value,
            AppPermission::BookingConfirm->value,
            AppPermission::PaymentView->value,
            AppPermission::PaymentRecordOffline->value,
            AppPermission::IncidentView->value,
        ]);
    }
}
