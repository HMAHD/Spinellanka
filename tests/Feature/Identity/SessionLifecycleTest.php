<?php

use App\Domain\Identity\Role;
use App\Domain\Identity\Services\SessionRevocationService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed();
    config(['session.driver' => 'database']);
});

test('session revocation deletes database rows and increments token version', function (): void {
    $user = User::create([
        'name' => 'Revoke Target',
        'email' => 'revokeme@spinellanka.test',
        'password' => Hash::make('Password123456!'),
        'is_active' => true,
        'token_version' => 1,
    ]);
    $user->assignRole(Role::Staff->value);

    // Insert mock sessions into database
    DB::table('sessions')->insert([
        [
            'id' => 'sess_1',
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0',
            'payload' => serialize(['token_version' => 1]),
            'last_activity' => now()->unix(),
        ],
        [
            'id' => 'sess_2',
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mobile Safari',
            'payload' => serialize(['token_version' => 1]),
            'last_activity' => now()->unix(),
        ],
    ]);

    expect(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(2);

    $service = app(SessionRevocationService::class);
    $count = $service->revokeUserSessions($user);

    expect($count)->toBe(2);
    expect(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0);
    expect($user->fresh()->token_version)->toBe(2);
});

test('ensure active staff middleware blocks deactivated user with active session', function (): void {
    $user = User::create([
        'name' => 'Deactivated User',
        'email' => 'deactivated@spinellanka.test',
        'password' => Hash::make('Password123456!'),
        'is_active' => false,
        'token_version' => 1,
    ]);
    $user->assignRole(Role::Staff->value);

    $this->actingAs($user)
        ->withSession(['token_version' => 1])
        ->get('/admin')
        ->assertStatus(403);
});

test('ensure active staff middleware blocks session with mismatched token version', function (): void {
    $user = User::create([
        'name' => 'Revoked Token User',
        'email' => 'revokedtoken@spinellanka.test',
        'password' => Hash::make('Password123456!'),
        'is_active' => true,
        'token_version' => 2, // Incremented after revocation
    ]);
    $user->assignRole(Role::Staff->value);

    // Request carries stale token_version = 1
    $this->actingAs($user)
        ->withSession(['token_version' => 1])
        ->get('/admin')
        ->assertStatus(403);
});
