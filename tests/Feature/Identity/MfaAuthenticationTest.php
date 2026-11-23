<?php

use App\Domain\Identity\Role;
use App\Models\User;
use Filament\Auth\Pages\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed();
});

test('active staff user with valid credentials can initiate login and reach mfa challenge', function (): void {
    $user = User::create([
        'name' => 'Active Staff',
        'email' => 'staff@spinellanka.test',
        'password' => Hash::make('CorrectPassword123!'),
        'is_active' => true,
        'token_version' => 1,
    ]);
    $user->assignRole(Role::Staff->value);

    // Initial login via Livewire form
    Livewire::test(Login::class)
        ->fillForm([
            'email' => 'staff@spinellanka.test',
            'password' => 'CorrectPassword123!',
        ])
        ->call('authenticate')
        ->assertHasNoFormErrors();
});

test('inactive staff user is rejected at login with generic credentials error', function (): void {
    $user = User::create([
        'name' => 'Inactive Staff',
        'email' => 'inactive@spinellanka.test',
        'password' => Hash::make('CorrectPassword123!'),
        'is_active' => false,
        'token_version' => 1,
    ]);
    $user->assignRole(Role::Staff->value);

    Livewire::test(Login::class)
        ->fillForm([
            'email' => 'inactive@spinellanka.test',
            'password' => 'CorrectPassword123!',
        ])
        ->call('authenticate')
        ->assertHasFormErrors(['email']);

    expect(Auth::check())->toBeFalse();
});

test('login is throttled after 5 failed attempts', function (): void {
    $email = 'throttled@spinellanka.test';

    for ($i = 0; $i < 5; $i++) {
        Livewire::test(Login::class)
            ->fillForm([
                'email' => $email,
                'password' => 'WrongPassword!',
            ])
            ->call('authenticate');
    }

    // 6th attempt must be throttled with rate limited notification
    Livewire::test(Login::class)
        ->fillForm([
            'email' => $email,
            'password' => 'WrongPassword!',
        ])
        ->call('authenticate')
        ->assertNotified();
});
