<?php

declare(strict_types=1);

use App\Domain\Availability\Services\AvailabilityService;
use App\Domain\Booking\DTOs\AirportTransferCriteria;
use App\Domain\Booking\Services\AirportTransferRecommendationCalculator;
use App\Domain\Booking\Services\AirportTransferSuitabilityService;
use App\Domain\Booking\ServiceType;
use App\Domain\Catalog\Enums\DriverStatus;
use App\Domain\Catalog\Enums\VehicleStatus;
use App\Domain\Catalog\Services\DriverAssignmentService;
use App\Domain\Pricing\Enums\PricingComponentType;
use App\Models\Driver;
use App\Models\PricingRule;
use App\Models\PricingRuleComponent;
use App\Models\Vehicle;
use App\Models\VehicleDriverAssignment;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed();
    $this->suitabilityService = app(AirportTransferSuitabilityService::class);
    $this->assignmentService = app(DriverAssignmentService::class);
    $this->availabilityService = app(AvailabilityService::class);
    $this->calculator = app(AirportTransferRecommendationCalculator::class);
});

test('evaluates transfer suitability based on eligibility, capacity, driver, notice, and availability', function (): void {
    $vehicle = Vehicle::factory()->create([
        'eligible_for_transfer' => true,
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
        'starts_at' => Carbon::now('Asia/Colombo')->subDays(10),
    ]);

    $pricingRule = PricingRule::factory()->create([
        'service_type' => ServiceType::AirportTransfer,
        'vehicle_id' => $vehicle->id,
        'effective_from' => Carbon::now('Asia/Colombo')->subDays(5),
    ]);

    PricingRuleComponent::factory()->create([
        'pricing_rule_id' => $pricingRule->id,
        'component_type' => PricingComponentType::Base,
        'amount_minor_units' => 6500,
    ]);

    // Scenario 1: Suitable criteria
    $departure = Carbon::now('Asia/Colombo')->addDays(2)->setHour(20)->setMinute(0);
    $recommended = $this->calculator->calculateRecommendedPickupTime($departure, 120);

    $criteria = new AirportTransferCriteria(
        airport: 'CMB',
        flightNumber: 'EK651',
        departureTime: $departure,
        originText: 'Galle Fort Heritage Villa, Galle',
        passengerCount: 3,
        luggageCount: 3,
        recommendedPickupTime: $recommended,
        chosenPickupTime: $recommended,
        distanceKm: 150.0,
        durationHours: 2.0,
    );

    $result = $this->suitabilityService->evaluate($criteria);

    expect($result->suitable)->toHaveCount(1)
        ->and($result->suitable->first()->vehicle->id)->toBe($vehicle->id)
        ->and($result->suitable->first()->isSuitable)->toBeTrue()
        ->and($result->suitable->first()->estimate)->not->toBeNull();

    // Scenario 2: Capacity exceeded (5 passengers > capacity 4)
    $criteriaExcessivePassengers = new AirportTransferCriteria(
        airport: 'CMB',
        flightNumber: 'EK651',
        departureTime: $departure,
        originText: 'Galle Fort Heritage Villa, Galle',
        passengerCount: 5,
        luggageCount: 2,
        recommendedPickupTime: $recommended,
        chosenPickupTime: $recommended,
        distanceKm: 150.0,
        durationHours: 2.0,
    );

    $resultCapacity = $this->suitabilityService->evaluate($criteriaExcessivePassengers);
    expect($resultCapacity->suitable)->toHaveCount(0)
        ->and($resultCapacity->unsuitable)->toHaveCount(1)
        ->and($resultCapacity->unsuitable->first()->reasons[0])->toContain('Passenger capacity');

    // Scenario 3: Minimum advance notice violated (less than 6 hours)
    $tooSoonDeparture = Carbon::now('Asia/Colombo')->addHours(4);
    $tooSoonPickup = (clone $tooSoonDeparture)->subHours(2);
    $criteriaNotice = new AirportTransferCriteria(
        airport: 'CMB',
        flightNumber: 'EK651',
        departureTime: $tooSoonDeparture,
        originText: 'Colombo 03',
        passengerCount: 2,
        luggageCount: 2,
        recommendedPickupTime: $tooSoonPickup,
        chosenPickupTime: $tooSoonPickup,
    );

    $resultNotice = $this->suitabilityService->evaluate($criteriaNotice);
    expect($resultNotice->suitable)->toHaveCount(0)
        ->and($resultNotice->unsuitable)->toHaveCount(1)
        ->and($resultNotice->unsuitable->first()->reasons[0])->toContain('Minimum notice');
});

test('excludes vehicle without active driver for airport transfer', function (): void {
    $vehicle = Vehicle::factory()->create([
        'eligible_for_transfer' => true,
        'status' => VehicleStatus::Active,
        'passenger_capacity' => 4,
        'luggage_capacity' => 4,
    ]);

    // No driver assigned to vehicle
    $departure = Carbon::now('Asia/Colombo')->addDays(2)->setHour(20)->setMinute(0);
    $recommended = $this->calculator->calculateRecommendedPickupTime($departure, 120);

    $criteria = new AirportTransferCriteria(
        airport: 'CMB',
        flightNumber: 'EK651',
        departureTime: $departure,
        originText: 'Bentota Resort',
        passengerCount: 2,
        luggageCount: 2,
        recommendedPickupTime: $recommended,
        chosenPickupTime: $recommended,
    );

    $result = $this->suitabilityService->evaluate($criteria);

    expect($result->suitable)->toBeEmpty()
        ->and($result->unsuitable)->toHaveCount(1)
        ->and($result->unsuitable->first()->reasons)->toContain('No active driver currently assigned to this vehicle.');
});
