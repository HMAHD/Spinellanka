<?php

declare(strict_types=1);

use App\Domain\Availability\Services\AvailabilityService;
use App\Domain\Booking\DTOs\AirportPickupCriteria;
use App\Domain\Booking\Services\AirportPickupSuitabilityService;
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
    $this->suitabilityService = app(AirportPickupSuitabilityService::class);
    $this->assignmentService = app(DriverAssignmentService::class);
    $this->availabilityService = app(AvailabilityService::class);
});

test('evaluates suitability based on capacity, driver, notice, and availability', function (): void {
    $vehicle = Vehicle::factory()->create([
        'eligible_for_pickup' => true,
        'status' => VehicleStatus::Active,
        'passenger_capacity' => 4,
        'luggage_capacity' => 3,
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
        'service_type' => ServiceType::AirportPickup,
        'vehicle_id' => $vehicle->id,
        'effective_from' => Carbon::now('Asia/Colombo')->subDays(5),
    ]);

    PricingRuleComponent::factory()->create([
        'pricing_rule_id' => $pricingRule->id,
        'component_type' => PricingComponentType::Base,
        'amount_minor_units' => 5000,
    ]);

    // Scenario 1: Suitable criteria
    $arrivalTime = Carbon::now('Asia/Colombo')->addDays(2)->setHour(12)->setMinute(0);
    $criteria = new AirportPickupCriteria(
        airport: 'CMB',
        flightNumber: 'UL504',
        arrivalTime: $arrivalTime,
        destinationText: 'Colombo 03',
        passengerCount: 3,
        luggageCount: 2,
        distanceKm: 35.0,
        durationHours: 1.0,
    );

    $result = $this->suitabilityService->evaluate($criteria);
    expect($result->suitable)->toHaveCount(1)
        ->and($result->suitable->first()->vehicle->id)->toBe($vehicle->id)
        ->and($result->suitable->first()->isSuitable)->toBeTrue()
        ->and($result->suitable->first()->estimate)->not->toBeNull();

    // Scenario 2: Insufficient luggage capacity
    $criteriaHeavyLuggage = new AirportPickupCriteria(
        airport: 'CMB',
        flightNumber: 'UL504',
        arrivalTime: $arrivalTime,
        destinationText: 'Colombo 03',
        passengerCount: 3,
        luggageCount: 5, // Exceeds luggage capacity of 3
        distanceKm: 35.0,
        durationHours: 1.0,
    );

    $resultHeavy = $this->suitabilityService->evaluate($criteriaHeavyLuggage);
    expect($resultHeavy->suitable)->toHaveCount(0)
        ->and($resultHeavy->unsuitable)->toHaveCount(1)
        ->and($resultHeavy->unsuitable->first()->reasons[0])->toContain('Luggage capacity');

    // Scenario 3: Violates minimum notice
    $criteriaShortNotice = new AirportPickupCriteria(
        airport: 'CMB',
        flightNumber: 'UL504',
        arrivalTime: Carbon::now('Asia/Colombo')->addHours(2), // Less than 6h notice
        destinationText: 'Colombo 03',
        passengerCount: 2,
        luggageCount: 2,
        distanceKm: 35.0,
        durationHours: 1.0,
    );

    $resultShortNotice = $this->suitabilityService->evaluate($criteriaShortNotice);
    expect($resultShortNotice->suitable)->toHaveCount(0)
        ->and($resultShortNotice->unsuitable->first()->reasons[0])->toContain('Minimum notice');
});

test('vehicle without assigned driver is marked unsuitable for chauffeured airport pickup', function (): void {
    $vehicleWithoutDriver = Vehicle::factory()->create([
        'eligible_for_pickup' => true,
        'status' => VehicleStatus::Active,
        'passenger_capacity' => 4,
        'luggage_capacity' => 4,
    ]);

    $arrivalTime = Carbon::now('Asia/Colombo')->addDays(2)->setHour(12)->setMinute(0);
    $criteria = new AirportPickupCriteria(
        airport: 'CMB',
        flightNumber: 'UL504',
        arrivalTime: $arrivalTime,
        destinationText: 'Colombo 03',
        passengerCount: 2,
        luggageCount: 2,
        distanceKm: 35.0,
        durationHours: 1.0,
    );

    $result = $this->suitabilityService->evaluate($criteria);
    expect($result->suitable)->toHaveCount(0)
        ->and($result->unsuitable->first()->reasons)->toContain('No active driver currently assigned to this vehicle.');
});
