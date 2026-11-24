<?php

declare(strict_types=1);

use App\Domain\Availability\Enums\ScheduleBlockKind;
use App\Domain\Availability\Services\AvailabilityService;
use App\Domain\Booking\Enums\BookingStatus;
use App\Domain\Booking\ServiceType;
use App\Domain\Catalog\Enums\DriverStatus;
use App\Domain\Catalog\Enums\VehicleStatus;
use App\Domain\Pricing\Enums\PricingComponentType;
use App\Models\Booking;
use App\Models\Driver;
use App\Models\GuestAccessToken;
use App\Models\PricingRule;
use App\Models\PricingRuleComponent;
use App\Models\Vehicle;
use App\Models\VehicleDriverAssignment;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed();
    $this->availabilityService = app(AvailabilityService::class);
});

test('guest can navigate the entire 4-step airport pickup flow through web routes', function (): void {
    $vehicle = Vehicle::factory()->create([
        'make' => 'Toyota',
        'model' => 'KDH Luxury Van',
        'year' => 2024,
        'license_plate' => 'WP-CAD-1024',
        'eligible_for_pickup' => true,
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
        'service_type' => ServiceType::AirportPickup,
        'vehicle_id' => $vehicle->id,
        'effective_from' => Carbon::now('Asia/Colombo')->subDays(5),
    ]);

    PricingRuleComponent::factory()->create([
        'pricing_rule_id' => $rule->id,
        'component_type' => PricingComponentType::Base,
        'amount_minor_units' => 7500,
    ]);

    // Step 1: Render criteria form
    $step1 = $this->get(route('booking.airport-pickup'));
    $step1->assertOk()
        ->assertSee('Airport Pickup Reservation')
        ->assertSee('CMB')
        ->assertSee('Scheduled Arrival Date');

    // Step 2: Submit search criteria
    $arrivalDate = Carbon::now('Asia/Colombo')->addDays(3)->format('Y-m-d');
    $step2 = $this->post(route('booking.airport-pickup.search'), [
        'airport' => 'CMB',
        'flight_number' => 'UL504',
        'arrival_date' => $arrivalDate,
        'arrival_time' => '15:45',
        'destination_text' => 'Heritance Ahungalla',
        'passenger_count' => 3,
        'luggage_count' => 3,
        'place_id' => 'galle_fort',
        'meet_and_greet' => '1',
        'name_board_text' => 'Dr. Livingston',
    ]);

    $step2->assertOk()
        ->assertSee('Select Your Vehicle')
        ->assertSee('KDH Luxury Van')
        ->assertSee('WP-CAD-1024')
        ->assertSee('$75.00')
        ->assertSee('Select & Hold Vehicle');

    // Step 3: Hold the vehicle for 15 minutes
    $step3 = $this->post(route('booking.airport-pickup.hold'), [
        'vehicle_id' => $vehicle->id,
    ]);

    $step3->assertOk()
        ->assertSee('Vehicle Hold Active')
        ->assertSee('15 minutes')
        ->assertSee('Booker Contact Information')
        ->assertSee('Lead traveler is the same person as the booker')
        ->assertSee('Terms of Service');

    // Step 4: Submit the advance reservation
    $step4 = $this->post(route('booking.airport-pickup.submit'), [
        'booker_first_name' => 'Amelia',
        'booker_last_name' => 'Earhart',
        'booker_phone' => '+447700900555',
        'booker_is_whatsapp' => '1',
        'booker_email' => 'amelia@example.co.uk',
        'booker_marketing_consent' => '0',
        'lead_traveler_same' => '1',
        'terms_accepted' => '1',
        'privacy_accepted' => '1',
    ]);

    $step4->assertRedirect();
    $targetUrl = $step4->headers->get('Location');
    expect($targetUrl)->toContain('/booking/BKG-');

    // Follow redirect to private under-review page
    $statusPage = $this->get($targetUrl);
    $statusPage->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertHeader('Referrer-Policy', 'no-referrer')
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertSee('Request Received')
        ->assertSee('Under Review')
        ->assertDontSee('Your Airport Pickup is Confirmed')
        ->assertSee('Amelia Earhart')
        ->assertSee('KDH Luxury Van')
        ->assertSee('UL504')
        ->assertSee('Heritance Ahungalla')
        ->assertSee('$75.00');

    // Assert booking in database
    $booking = Booking::latest()->first();
    expect($booking->status)->toBe(BookingStatus::Submitted)
        ->and($booking->scheduleBlock->kind)->toBe(ScheduleBlockKind::SubmittedRequest);
});

test('status page returns identical 404 on invalid, expired, or revoked token without leaking existence', function (): void {
    $booking = Booking::factory()->create([
        'reference' => 'BKG-SECRET01',
    ]);

    $plainToken = Str::random(64);
    $token = GuestAccessToken::create([
        'booking_id' => $booking->id,
        'token_hash' => GuestAccessToken::hashToken($plainToken),
        'expires_at' => Carbon::now('Asia/Colombo')->addDays(30),
    ]);

    // Valid token works
    $this->get(route('booking.status', ['reference' => $booking->reference, 'token' => $plainToken]))
        ->assertOk();

    // Wrong reference with valid token returns 404
    $this->get(route('booking.status', ['reference' => 'BKG-WRONG999', 'token' => $plainToken]))
        ->assertNotFound();

    // Invalid token returns 404
    $this->get(route('booking.status', ['reference' => $booking->reference, 'token' => 'invalid-token-12345']))
        ->assertNotFound();

    // Revoked token returns 404
    $token->update(['revoked_at' => Carbon::now('Asia/Colombo')]);
    $this->get(route('booking.status', ['reference' => $booking->reference, 'token' => $plainToken]))
        ->assertNotFound();

    // Expired token returns 404
    $token->update(['revoked_at' => null, 'expires_at' => Carbon::now('Asia/Colombo')->subDay()]);
    $this->get(route('booking.status', ['reference' => $booking->reference, 'token' => $plainToken]))
        ->assertNotFound();
});
