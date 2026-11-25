<?php

declare(strict_types=1);

use App\Domain\Availability\Enums\ScheduleBlockKind;
use App\Domain\Availability\Services\AvailabilityService;
use App\Domain\Booking\DTOs\RideCriteria;
use App\Domain\Booking\DTOs\RideStopData;
use App\Domain\Booking\Services\RideSuitabilityService;
use App\Domain\Catalog\Enums\DriverStatus;
use App\Domain\Catalog\Enums\VehicleStatus;
use App\Models\Driver;
use App\Models\Vehicle;
use App\Models\VehicleDriverAssignment;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed();
    $this->suitabilityService = app(RideSuitabilityService::class);
    $this->availabilityService = app(AvailabilityService::class);
});

test('it identifies suitable vehicle when capacity, driver, and schedule align', function (): void {
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

    $criteria = new RideCriteria(
        pickupTime: Carbon::now('Asia/Colombo')->addDays(2)->setTime(9, 0),
        originText: 'Cinnamon Grand, Colombo',
        destinationText: 'Amangalla, Galle Fort',
        passengerCount: 2,
        luggageCount: 2,
        stops: [
            new RideStopData(stopOrder: 1, locationText: 'Bentota Beach', plannedWaitingMinutes: 60),
        ],
    );

    $result = $this->suitabilityService->evaluate($criteria);

    expect($result->suitable->pluck('vehicle.id'))->toContain($vehicle->id)
        ->and($result->unsuitable->pluck('vehicle.id'))->not->toContain($vehicle->id);
});

test('it marks vehicle unsuitable if passenger capacity is exceeded', function (): void {
    $vehicle = Vehicle::factory()->create([
        'eligible_for_ride' => true,
        'status' => VehicleStatus::Active,
        'passenger_capacity' => 2,
        'luggage_capacity' => 4,
    ]);

    $driver = Driver::factory()->create(['status' => DriverStatus::Active]);
    VehicleDriverAssignment::create([
        'vehicle_id' => $vehicle->id,
        'driver_id' => $driver->id,
        'is_permanent' => true,
        'starts_at' => Carbon::now('Asia/Colombo')->subDays(5),
    ]);

    $criteria = new RideCriteria(
        pickupTime: Carbon::now('Asia/Colombo')->addDays(2)->setTime(9, 0),
        originText: 'Colombo',
        destinationText: 'Kandy',
        passengerCount: 4, // Exceeds capacity of 2
        luggageCount: 2,
    );

    $result = $this->suitabilityService->evaluate($criteria);

    $unsuitableItem = $result->unsuitable->firstWhere('vehicle.id', $vehicle->id);
    expect($unsuitableItem)->not->toBeNull()
        ->and($unsuitableItem->reasons)->toContain('Passenger capacity (2) is less than required (4).');
});

test('it marks vehicle unsuitable if no active dedicated chauffeur is assigned', function (): void {
    $vehicle = Vehicle::factory()->create([
        'eligible_for_ride' => true,
        'status' => VehicleStatus::Active,
        'passenger_capacity' => 4,
        'luggage_capacity' => 4,
    ]);
    // No driver assignment

    $criteria = new RideCriteria(
        pickupTime: Carbon::now('Asia/Colombo')->addDays(2)->setTime(9, 0),
        originText: 'Colombo',
        destinationText: 'Galle',
        passengerCount: 2,
        luggageCount: 2,
    );

    $result = $this->suitabilityService->evaluate($criteria);

    $unsuitableItem = $result->unsuitable->firstWhere('vehicle.id', $vehicle->id);
    expect($unsuitableItem)->not->toBeNull()
        ->and($unsuitableItem->reasons)->toContain('No active driver currently assigned to this vehicle for outbound journey.');
});

test('it marks vehicle unsuitable if return leg conflicts with existing schedule', function (): void {
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

    $pickupTime = Carbon::now('Asia/Colombo')->addDays(2)->setTime(8, 0);
    $returnTime = Carbon::now('Asia/Colombo')->addDays(2)->setTime(18, 0);

    // Create an existing schedule block during the requested return time
    $this->availabilityService->createBlock(
        vehicle: $vehicle,
        kind: ScheduleBlockKind::ConfirmedBooking,
        start: Carbon::now('Asia/Colombo')->addDays(2)->setTime(17, 30),
        end: Carbon::now('Asia/Colombo')->addDays(2)->setTime(21, 0),
        bufferBefore: 30,
        bufferAfter: 60,
    );

    $criteria = new RideCriteria(
        pickupTime: $pickupTime,
        originText: 'Colombo',
        destinationText: 'Galle',
        passengerCount: 2,
        luggageCount: 2,
        hasReturn: true,
        returnPickupTime: $returnTime,
    );

    $result = $this->suitabilityService->evaluate($criteria);

    $unsuitableItem = $result->unsuitable->firstWhere('vehicle.id', $vehicle->id);
    expect($unsuitableItem)->not->toBeNull()
        ->and($unsuitableItem->reasons)->toContain('Vehicle has an existing booking or operational block during requested return time.');
});
