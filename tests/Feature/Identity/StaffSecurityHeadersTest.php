<?php

use App\Domain\Identity\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed();
});

test('staff routes return strict security headers and noindex', function (): void {
    $owner = User::create([
        'name' => 'Header Owner',
        'email' => 'headerowner@spinellanka.test',
        'password' => Hash::make('Password123456!'),
        'is_active' => true,
        'token_version' => 1,
    ]);
    $owner->assignRole(Role::OwnerAdmin->value);

    // 1. Unauthenticated login route
    $loginResponse = $this->get('/admin/login');
    $loginResponse->assertStatus(200);
    $loginResponse->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
    $loginResponse->assertHeader('X-Frame-Options', 'DENY');
    $loginResponse->assertHeader('X-Content-Type-Options', 'nosniff');
    $loginResponse->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

    // 2. Authenticated dashboard route
    $adminResponse = $this->actingAs($owner)->get('/admin');
    $adminResponse->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
    $adminResponse->assertHeader('X-Frame-Options', 'DENY');
    $adminResponse->assertHeader('X-Content-Type-Options', 'nosniff');
    $adminResponse->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
});
