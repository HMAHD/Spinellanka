<?php

declare(strict_types=1);

use App\Domain\Availability\Enums\ScheduleBlockKind;
use App\Domain\Availability\Enums\ScheduleBlockStatus;
use App\Domain\Booking\DTOs\RentCriteria;
use App\Domain\Booking\Services\RentSuitabilityService;
use App\Domain\Catalog\Enums\VehicleStatus;
use App\Models\Driver;
use App\Models\RentPolicy;
use App\Models\Vehicle;
use App\Models\VehicleDriverAssignment;
use App\Models\VehicleScheduleBlock;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->policy = RentPolicy::factory()->create([
        'min_driver_age' => 23,
        'max_driver_age' => 70,
        'min_licence_years' => 2,
        'idp_required' => true,
    ]);

    // Active vehicle eligible for rent
    $this->vehicle = Vehicle::factory()->create([
        'public_name' => 'Toyota Land Cruiser Prado',
        'make' => 'Toyota',
        'model' => 'Land Cruiser Prado',
        'status' => VehicleStatus::Active,
        'eligible_for_rent' => true,
        'eligible_for_pickup' => false,
        'eligible_for_transfer' => false,
        'turnaround_buffer_minutes' => 60,
    ]);

    // Active vehicle NOT eligible for rent (chauffeured only)
    $this->chauffeuredOnlyVehicle = Vehicle::factory()->create([
        'public_name' => 'Mercedes-Benz E-Class',
        'status' => VehicleStatus::Active,
        'eligible_for_rent' => false,
        'eligible_for_transfer' => true,
    ]);

    $this->suitabilityService = app(RentSuitabilityService::class);
});

test('evaluates eligible vehicle as suitable when criteria match policy', function (): void {
    $pickup = Carbon::now('Asia/Colombo')->addDays(2)->setHour(10)->setMinute(0)->setSecond(0);
    $return = (clone $pickup)->addDays(3);

    $criteria = new RentCriteria(
        pickupDateTime: $pickup,
        returnDateTime: $return,
        pickupLocationType: 'office',
        pickupLocationText: 'Spinellanka Head Office, Colombo 03',
        returnLocationType: 'office',
        returnLocationText: 'Spinellanka Head Office, Colombo 03',
        driverAge: 35,
        driverLicenceCountry: 'United Kingdom',
        driverLicenceYears: 8,
        hasIdp: true,
        durationDays: 3,
        extraHours: 0,
    );

    $result = $this->suitabilityService->evaluate($criteria, $this->policy);

    expect($result->suitable)->toHaveCount(1)
        ->and($result->suitable->first()->vehicle->id)->toBe($this->vehicle->id)
        ->and($result->suitable->first()->isSuitable)->toBeTrue()
        ->and($result->unsuitable)->toHaveCount(0);
});

test('vehicles not eligible for rent are excluded from candidate evaluation', function (): void {
    $pickup = Carbon::now('Asia/Colombo')->addDays(2);
    $return = (clone $pickup)->addDays(2);

    $criteria = new RentCriteria(
        pickupDateTime: $pickup,
        returnDateTime: $return,
        pickupLocationType: 'office',
        pickupLocationText: 'Office',
        returnLocationType: 'office',
        returnLocationText: 'Office',
        driverAge: 30,
        driverLicenceCountry: 'UK',
        driverLicenceYears: 5,
        hasIdp: true,
        durationDays: 2,
    );

    $result = $this->suitabilityService->evaluate($criteria, $this->policy);

    // chauffeuredOnlyVehicle should not appear in suitable or unsuitable
    $evaluatedIds = $result->suitable->pluck('vehicle.id')->concat($result->unsuitable->pluck('vehicle.id'));
    expect($evaluatedIds)->not->toContain($this->chauffeuredOnlyVehicle->id);
});

test('underage lead driver marks vehicle unsuitable', function (): void {
    $pickup = Carbon::now('Asia/Colombo')->addDays(2);
    $return = (clone $pickup)->addDays(2);

    $criteria = new RentCriteria(
        pickupDateTime: $pickup,
        returnDateTime: $return,
        pickupLocationType: 'office',
        pickupLocationText: 'Office',
        returnLocationType: 'office',
        returnLocationText: 'Office',
        driverAge: 21, // Under min age of 23
        driverLicenceCountry: 'UK',
        driverLicenceYears: 3,
        hasIdp: true,
        durationDays: 2,
    );

    $result = $this->suitabilityService->evaluate($criteria, $this->policy);

    expect($result->suitable)->toHaveCount(0)
        ->and($result->unsuitable)->toHaveCount(1)
        ->and($result->unsuitable->first()->reasons)->toContain('Lead driver age (21) must be between 23 and 70 years.');
});

test('insufficient licence experience marks vehicle unsuitable', function (): void {
    $pickup = Carbon::now('Asia/Colombo')->addDays(2);
    $return = (clone $pickup)->addDays(2);

    $criteria = new RentCriteria(
        pickupDateTime: $pickup,
        returnDateTime: $return,
        pickupLocationType: 'office',
        pickupLocationText: 'Office',
        returnLocationType: 'office',
        returnLocationText: 'Office',
        driverAge: 25,
        driverLicenceCountry: 'UK',
        driverLicenceYears: 1, // Under min 2 years
        hasIdp: true,
        durationDays: 2,
    );

    $result = $this->suitabilityService->evaluate($criteria, $this->policy);

    expect($result->suitable)->toHaveCount(0)
        ->and($result->unsuitable)->toHaveCount(1)
        ->and($result->unsuitable->first()->reasons)->toContain('Lead driver must have held a valid licence for at least 2 years (current: 1).');
});

test('missing IDP when required marks vehicle unsuitable', function (): void {
    $pickup = Carbon::now('Asia/Colombo')->addDays(2);
    $return = (clone $pickup)->addDays(2);

    $criteria = new RentCriteria(
        pickupDateTime: $pickup,
        returnDateTime: $return,
        pickupLocationType: 'office',
        pickupLocationText: 'Office',
        returnLocationType: 'office',
        returnLocationText: 'Office',
        driverAge: 30,
        driverLicenceCountry: 'UK',
        driverLicenceYears: 5,
        hasIdp: false, // No IDP
        durationDays: 2,
    );

    $result = $this->suitabilityService->evaluate($criteria, $this->policy);

    expect($result->suitable)->toHaveCount(0)
        ->and($result->unsuitable)->toHaveCount(1)
        ->and($result->unsuitable->first()->reasons)->toContain('An International Driving Permit (IDP) or Sri Lankan temporary driving permit endorsement is required.');
});

test('invariant 6: rent creates no driver assignment and does not check for assigned chauffeured driver', function (): void {
    // Ensure vehicle has NO driver assigned at all
    VehicleDriverAssignment::where('vehicle_id', $this->vehicle->id)->delete();

    $pickup = Carbon::now('Asia/Colombo')->addDays(2)->setHour(10)->setMinute(0);
    $return = (clone $pickup)->addDays(2);

    $criteria = new RentCriteria(
        pickupDateTime: $pickup,
        returnDateTime: $return,
        pickupLocationType: 'office',
        pickupLocationText: 'Office',
        returnLocationType: 'office',
        returnLocationText: 'Office',
        driverAge: 30,
        driverLicenceCountry: 'UK',
        driverLicenceYears: 5,
        hasIdp: true,
        durationDays: 2,
    );

    $result = $this->suitabilityService->evaluate($criteria, $this->policy);

    // Still suitable because Rent is self-drive!
    expect($result->suitable)->toHaveCount(1)
        ->and($result->suitable->first()->vehicle->id)->toBe($this->vehicle->id);
});

test('detects schedule conflict during requested rental interval', function (): void {
    $pickup = Carbon::now('Asia/Colombo')->addDays(2)->setHour(10)->setMinute(0);
    $return = (clone $pickup)->addDays(3);

    // Create an overlapping confirmed block on this vehicle
    VehicleScheduleBlock::create([
        'vehicle_id' => $this->vehicle->id,
        'kind' => ScheduleBlockKind::ConfirmedBooking,
        'starts_at' => (clone $pickup)->addDay(),
        'ends_at' => (clone $pickup)->addDays(2),
        'buffer_minutes_before' => 0,
        'buffer_minutes_after' => 60,
        'effective_start' => (clone $pickup)->addDay(),
        'effective_end' => (clone $pickup)->addDays(2)->addMinutes(60),
        'blocks_availability' => true,
        'status' => ScheduleBlockStatus::Active,
        'is_chauffeured_only' => false,
    ]);

    $criteria = new RentCriteria(
        pickupDateTime: $pickup,
        returnDateTime: $return,
        pickupLocationType: 'office',
        pickupLocationText: 'Office',
        returnLocationType: 'office',
        returnLocationText: 'Office',
        driverAge: 30,
        driverLicenceCountry: 'UK',
        driverLicenceYears: 5,
        hasIdp: true,
        durationDays: 3,
    );

    $result = $this->suitabilityService->evaluate($criteria, $this->policy);

    expect($result->suitable)->toHaveCount(0)
        ->and($result->unsuitable)->toHaveCount(1)
        ->and($result->unsuitable->first()->reasons)->toContain('Vehicle schedule conflicts with an existing booking or maintenance block.');
});
