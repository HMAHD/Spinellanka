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
use App\Domain\Pricing\Enums\PricingUnit;
use App\Models\Booking;
use App\Models\BookingHold;
use App\Models\Driver;
use App\Models\GuestAccessToken;
use App\Models\PricingRule;
use App\Models\PricingRuleComponent;
use App\Models\Vehicle;
use App\Models\VehicleDriverAssignment;
use App\Models\VehicleScheduleBlock;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed();
    $this->availabilityService = app(AvailabilityService::class);
});

test('guest can navigate the entire chauffeured ride booking flow with intermediate stops', function (): void {
    $vehicle = Vehicle::factory()->create([
        'make' => 'Toyota',
        'model' => 'Land Cruiser Prado',
        'year' => 2024,
        'license_plate' => 'WP-CBD-4500',
        'eligible_for_ride' => true,
        'status' => VehicleStatus::Active,
        'passenger_capacity' => 6,
        'luggage_capacity' => 5,
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
        'service_type' => ServiceType::Ride,
        'vehicle_id' => $vehicle->id,
        'effective_from' => Carbon::now('Asia/Colombo')->subDays(5),
    ]);

    PricingRuleComponent::factory()->create([
        'pricing_rule_id' => $rule->id,
        'component_type' => PricingComponentType::Base,
        'unit' => PricingUnit::Flat,
        'amount_minor_units' => 12000,
    ]);

    // 1. Visit create page (Step 1)
    $response = $this->get(route('booking.ride'));
    $response->assertOk()
        ->assertSee('Chauffeured Ride Reservation')
        ->assertSee('En-Route Stops (Optional, Max 10)');

    // 2. Test No-JS Stop additions and reordering
    $pickupDate = Carbon::now('Asia/Colombo')->addDays(2)->format('Y-m-d');

    // Add Stop action
    $addResponse = $this->post(route('booking.ride.search'), [
        'action' => 'add_stop',
        'origin_text' => 'Cinnamon Grand, Colombo 03',
        'destination_text' => 'Amangalla, Galle Fort',
        'pickup_date' => $pickupDate,
        'pickup_time' => '09:00',
        'passenger_count' => 2,
        'luggage_count' => 2,
        'stops' => [
            ['location_text' => 'Kosgoda Turtle Hatchery', 'waiting_minutes' => 45],
        ],
    ]);
    $addResponse->assertRedirect(route('booking.ride'));

    // Check draft in session
    $draft = session('ride_draft');
    expect($draft['stops'])->toHaveCount(2);

    // 3. Submit search criteria with stops
    $searchResponse = $this->post(route('booking.ride.search'), [
        'origin_text' => 'Cinnamon Grand, Colombo 03',
        'destination_text' => 'Amangalla, Galle Fort',
        'pickup_date' => $pickupDate,
        'pickup_time' => '09:00',
        'passenger_count' => 3,
        'luggage_count' => 3,
        'stops' => [
            ['location_text' => 'Kosgoda Turtle Hatchery', 'waiting_minutes' => 45],
            ['location_text' => 'Bentota Beach', 'waiting_minutes' => 60],
        ],
        'has_return' => 0,
    ]);
    $searchResponse->assertRedirect(route('booking.ride.vehicles'));

    // 4. Visit vehicles selection page (Step 2)
    $vehiclesResponse = $this->get(route('booking.ride.vehicles'));
    $vehiclesResponse->assertOk()
        ->assertSee('Select Your Chauffeured Vehicle')
        ->assertSee('WP-CBD-4500')
        ->assertSee('USD 120.00');

    // 5. Select vehicle and establish hold
    $holdResponse = $this->post(route('booking.ride.hold'), [
        'vehicle_id' => $vehicle->id,
    ]);
    $holdResponse->assertRedirect(route('booking.ride.contact'));

    // Verify hold exists in database
    $hold = BookingHold::where('vehicle_id', $vehicle->id)->where('status', HoldStatus::Active)->first();
    expect($hold)->not->toBeNull()
        ->and($hold->service_type)->toBe(ServiceType::Ride);

    // 6. Visit contact page (Step 3)
    $contactResponse = $this->get(route('booking.ride.contact'));
    $contactResponse->assertOk()
        ->assertSee('Traveler &amp; Contact Details', false)
        ->assertSee('Vehicle Hold Active:')
        ->assertSee('2 stops (105 min total wait)');

    // 7. Submit booking request
    $submitResponse = $this->post(route('booking.ride.submit'), [
        'booker_first_name' => 'Sophia',
        'booker_last_name' => 'Laurent',
        'booker_phone' => '+33 6 12 34 56 78',
        'booker_is_whatsapp' => 1,
        'booker_email' => 'sophia.laurent@example.fr',
        'lead_traveler_same' => 1,
        'terms_accepted' => 1,
        'privacy_accepted' => 1,
        'booker_marketing_consent' => 0,
    ]);

    $booking = Booking::where('service_type', ServiceType::Ride)->latest()->first();
    expect($booking)->not->toBeNull()
        ->and($booking->status)->toBe(BookingStatus::Submitted)
        ->and($booking->leadTraveler->first_name)->toBe('Sophia');

    // Verify RideDetail and RideStops created
    $rideDetail = $booking->rideDetail;
    expect($rideDetail)->not->toBeNull()
        ->and($rideDetail->origin_text)->toBe('Cinnamon Grand, Colombo 03')
        ->and($rideDetail->destination_text)->toBe('Amangalla, Galle Fort')
        ->and($rideDetail->stops)->toHaveCount(2)
        ->and($rideDetail->stops[0]->location_text)->toBe('Kosgoda Turtle Hatchery')
        ->and($rideDetail->stops[0]->planned_waiting_minutes)->toBe(45);

    // Schedule block converted to SubmittedRequest
    $block = VehicleScheduleBlock::where('id', $hold->schedule_block_id)->first();
    expect($block->kind)->toBe(ScheduleBlockKind::SubmittedRequest)
        ->and($block->source_id)->toBe($booking->id);

    // 8. Follow redirect to status URL
    $token = GuestAccessToken::where('booking_id', $booking->id)->first();
    $statusUrl = route('booking.status', ['reference' => $booking->reference, 'token' => 'mock_token']);

    // Direct visit with correct hashed token check
    $realToken = 'valid_test_token_'.Str::random(30);
    $token->update(['token_hash' => GuestAccessToken::hashToken($realToken)]);

    $statusResponse = $this->get(route('booking.status', ['reference' => $booking->reference, 'token' => $realToken]));
    $statusResponse->assertOk()
        ->assertSee('Request Received &bull; Under Review', false)
        ->assertSee('Ride Itinerary &amp; Journey Details', false)
        ->assertSee('Kosgoda Turtle Hatchery')
        ->assertSee('Journey Route Overview')
        ->assertDontSee('Your Chauffeured Ride is Confirmed'); // Never say confirmed when under review!
});

test('guest can book a multi-leg ride with scheduled return leg atomically', function (): void {
    $vehicle = Vehicle::factory()->create([
        'eligible_for_ride' => true,
        'status' => VehicleStatus::Active,
        'passenger_capacity' => 4,
        'luggage_capacity' => 4,
        'turnaround_buffer_minutes' => 60,
    ]);

    $driver = Driver::factory()->create(['status' => DriverStatus::Active]);
    VehicleDriverAssignment::create([
        'vehicle_id' => $vehicle->id,
        'driver_id' => $driver->id,
        'is_permanent' => true,
        'starts_at' => Carbon::now('Asia/Colombo')->subDays(5),
    ]);

    $rule = PricingRule::factory()->create([
        'service_type' => ServiceType::Ride,
        'vehicle_id' => $vehicle->id,
        'effective_from' => Carbon::now('Asia/Colombo')->subDays(5),
    ]);

    PricingRuleComponent::factory()->create([
        'pricing_rule_id' => $rule->id,
        'component_type' => PricingComponentType::Base,
        'unit' => PricingUnit::Flat,
        'amount_minor_units' => 22000,
    ]);

    $pickupDate = Carbon::now('Asia/Colombo')->addDays(3)->format('Y-m-d');
    $returnDate = Carbon::now('Asia/Colombo')->addDays(3)->format('Y-m-d');

    // 1. Submit search with return leg
    $this->post(route('booking.ride.search'), [
        'origin_text' => 'Colombo Fort',
        'destination_text' => 'Kandy City Centre',
        'pickup_date' => $pickupDate,
        'pickup_time' => '08:00',
        'passenger_count' => 2,
        'luggage_count' => 2,
        'has_return' => 1,
        'return_date' => $returnDate,
        'return_time' => '17:00',
    ])->assertRedirect(route('booking.ride.vehicles'));

    // 2. Select vehicle & hold (should hold both outbound and return under same group_id)
    $this->post(route('booking.ride.hold'), ['vehicle_id' => $vehicle->id])
        ->assertRedirect(route('booking.ride.contact'));

    $holds = BookingHold::where('vehicle_id', $vehicle->id)->where('status', HoldStatus::Active)->get();
    expect($holds)->toHaveCount(2)
        ->and($holds[0]->group_id)->not->toBeNull()
        ->and($holds[0]->group_id)->toBe($holds[1]->group_id);

    // 3. Submit booking
    $this->post(route('booking.ride.submit'), [
        'booker_first_name' => 'Alexander',
        'booker_last_name' => 'Wright',
        'booker_phone' => '+44 7700 900555',
        'booker_is_whatsapp' => 1,
        'booker_email' => 'alex.wright@example.co.uk',
        'lead_traveler_same' => 1,
        'terms_accepted' => 1,
        'privacy_accepted' => 1,
    ])->assertRedirect();

    $booking = Booking::where('service_type', ServiceType::Ride)->latest()->first();
    expect($booking->rideDetail->has_return)->toBeTrue()
        ->and($booking->rideDetail->return_pickup_time)->not->toBeNull();

    // Verify both schedule blocks are converted to SubmittedRequest
    $blocks = VehicleScheduleBlock::where('group_id', $holds[0]->group_id)->get();
    expect($blocks)->toHaveCount(2);
    foreach ($blocks as $blk) {
        expect($blk->kind)->toBe(ScheduleBlockKind::SubmittedRequest)
            ->and($blk->source_id)->toBe($booking->id);
    }
});

test('it rejects search when cumulative planned waiting exceeds 480 minutes', function (): void {
    $pickupDate = Carbon::now('Asia/Colombo')->addDays(2)->format('Y-m-d');

    $response = $this->post(route('booking.ride.search'), [
        'origin_text' => 'Colombo',
        'destination_text' => 'Galle',
        'pickup_date' => $pickupDate,
        'pickup_time' => '09:00',
        'passenger_count' => 2,
        'luggage_count' => 2,
        'stops' => [
            ['location_text' => 'Stop 1', 'waiting_minutes' => 300],
            ['location_text' => 'Stop 2', 'waiting_minutes' => 200], // 300 + 200 = 500 min > 480 min
        ],
    ]);

    $response->assertSessionHasErrors(['stops']);
});

test('it rejects search when return pickup is too close to outbound arrival', function (): void {
    $pickupDate = Carbon::now('Asia/Colombo')->addDays(2)->format('Y-m-d');

    // Default outbound drive is 120 min + 60 min waiting = 180 min (3h)
    // 09:00 + 3h = 12:00 arrival. Minimum return pickup is 13:00 (12:00 + 60 min).
    // Requesting return at 12:30 should fail.
    $response = $this->post(route('booking.ride.search'), [
        'origin_text' => 'Colombo',
        'destination_text' => 'Galle',
        'pickup_date' => $pickupDate,
        'pickup_time' => '09:00',
        'passenger_count' => 2,
        'luggage_count' => 2,
        'stops' => [
            ['location_text' => 'Stop 1', 'waiting_minutes' => 60],
        ],
        'has_return' => 1,
        'return_date' => $pickupDate,
        'return_time' => '12:30', // Less than 60 min after 12:00
    ]);

    $response->assertSessionHasErrors(['return_time']);
});

test('it degrades transparently to Custom Quote when pricing rule has no waiting component', function (): void {
    $vehicle = Vehicle::factory()->create([
        'eligible_for_ride' => true,
        'status' => VehicleStatus::Active,
        'passenger_capacity' => 4,
        'luggage_capacity' => 4,
    ]);

    $driver = Driver::factory()->create(['status' => DriverStatus::Active]);
    VehicleDriverAssignment::create([
        'vehicle_id' => $vehicle->id,
        'driver_id' => $driver->id,
        'is_permanent' => true,
        'starts_at' => Carbon::now('Asia/Colombo')->subDays(5),
    ]);

    // Rule with no components or missing waiting rate
    PricingRule::factory()->create([
        'service_type' => ServiceType::Ride,
        'vehicle_id' => $vehicle->id,
        'effective_from' => Carbon::now('Asia/Colombo')->subDays(5),
    ]);

    $pickupDate = Carbon::now('Asia/Colombo')->addDays(2)->format('Y-m-d');
    $this->post(route('booking.ride.search'), [
        'origin_text' => 'Colombo',
        'destination_text' => 'Jaffna',
        'pickup_date' => $pickupDate,
        'pickup_time' => '09:00',
        'passenger_count' => 2,
        'luggage_count' => 2,
    ]);

    $response = $this->get(route('booking.ride.vehicles'));
    $response->assertOk()
        ->assertSee('Custom Route Quote');
});
