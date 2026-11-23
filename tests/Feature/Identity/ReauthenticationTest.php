<?php

use App\Domain\Identity\Role;
use App\Domain\Identity\Services\ReauthService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed();
});

test('reauth service detects unconfirmed session', function (): void {
    $reauth = app(ReauthService::class);
    session()->forget('auth.password_confirmed_at');

    expect($reauth->isRecentlyConfirmed())->toBeFalse();
});

test('reauth service sets timestamp and respects 15 minute window', function (): void {
    $user = User::create([
        'name' => 'Reauth User',
        'email' => 'reauth@spinellanka.test',
        'password' => Hash::make('Password123456!'),
        'is_active' => true,
        'token_version' => 1,
    ]);
    $user->assignRole(Role::Staff->value);

    $reauth = app(ReauthService::class);
    $reauth->confirm($user, 'test.action');

    expect($reauth->isRecentlyConfirmed())->toBeTrue();

    // Fast-forward 14 minutes -> still confirmed
    $this->travel(14)->minutes();
    expect($reauth->isRecentlyConfirmed())->toBeTrue();

    // Fast-forward 2 more minutes (16 min total) -> expired
    $this->travel(2)->minutes();
    expect($reauth->isRecentlyConfirmed())->toBeFalse();
});

test('consume confirmation immediately expires the re-auth timestamp', function (): void {
    $user = User::create([
        'name' => 'Reauth User 2',
        'email' => 'reauth2@spinellanka.test',
        'password' => Hash::make('Password123456!'),
        'is_active' => true,
        'token_version' => 1,
    ]);
    $user->assignRole(Role::Staff->value);

    $reauth = app(ReauthService::class);
    $reauth->confirm($user, 'tier1.action');
    expect($reauth->isRecentlyConfirmed())->toBeTrue();

    $reauth->consumeConfirmation();
    expect($reauth->isRecentlyConfirmed())->toBeFalse();
});
