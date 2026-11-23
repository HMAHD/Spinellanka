<?php

use App\Domain\Identity\Role;
use App\Domain\Identity\Services\SessionRevocationService;
use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed();
    config(['session.driver' => 'database']);
});

test('staff shell end-to-end journey: login, mfa, denied access, logout, and revocation', function (): void {
    // 1. First-Owner Provisioning via CLI
    $this->artisan('staff:create-owner', [
        '--name' => 'Lead Owner',
        '--email' => 'lead@spinellanka.test',
        '--generate-password' => true,
        '--force' => true,
    ])->assertSuccessful();

    $owner = User::where('email', 'lead@spinellanka.test')->firstOrFail();
    expect($owner->hasRole(Role::OwnerAdmin->value))->toBeTrue();

    // 2. Unenrolled owner visits /admin and is redirected to MFA setup
    $response = $this->actingAs($owner)->get('/admin');
    $response->assertRedirect('/admin/multi-factor-authentication/set-up');

    // 3. Complete MFA enrollment
    $owner->app_authentication_secret = 'mock_totp_secret_for_lead';
    $owner->save();

    // 4. Enrolled owner visits /admin and /admin/users successfully
    $this->actingAs($owner)->get('/admin')->assertOk();
    $this->actingAs($owner)->get('/admin/users')->assertOk();
    $this->actingAs($owner)->get('/admin/audit-events')->assertOk();

    // 5. Create a Staff user (operational role only)
    $staff = User::create([
        'name' => 'Operational Staff',
        'email' => 'operations@spinellanka.test',
        'password' => Hash::make('StaffPassword123!'),
        'is_active' => true,
        'token_version' => 1,
    ]);
    $staff->app_authentication_secret = 'mock_totp_secret_staff';
    $staff->save();
    $staff->assignRole(Role::Staff->value);

    // 6. Staff can access panel dashboard, but is DENIED access to staff management (/admin/users)
    $this->flushSession();
    $resp = $this->actingAs($staff)->get('/admin');
    $resp->assertOk();
    $this->actingAs($staff)->get('/admin/users')->assertStatus(403);

    // 7. Session revocation for staff user
    $revocationService = app(SessionRevocationService::class);
    $revocationService->revokeUserSessions($staff);
    expect($staff->fresh()->token_version)->toBe(2);

    // 8. Staff attempting to use old session (token_version = 1) is blocked
    $this->actingAs($staff)
        ->withSession(['token_version' => 1])
        ->get('/admin')
        ->assertStatus(403);

    // 9. Logout terminates session and is audited
    $this->actingAs($owner)->post('/admin/logout');
    expect(Auth::check())->toBeFalse();

    $logoutAudit = AuditEvent::where('action', 'auth.logout')
        ->where('actor_email', 'lead@spinellanka.test')
        ->first();
    expect($logoutAudit)->not->toBeNull();
});
