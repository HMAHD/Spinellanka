<?php

declare(strict_types=1);

use App\Domain\Booking\Enums\BookingStatus;
use App\Domain\Booking\Services\TokenRotationService;
use App\Domain\Identity\Permission;
use App\Models\Booking;
use App\Models\GuestAccessToken;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed();
    $this->tokenService = app(TokenRotationService::class);

    $this->staff = User::create([
        'name' => 'Support Desk',
        'email' => 'support@spinellanka.test',
        'password' => Hash::make('Password123456!'),
        'is_active' => true,
        'token_version' => 1,
    ]);
    $this->staff->givePermissionTo(Permission::BookingReview->value);
});

test('token rotation revokes previous tokens and generates fresh valid token', function (): void {
    $booking = Booking::factory()->create([
        'status' => BookingStatus::Submitted,
    ]);

    $initialToken = GuestAccessToken::generateToken();
    $oldTokenRecord = GuestAccessToken::create([
        'booking_id' => $booking->id,
        'token_hash' => GuestAccessToken::hashToken($initialToken),
        'purpose' => 'guest_access',
        'expires_at' => Carbon::now('Asia/Colombo')->addDays(30),
    ]);

    // Initial token can view status page
    $this->get(route('booking.status', [
        'reference' => $booking->reference,
        'token' => $initialToken,
    ]))->assertStatus(200);

    // Rotate token
    $newToken = $this->tokenService->rotateToken($booking, 'guest_access', $this->staff);

    expect($newToken)->not->toBe($initialToken);

    $oldTokenRecord->refresh();
    expect($oldTokenRecord->revoked_at)->not->toBeNull();
    expect($oldTokenRecord->isValid())->toBeFalse();

    // Old token receives 404
    $this->get(route('booking.status', [
        'reference' => $booking->reference,
        'token' => $initialToken,
    ]))->assertStatus(404);

    // New token accesses status page
    $this->get(route('booking.status', [
        'reference' => $booking->reference,
        'token' => $newToken,
    ]))->assertStatus(200);
});

test('revoking tokens terminates access for all tokens of a booking', function (): void {
    $booking = Booking::factory()->create([
        'status' => BookingStatus::Submitted,
    ]);

    $token = GuestAccessToken::generateToken();
    GuestAccessToken::create([
        'booking_id' => $booking->id,
        'token_hash' => GuestAccessToken::hashToken($token),
        'purpose' => 'guest_access',
        'expires_at' => Carbon::now('Asia/Colombo')->addDays(30),
    ]);

    $this->get(route('booking.status', [
        'reference' => $booking->reference,
        'token' => $token,
    ]))->assertStatus(200);

    $this->tokenService->revokeTokens($booking, $this->staff);

    $this->get(route('booking.status', [
        'reference' => $booking->reference,
        'token' => $token,
    ]))->assertStatus(404);
});
