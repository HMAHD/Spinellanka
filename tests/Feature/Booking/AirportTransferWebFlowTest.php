<?php

declare(strict_types=1);

use App\Domain\Availability\Enums\HoldStatus;
use App\Domain\Availability\Enums\ScheduleBlockKind;
use App\Domain\Availability\Services\AvailabilityService;
use App\Domain\Booking\Enums\BookingStatus;
use App\Domain\Booking\ServiceType;
use App\Domain\Catalog\Enums\DriverStatus;
use App\Domain\Catalog\Enums\VehicleStatus;
use App\Domain\Pricing\Enums\PricingComponentType;
use App\Models\Booking;
use App\Models\BookingHold;
use App\Models\Driver;
use App\Models\GuestAccessToken;
use App\Models\PricingRule;
use App\Models\PricingRuleComponent;
use App\Models\Vehicle;
use App\Models\VehicleDriverAssignment;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed();
    $this->availabilityService = app(AvailabilityService::class);
});

test('guest can navigate the entire 4-step airport transfer flow through web routes', function (): void {
    $vehicle = Vehicle::factory()->create([
        'make' => 'Toyota',
        'model' => 'KDH Luxury Van',
        'year' => 2024,
        'license_plate' => 'WP-CAD-2048',
        'eligible_for_transfer' => true,
        'status' => VehicleStatus::Active,
        'passenger_capacity' => 6,
        'luggage_capacity' => 6,
        'turnaround_buffer_minutes' => 60,
    ]);

    $driver = Driver::factory()->create(['status' => DriverStatus::Active]);
    VehicleDriverAssignment::create([
        'vehicle_id' => $vehicle->id,
        'driver_id' => $driver->id,
        'is_permanent' => true,
        'starts_at' => Carbon::now('Asia/Colombo')->subDays(10),
    ]);

    $rule = PricingRule::factory()->create([
        'service_type' => ServiceType::AirportTransfer,
        'vehicle_id' => $vehicle->id,
        'effective_from' => Carbon::now('Asia/Colombo')->subDays(5),
    ]);

    PricingRuleComponent::factory()->create([
        'pricing_rule_id' => $rule->id,
        'component_type' => PricingComponentType::Base,
        'amount_minor_units' => 9500,
    ]);

    // 1. Visit create page (Step 1)
    $response = $this->get(route('booking.airport-transfer'));
    $response->assertOk()
        ->assertSee('Airport Transfer Reservation')
        ->assertSee('Flight Departure Date');

    // 2. Submit search criteria
    $departureDate = Carbon::now('Asia/Colombo')->addDays(3)->format('Y-m-d');
    $response = $this->post(route('booking.airport-transfer.search'), [
        'airport' => 'CMB',
        'flight_number' => 'EK651',
        'departure_date' => $departureDate,
        'departure_time' => '21:00',
        'origin_text' => 'Heritance Ahungalla, Galle Road',
        'passenger_count' => 3,
        'luggage_count' => 3,
        'pickup_timing_mode' => 'recommended',
    ]);

    $response->assertRedirect(route('booking.airport-transfer.vehicles'));

    // 3. Visit vehicles selection page (Step 2)
    $response = $this->get(route('booking.airport-transfer.vehicles'));
    $response->assertOk()
        ->assertSee('Select Your Transfer Vehicle')
        ->assertSee('WP-CAD-2048')
        ->assertSee('USD')
        ->assertSee('$95.00')
        ->assertSee('Select &amp; Hold Vehicle', false);

    // 4. Select vehicle and establish hold
    $response = $this->post(route('booking.airport-transfer.hold'), [
        'vehicle_id' => $vehicle->id,
    ]);
    $response->assertRedirect(route('booking.airport-transfer.contact'));

    // Verify hold exists in DB
    $this->assertDatabaseHas('booking_holds', [
        'vehicle_id' => $vehicle->id,
        'service_type' => ServiceType::AirportTransfer->value,
    ]);

    // 5. Visit traveler contact form (Step 3)
    $response = $this->get(route('booking.airport-transfer.contact'));
    $response->assertOk()
        ->assertSee('Vehicle Hold Active:')
        ->assertSee('WP-CAD-2048')
        ->assertSee('Airport Departure Liability Notice:');

    // 6. Submit reservation
    $response = $this->post(route('booking.airport-transfer.submit'), [
        'booker_first_name' => 'Alexander',
        'booker_last_name' => 'Wright',
        'booker_phone' => '+44 7911 123456',
        'booker_is_whatsapp' => 1,
        'booker_email' => 'alex@example.com',
        'lead_traveler_same' => 1,
        'terms_accepted' => 1,
        'privacy_accepted' => 1,
    ]);

    // Assert booking created and redirected to private token status page
    $booking = Booking::where('service_type', ServiceType::AirportTransfer)->first();
    expect($booking)->not->toBeNull()
        ->and($booking->status)->toBe(BookingStatus::Submitted)
        ->and($booking->vehicle_id)->toBe($vehicle->id);

    $this->assertDatabaseHas('airport_transfer_details', [
        'booking_id' => $booking->id,
        'flight_number' => 'EK651',
        'origin_text' => 'Heritance Ahungalla, Galle Road',
        'is_unsafe_time' => 0,
    ]);

    // Hold should be marked converted
    $hold = BookingHold::where('vehicle_id', $vehicle->id)->latest()->first();
    expect($hold->status)->toBe(HoldStatus::Converted)
        ->and($hold->released_at)->not->toBeNull();

    // Verify Schedule Block exists as SubmittedRequest
    $this->assertDatabaseHas('vehicle_schedule_blocks', [
        'id' => $booking->schedule_block_id,
        'vehicle_id' => $vehicle->id,
        'kind' => ScheduleBlockKind::SubmittedRequest->value,
    ]);

    // 7. Verify status page loads cleanly using guest token
    $tokenRecord = GuestAccessToken::where('booking_id', $booking->id)->first();
    expect($tokenRecord)->not->toBeNull();

    $response->assertRedirect();
    $redirectUrl = $response->headers->get('Location');
    expect($redirectUrl)->toContain("/booking/{$booking->reference}/");

    $statusResponse = $this->get($redirectUrl);
    $statusResponse->assertOk()
        ->assertSee($booking->reference)
        ->assertSee('Departure Flight &amp; Transfer Details', false)
        ->assertSee('EK651')
        ->assertSee('Heritance Ahungalla, Galle Road')
        ->assertSee('WP-CAD-2048');
});

test('handles permitted custom later pickup time with required warning acknowledgment', function (): void {
    $vehicle = Vehicle::factory()->create([
        'eligible_for_transfer' => true,
        'status' => VehicleStatus::Active,
        'passenger_capacity' => 4,
        'luggage_capacity' => 4,
    ]);

    $driver = Driver::factory()->create(['status' => DriverStatus::Active]);
    VehicleDriverAssignment::create([
        'vehicle_id' => $vehicle->id,
        'driver_id' => $driver->id,
        'is_permanent' => true,
        'starts_at' => Carbon::now('Asia/Colombo')->subDays(10),
    ]);

    $rule = PricingRule::factory()->create([
        'service_type' => ServiceType::AirportTransfer,
        'vehicle_id' => $vehicle->id,
    ]);
    PricingRuleComponent::factory()->create([
        'pricing_rule_id' => $rule->id,
        'component_type' => PricingComponentType::Base,
        'amount_minor_units' => 7000,
    ]);

    $departureDate = Carbon::now('Asia/Colombo')->addDays(3)->format('Y-m-d');

    // Attempt custom later pickup time WITHOUT warning acceptance -> Fails validation
    $response = $this->from(route('booking.airport-transfer'))->post(route('booking.airport-transfer.search'), [
        'airport' => 'CMB',
        'flight_number' => 'UL504',
        'departure_date' => $departureDate,
        'departure_time' => '20:00',
        'origin_text' => 'Galle Fort Heritage Villa',
        'passenger_count' => 2,
        'luggage_count' => 2,
        'pickup_timing_mode' => 'custom',
        'custom_pickup_date' => $departureDate,
        'custom_pickup_time' => '15:00', // Later than recommended (13:15) but before cutoff (15:30)
        'warning_accepted' => 0,
    ]);

    $response->assertRedirect(route('booking.airport-transfer'));
    $response->assertSessionHasErrors('warning_accepted');

    // Now submit WITH warning acceptance checkbox -> Succeeds and flags is_unsafe_time = true
    $responseSuccess = $this->post(route('booking.airport-transfer.search'), [
        'airport' => 'CMB',
        'flight_number' => 'UL504',
        'departure_date' => $departureDate,
        'departure_time' => '20:00',
        'origin_text' => 'Galle Fort Heritage Villa',
        'passenger_count' => 2,
        'luggage_count' => 2,
        'pickup_timing_mode' => 'custom',
        'custom_pickup_date' => $departureDate,
        'custom_pickup_time' => '15:00',
        'warning_accepted' => 1,
    ]);

    $responseSuccess->assertRedirect(route('booking.airport-transfer.vehicles'));

    $criteria = session('airport_transfer_criteria');
    expect($criteria)->not->toBeNull()
        ->and($criteria->isUnsafeTime)->toBeTrue()
        ->and($criteria->warningAccepted)->toBeTrue();

    // Vehicles view shows warning badge
    $vehiclesView = $this->get(route('booking.airport-transfer.vehicles'));
    $vehiclesView->assertOk()
        ->assertSee('Later than Recommended');

    // Hold and submit
    $this->post(route('booking.airport-transfer.hold'), ['vehicle_id' => $vehicle->id]);

    $submitResponse = $this->post(route('booking.airport-transfer.submit'), [
        'booker_first_name' => 'Elena',
        'booker_last_name' => 'Rostova',
        'booker_phone' => '+49 151 12345678',
        'terms_accepted' => 1,
        'privacy_accepted' => 1,
    ]);

    $submitResponse->assertRedirect();

    $booking = Booking::where('service_type', ServiceType::AirportTransfer)->first();
    expect($booking)->not->toBeNull();

    $this->assertDatabaseHas('airport_transfer_details', [
        'booking_id' => $booking->id,
        'is_unsafe_time' => 1,
        'warning_accepted' => 1,
    ]);
});

test('rejects impermissible custom pickup time later than cutoff buffer', function (): void {
    $departureDate = Carbon::now('Asia/Colombo')->addDays(3)->format('Y-m-d');

    // Departure at 20:00, drive is default 180m, cutoff buffer 90m -> cutoff is 20:00 - 4h 30m = 15:30
    // Custom time 18:00 is dangerously close (only 2h before flight) -> Impermissible
    $response = $this->from(route('booking.airport-transfer'))->post(route('booking.airport-transfer.search'), [
        'airport' => 'CMB',
        'flight_number' => 'QR665',
        'departure_date' => $departureDate,
        'departure_time' => '20:00',
        'origin_text' => 'Yala National Park Hotel',
        'passenger_count' => 2,
        'luggage_count' => 2,
        'pickup_timing_mode' => 'custom',
        'custom_pickup_date' => $departureDate,
        'custom_pickup_time' => '18:00',
        'warning_accepted' => 1,
    ]);

    $response->assertRedirect(route('booking.airport-transfer'));
    $response->assertSessionHasErrors('custom_pickup_time');
});

test('handles expired hold gracefully by blocking submission', function (): void {
    $vehicle = Vehicle::factory()->create([
        'eligible_for_transfer' => true,
        'status' => VehicleStatus::Active,
        'passenger_capacity' => 4,
        'luggage_capacity' => 4,
    ]);

    $driver = Driver::factory()->create(['status' => DriverStatus::Active]);
    VehicleDriverAssignment::create([
        'vehicle_id' => $vehicle->id,
        'driver_id' => $driver->id,
        'is_permanent' => true,
        'starts_at' => Carbon::now('Asia/Colombo')->subDays(10),
    ]);

    $departureDate = Carbon::now('Asia/Colombo')->addDays(3)->format('Y-m-d');
    $this->post(route('booking.airport-transfer.search'), [
        'airport' => 'CMB',
        'flight_number' => 'UL504',
        'departure_date' => $departureDate,
        'departure_time' => '21:00',
        'origin_text' => 'Colombo 03',
        'passenger_count' => 2,
        'luggage_count' => 2,
    ]);

    $rule = PricingRule::factory()->create([
        'service_type' => ServiceType::AirportTransfer,
        'vehicle_id' => $vehicle->id,
    ]);
    PricingRuleComponent::factory()->create([
        'pricing_rule_id' => $rule->id,
        'component_type' => PricingComponentType::Base,
        'amount_minor_units' => 5000,
    ]);

    $this->post(route('booking.airport-transfer.hold'), ['vehicle_id' => $vehicle->id]);

    // Simulate hold expiration in DB and session
    $hold = BookingHold::first();
    $hold->update(['expires_at' => Carbon::now('Asia/Colombo')->subMinute()]);

    $response = $this->post(route('booking.airport-transfer.submit'), [
        'booker_first_name' => 'Expired',
        'booker_last_name' => 'Hold',
        'booker_phone' => '+44 7700 900123',
        'terms_accepted' => 1,
        'privacy_accepted' => 1,
    ]);

    // Redirects to vehicle selection with error message
    $response->assertRedirect(route('booking.airport-transfer.vehicles'));
    $response->assertSessionHas('error');
});
