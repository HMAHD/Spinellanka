<?php

declare(strict_types=1);

use App\Domain\Availability\Enums\HoldStatus;
use App\Domain\Availability\Enums\ScheduleBlockKind;
use App\Domain\Availability\Enums\ScheduleBlockStatus;
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

    $this->vehicle = Vehicle::factory()->create([
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
        'vehicle_id' => $this->vehicle->id,
        'driver_id' => $driver->id,
        'is_permanent' => true,
        'starts_at' => Carbon::now('Asia/Colombo')->subDays(10),
    ]);

    $rule = PricingRule::factory()->create([
        'service_type' => ServiceType::Ride,
        'vehicle_id' => $this->vehicle->id,
        'effective_from' => Carbon::now('Asia/Colombo')->subDays(5),
    ]);

    PricingRuleComponent::factory()->create([
        'pricing_rule_id' => $rule->id,
        'component_type' => PricingComponentType::Base,
        'unit' => PricingUnit::Flat,
        'amount_minor_units' => 12000,
    ]);

    PricingRuleComponent::factory()->create([
        'pricing_rule_id' => $rule->id,
        'component_type' => PricingComponentType::Waiting,
        'unit' => PricingUnit::PerHour,
        'amount_minor_units' => 1500,
    ]);
});

test('submitting a two-leg ride converts both outbound and return booking holds and prevents stale hold expiration', function (): void {
    $pickupDate = Carbon::now('Asia/Colombo')->addDays(3)->format('Y-m-d');

    // 1. Search criteria for 2-leg ride
    $this->post(route('booking.ride.search'), [
        'origin_text' => 'Colombo Fort',
        'destination_text' => 'Galle Face Hotel',
        'pickup_date' => $pickupDate,
        'pickup_time' => '09:00',
        'passenger_count' => 2,
        'luggage_count' => 2,
        'stops' => [
            ['location_text' => 'Kalutara Temple', 'waiting_minutes' => 30],
        ],
        'has_return' => 1,
        'return_date' => $pickupDate,
        'return_time' => '17:00',
    ])->assertRedirect(route('booking.ride.vehicles'));

    // 2. Select vehicle and acquire hold
    $this->post(route('booking.ride.hold'), ['vehicle_id' => $this->vehicle->id])
        ->assertRedirect(route('booking.ride.contact'));

    $holdSession = session('ride_hold');
    expect($holdSession)->not->toBeNull()
        ->and($holdSession['return_plain_token'])->not->toBeNull();

    // Verify both holds exist and are Active
    $holdsBeforeSubmit = BookingHold::where('vehicle_id', $this->vehicle->id)
        ->where('status', HoldStatus::Active)
        ->get();
    expect($holdsBeforeSubmit)->toHaveCount(2);

    // 3. Submit booking
    $this->post(route('booking.ride.submit'), [
        'booker_first_name' => 'Alice',
        'booker_last_name' => 'Smith',
        'booker_phone' => '+94771234567',
        'booker_is_whatsapp' => 1,
        'booker_email' => 'alice@example.com',
        'lead_traveler_same' => 1,
        'terms_accepted' => 1,
        'privacy_accepted' => 1,
    ])->assertRedirect();

    $booking = Booking::where('service_type', ServiceType::Ride)->latest()->first();
    expect($booking)->not->toBeNull()
        ->and($booking->status)->toBe(BookingStatus::Submitted);

    // 4. Critical verification: BOTH booking holds must be Converted!
    $convertedHolds = BookingHold::where('vehicle_id', $this->vehicle->id)
        ->where('status', HoldStatus::Converted)
        ->get();
    expect($convertedHolds)->toHaveCount(2);

    $activeHolds = BookingHold::where('vehicle_id', $this->vehicle->id)
        ->where('status', HoldStatus::Active)
        ->get();
    expect($activeHolds)->toHaveCount(0);

    // 5. Fast-forward 16 minutes (past initial 15-minute hold TTL) and run expireStaleHoldsForVehicle
    Carbon::setTestNow(Carbon::now('Asia/Colombo')->addMinutes(16));
    try {
        $expiredCount = $this->availabilityService->expireStaleHoldsForVehicle($this->vehicle->id);
        expect($expiredCount)->toBe(0);

        // Verify both schedule blocks remain Active and SubmittedRequest
        $blocks = VehicleScheduleBlock::where('source_type', Booking::class)
            ->where('source_id', $booking->id)
            ->get();
        expect($blocks)->toHaveCount(2);
        foreach ($blocks as $blk) {
            expect($blk->status)->toBe(ScheduleBlockStatus::Active)
                ->and($blk->kind)->toBe(ScheduleBlockKind::SubmittedRequest)
                ->and($blk->blocks_availability)->toBeTrue();
        }
    } finally {
        Carbon::setTestNow();
    }
});

test('if return leg hold conflicts, outbound hold is atomically rolled back leaving zero active blocks', function (): void {
    $pickupDate = Carbon::now('Asia/Colombo')->addDays(3)->format('Y-m-d');

    // Create an existing blocking block covering the return window (17:00 to 20:00)
    $returnStart = Carbon::parse("{$pickupDate} 17:00", 'Asia/Colombo');
    $returnEnd = (clone $returnStart)->addHours(3);

    VehicleScheduleBlock::create([
        'vehicle_id' => $this->vehicle->id,
        'kind' => ScheduleBlockKind::ConfirmedBooking,
        'status' => ScheduleBlockStatus::Active,
        'starts_at' => $returnStart,
        'ends_at' => $returnEnd,
        'effective_start' => $returnStart,
        'effective_end' => (clone $returnEnd)->addMinutes(60),
        'blocks_availability' => true,
    ]);

    // Setup search criteria for outbound 09:00 and return 17:00
    $this->post(route('booking.ride.search'), [
        'origin_text' => 'Colombo Fort',
        'destination_text' => 'Galle Face Hotel',
        'pickup_date' => $pickupDate,
        'pickup_time' => '09:00',
        'passenger_count' => 2,
        'luggage_count' => 2,
        'has_return' => 1,
        'return_date' => $pickupDate,
        'return_time' => '17:00',
    ])->assertRedirect(route('booking.ride.vehicles'));

    // Attempting to hold the vehicle must fail because return leg conflicts
    $response = $this->post(route('booking.ride.hold'), ['vehicle_id' => $this->vehicle->id]);
    $response->assertRedirect(route('booking.ride.vehicles'));
    $response->assertSessionHas('error');

    // Assert that outbound hold was released and no active hold blocks remain for this request
    $activeHolds = BookingHold::where('vehicle_id', $this->vehicle->id)
        ->where('status', HoldStatus::Active)
        ->get();
    expect($activeHolds)->toBeEmpty();

    $activeHoldBlocks = VehicleScheduleBlock::where('vehicle_id', $this->vehicle->id)
        ->where('kind', ScheduleBlockKind::CheckoutHold)
        ->where('status', ScheduleBlockStatus::Active)
        ->get();
    expect($activeHoldBlocks)->toBeEmpty();
});

test('submitting with an expired hold session redirects to vehicle selection with error', function (): void {
    $pickupDate = Carbon::now('Asia/Colombo')->addDays(3)->format('Y-m-d');

    $this->post(route('booking.ride.search'), [
        'origin_text' => 'Colombo Fort',
        'destination_text' => 'Galle Face Hotel',
        'pickup_date' => $pickupDate,
        'pickup_time' => '09:00',
        'passenger_count' => 2,
        'luggage_count' => 2,
    ])->assertRedirect(route('booking.ride.vehicles'));

    $this->post(route('booking.ride.hold'), ['vehicle_id' => $this->vehicle->id])
        ->assertRedirect(route('booking.ride.contact'));

    // Fast forward past the 15-minute checkout hold expiration
    Carbon::setTestNow(Carbon::now('Asia/Colombo')->addMinutes(16));
    try {
        $response = $this->post(route('booking.ride.submit'), [
            'booker_first_name' => 'Bob',
            'booker_last_name' => 'Brown',
            'booker_phone' => '+94771234567',
            'booker_is_whatsapp' => 1,
            'booker_email' => 'bob@example.com',
            'lead_traveler_same' => 1,
            'terms_accepted' => 1,
            'privacy_accepted' => 1,
        ]);

        $response->assertRedirect(route('booking.ride.vehicles'));
        $response->assertSessionHas('error', 'Checkout hold has expired. Please reselect your vehicle.');

        // Verify no booking was created
        expect(Booking::where('service_type', ServiceType::Ride)->count())->toBe(0);
    } finally {
        Carbon::setTestNow();
    }
});
