<?php

use App\Domain\Identity\Role;
use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed();
});

test('create owner command provisions owner with generate password option', function (): void {
    $this->artisan('staff:create-owner', [
        '--name' => 'First Admin',
        '--email' => 'admin@spinellanka.com',
        '--generate-password' => true,
        '--force' => true,
    ])
        ->expectsOutputToContain('Owner account successfully provisioned for admin@spinellanka.com')
        ->assertSuccessful();

    $user = User::where('email', 'admin@spinellanka.com')->first();
    expect($user)->not->toBeNull();
    expect($user->is_active)->toBeTrue();
    expect($user->hasRole(Role::OwnerAdmin->value))->toBeTrue();

    // Verify system audit event was logged
    $audit = AuditEvent::where('action', 'user.created')
        ->where('summary', 'like', '%admin@spinellanka.com%')
        ->first();
    expect($audit)->not->toBeNull();
    expect($audit->actor_type)->toBe('system');
});

test('create owner command rejects duplicate email', function (): void {
    User::create([
        'name' => 'Existing User',
        'email' => 'duplicate@spinellanka.com',
        'password' => bcrypt('Password123456!'),
        'is_active' => true,
        'token_version' => 1,
    ]);

    $this->artisan('staff:create-owner', [
        '--name' => 'Another Owner',
        '--email' => 'duplicate@spinellanka.com',
        '--generate-password' => true,
        '--force' => true,
    ])
        ->assertFailed();
});
