<?php

declare(strict_types=1);

namespace Tests\Feature\Operations;

use App\Domain\Booking\Enums\BookingStatus;
use App\Domain\Booking\Enums\ContactType;
use App\Domain\Booking\ServiceType;
use App\Domain\Catalog\Enums\DriverStatus;
use App\Domain\Identity\Permission;
use App\Domain\Operations\Enums\DriverTripStatus;
use App\Domain\Operations\Services\DriverAssignmentService;
use App\Models\AirportPickupDetail;
use App\Models\Booking;
use App\Models\BookingContact;
use App\Models\Driver;
use App\Models\DriverAccessToken;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed();
    $this->assignmentService = app(DriverAssignmentService::class);

    $this->staff = User::create([
        'name' => 'Dispatch Staff',
        'email' => 'dispatch2@spinellanka.test',
        'password' => Hash::make('Password123456!'),
        'is_active' => true,
        'token_version' => 1,
    ]);
    $this->staff->givePermissionTo(Permission::BookingReview->value);
    $this->staff->givePermissionTo(Permission::BookingConfirm->value);
});

test('driver trip show endpoint returns 200 with security headers and neutral privacy shell on invalid token', function (): void {
    $vehicle = Vehicle::factory()->create();
    $driver = Driver::factory()->create(['status' => DriverStatus::Active]);

    $pickupTime = Carbon::now('Asia/Colombo')->addHours(4);

    $booking = Booking::factory()->create([
        'reference' => 'BK-TRIP-001',
        'service_type' => ServiceType::AirportPickup,
        'vehicle_id' => $vehicle->id,
        'status' => BookingStatus::Confirmed,
    ]);

    AirportPickupDetail::create([
        'booking_id' => $booking->id,
        'airport' => 'CMB',
        'flight_number' => 'UL-504',
        'arrival_time' => $pickupTime,
        'passenger_count' => 2,
        'luggage_count' => 2,
        'destination_text' => 'Heritance Tea Factory, Nuwara Eliya',
        'name_board_text' => 'Dr. Aris Thorne',
    ]);

    BookingContact::create([
        'booking_id' => $booking->id,
        'type' => ContactType::LeadTraveler,
        'first_name' => 'Aris',
        'last_name' => 'Thorne',
        'email' => 'aris@example.test',
        'phone' => '+447700900999',
    ]);

    $result = $this->assignmentService->assignDriver(
        booking: $booking,
        driver: $driver,
        staff: $this->staff
    );

    $plainToken = $result['plainToken'];

    // 1. Valid token returns 200 with security headers
    $response = $this->get(route('driver.trip.show', ['token' => $plainToken]));
    $response->assertStatus(200)
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertHeader('Referrer-Policy', 'no-referrer');

    expect($response->headers->get('Cache-Control'))->toContain('no-store')
        ->and($response->headers->get('Cache-Control'))->toContain('private');

    $content = $response->getContent();
    expect($content)->toContain('BK-TRIP-001')
        ->and($content)->toContain('Dr. Aris Thorne')
        ->and($content)->toContain('UL-504')
        ->and($content)->toContain('Heritance Tea Factory, Nuwara Eliya');

    // STRICT DATA LEAK CHECK: zero prices, zero currency symbols, zero internal notes
    expect($content)->not->toContain('LKR')
        ->and($content)->not->toContain('USD')
        ->and($content)->not->toContain('profit_margin')
        ->and($content)->not->toContain('internal_notes');

    // 2. Invalid token returns 404 with neutral privacy shell
    $invalidResponse = $this->get(route('driver.trip.show', ['token' => 'invalid-token-sequence-xyz']));
    $invalidResponse->assertStatus(404)
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow');

    $invalidContent = $invalidResponse->getContent();
    expect($invalidContent)->toContain('Link Inactive or Expired')
        ->and($invalidContent)->not->toContain('BK-TRIP-001');

    // 3. Revoked token returns 404 with neutral privacy shell
    $tokenRecord = DriverAccessToken::where('token_hash', hash('sha256', $plainToken))->first();
    $tokenRecord->update(['revoked_at' => Carbon::now('Asia/Colombo')]);

    $revokedResponse = $this->get(route('driver.trip.show', ['token' => $plainToken]));
    $revokedResponse->assertStatus(404);
});

test('driver trip show strictly time-gates passenger phone and WhatsApp until 24h prior to pickup', function (): void {
    $vehicle = Vehicle::factory()->create();
    $driver = Driver::factory()->create(['status' => DriverStatus::Active]);

    // Trip scheduled 72 hours in the future
    $futurePickup = Carbon::now('Asia/Colombo')->addHours(72);

    $booking = Booking::factory()->create([
        'reference' => 'BK-TIME-GATE',
        'service_type' => ServiceType::AirportPickup,
        'vehicle_id' => $vehicle->id,
        'status' => BookingStatus::Confirmed,
    ]);

    AirportPickupDetail::create([
        'booking_id' => $booking->id,
        'airport' => 'CMB',
        'flight_number' => 'UL-303',
        'arrival_time' => $futurePickup,
        'passenger_count' => 1,
        'luggage_count' => 1,
        'destination_text' => 'Galle Face Hotel, Colombo',
    ]);

    BookingContact::create([
        'booking_id' => $booking->id,
        'type' => ContactType::LeadTraveler,
        'first_name' => 'Elena',
        'last_name' => 'Rostova',
        'email' => 'elena@example.test',
        'phone' => '+447911123456',
    ]);

    $result = $this->assignmentService->assignDriver(
        booking: $booking,
        driver: $driver,
        staff: $this->staff
    );

    // Access page > 24 hours prior: phone and WhatsApp should be masked/gated
    $response = $this->get(route('driver.trip.show', ['token' => $result['plainToken']]));
    $response->assertStatus(200);

    $content = $response->getContent();
    expect($content)->not->toContain('+447911123456')
        ->and($content)->toContain('Traveler contact channels unlock 24 hours prior');
});

test('driver trip milestone status update is monotonic, idempotent, and completes booking', function (): void {
    $vehicle = Vehicle::factory()->create();
    $driver = Driver::factory()->create(['status' => DriverStatus::Active]);

    $pickup = Carbon::now('Asia/Colombo')->addHours(2);

    $booking = Booking::factory()->create([
        'reference' => 'BK-MONOTONIC',
        'service_type' => ServiceType::AirportPickup,
        'vehicle_id' => $vehicle->id,
        'status' => BookingStatus::Confirmed,
    ]);

    AirportPickupDetail::create([
        'booking_id' => $booking->id,
        'airport' => 'CMB',
        'flight_number' => 'UL-108',
        'arrival_time' => $pickup,
        'passenger_count' => 1,
        'luggage_count' => 1,
        'destination_text' => 'Cinnamon Grand, Colombo',
    ]);

    $result = $this->assignmentService->assignDriver(
        booking: $booking,
        driver: $driver,
        staff: $this->staff
    );

    $token = $result['plainToken'];

    // 1. Advance to Acknowledged
    $this->post(route('driver.trip.status', ['token' => $token]), [
        'status' => DriverTripStatus::Acknowledged->value,
    ])->assertRedirect(route('driver.trip.show', ['token' => $token]));

    expect($result['assignment']->fresh()->status)->toBe(DriverTripStatus::Acknowledged);

    // 2. Idempotency: repeating Acknowledged succeeds without error
    $this->post(route('driver.trip.status', ['token' => $token]), [
        'status' => DriverTripStatus::Acknowledged->value,
    ])->assertRedirect();

    expect($result['assignment']->fresh()->status)->toBe(DriverTripStatus::Acknowledged);

    // 3. Advance sequentially: EnRoute -> Arrived -> Started
    $this->post(route('driver.trip.status', ['token' => $token]), ['status' => DriverTripStatus::EnRoute->value]);
    $this->post(route('driver.trip.status', ['token' => $token]), ['status' => DriverTripStatus::Arrived->value]);
    $this->post(route('driver.trip.status', ['token' => $token]), ['status' => DriverTripStatus::Started->value]);

    expect($result['assignment']->fresh()->status)->toBe(DriverTripStatus::Started);

    // 4. Backward transition (regression) is rejected
    $this->post(route('driver.trip.status', ['token' => $token]), [
        'status' => DriverTripStatus::Acknowledged->value,
    ])->assertSessionHas('status_error');

    // 5. Completed milestone completes booking
    $this->post(route('driver.trip.status', ['token' => $token]), [
        'status' => DriverTripStatus::Completed->value,
    ])->assertRedirect();

    expect($result['assignment']->fresh()->status)->toBe(DriverTripStatus::Completed);
    expect($booking->fresh()->status)->toBe(BookingStatus::Completed)
        ->and($booking->fresh()->completed_at)->not->toBeNull();
});

test('driver can report operational issue without disrupting milestone flow', function (): void {
    $vehicle = Vehicle::factory()->create();
    $driver = Driver::factory()->create(['status' => DriverStatus::Active]);

    $booking = Booking::factory()->create([
        'reference' => 'BK-ISSUE-01',
        'vehicle_id' => $vehicle->id,
        'status' => BookingStatus::Confirmed,
    ]);

    $result = $this->assignmentService->assignDriver(
        booking: $booking,
        driver: $driver,
        staff: $this->staff
    );

    $token = $result['plainToken'];

    $this->post(route('driver.trip.issue', ['token' => $token]), [
        'notes' => 'Flight delayed by 45 minutes as per CMB arrivals board.',
    ])->assertRedirect(route('driver.trip.show', ['token' => $token]))
        ->assertSessionHas('status_success', 'Operational note dispatched to fleet desk.');

    $assignment = $result['assignment']->fresh();
    expect($assignment->issue_reported_notes)->toBe('Flight delayed by 45 minutes as per CMB arrivals board.')
        ->and($assignment->issue_reported_at)->not->toBeNull();
});
