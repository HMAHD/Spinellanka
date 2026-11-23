<?php

use App\Domain\Identity\Permission;
use App\Domain\Identity\Role;
use App\Domain\Identity\Services\UserService;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed();
});

test('owner role is granted all 20 canonical permissions', function (): void {
    $owner = User::create([
        'name' => 'Owner Admin',
        'email' => 'owner@spinellanka.test',
        'password' => Hash::make('Password123456!'),
        'is_active' => true,
        'token_version' => 1,
    ]);
    $owner->assignRole(Role::OwnerAdmin->value);

    foreach (Permission::cases() as $permission) {
        expect($owner->can($permission->value))
            ->toBeTrue("Owner should have permission: {$permission->value}");
    }
});

test('staff role is granted 8 operational permissions and denied sensitive administrative permissions', function (): void {
    $staff = User::create([
        'name' => 'Staff Member',
        'email' => 'staff1@spinellanka.test',
        'password' => Hash::make('Password123456!'),
        'is_active' => true,
        'token_version' => 1,
    ]);
    $staff->assignRole(Role::Staff->value);

    $allowed = [
        Permission::AuditView->value,
        Permission::FleetView->value,
        Permission::BookingView->value,
        Permission::BookingReview->value,
        Permission::BookingConfirm->value,
        Permission::PaymentView->value,
        Permission::PaymentRecordOffline->value,
        Permission::IncidentView->value,
    ];

    foreach ($allowed as $perm) {
        expect($staff->can($perm))
            ->toBeTrue("Staff should have operational permission: {$perm}");
    }

    $denied = [
        Permission::UsersView->value,
        Permission::UsersManage->value,
        Permission::RolesManage->value,
        Permission::AuditExport->value,
        Permission::FleetManage->value,
        Permission::BookingOverrideConflict->value,
        Permission::BookingCancel->value,
        Permission::PaymentRefund->value,
        Permission::IncidentManage->value,
        Permission::ExportCustomerData->value,
        Permission::ExportFinancialData->value,
        Permission::ConfigManage->value,
    ];

    foreach ($denied as $perm) {
        expect($staff->can($perm))
            ->toBeFalse("Staff must NOT have sensitive permission: {$perm}");
    }
});

test('direct access to admin users route is denied for staff role with 403', function (): void {
    $staff = User::create([
        'name' => 'Staff User',
        'email' => 'staffroute@spinellanka.test',
        'password' => Hash::make('Password123456!'),
        'is_active' => true,
        'token_version' => 1,
    ]);
    $staff->app_authentication_secret = 'dummy_totp_secret';
    $staff->save();
    $staff->assignRole(Role::Staff->value);

    $this->actingAs($staff)
        ->get('/admin/users')
        ->assertStatus(403);
});

test('owner cannot deactivate self', function (): void {
    $owner = User::create([
        'name' => 'Owner Self',
        'email' => 'ownerself@spinellanka.test',
        'password' => Hash::make('Password123456!'),
        'is_active' => true,
        'token_version' => 1,
    ]);
    $owner->assignRole(Role::OwnerAdmin->value);

    $userService = app(UserService::class);

    expect(fn () => $userService->deactivate($owner, $owner))
        ->toThrow(AuthorizationException::class, 'You cannot deactivate your own account.');
});

test('owner cannot deactivate the last active owner', function (): void {
    // Only 1 active owner in the system
    $soleOwner = User::create([
        'name' => 'Sole Owner',
        'email' => 'soleowner@spinellanka.test',
        'password' => Hash::make('Password123456!'),
        'is_active' => true,
        'token_version' => 1,
    ]);
    $soleOwner->assignRole(Role::OwnerAdmin->value);

    $adminActor = User::create([
        'name' => 'Admin Actor',
        'email' => 'adminactor@spinellanka.test',
        'password' => Hash::make('Password123456!'),
        'is_active' => true,
        'token_version' => 1,
    ]);
    $adminActor->assignRole(Role::OwnerAdmin->value);

    // Deactivate the second owner so soleOwner is the only one left
    $adminActor->is_active = false;
    $adminActor->save();

    $userService = app(UserService::class);

    expect(fn () => $userService->deactivate($adminActor, $soleOwner))
        ->toThrow(AuthorizationException::class, 'Cannot deactivate or remove the Owner/Admin role from the last active owner.');
});
