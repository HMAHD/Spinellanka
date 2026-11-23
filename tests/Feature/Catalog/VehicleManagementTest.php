<?php

use App\Domain\Catalog\Enums\VehicleStatus;
use App\Domain\Catalog\Enums\VehicleType;
use App\Domain\Catalog\Services\VehicleService;
use App\Domain\Identity\Role;
use App\Models\Driver;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleDriverAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed();

    $this->owner = User::create([
        'name' => 'Owner Admin',
        'email' => 'owner@spinellanka.test',
        'password' => Hash::make('Password123456!'),
        'is_active' => true,
        'token_version' => 1,
    ]);
    $this->owner->assignRole(Role::OwnerAdmin->value);

    $this->staff = User::create([
        'name' => 'Operational Staff',
        'email' => 'staff@spinellanka.test',
        'password' => Hash::make('Password123456!'),
        'is_active' => true,
        'token_version' => 1,
    ]);
    $this->staff->assignRole(Role::Staff->value);
});

test('staff and owner can view vehicles but only owner can manage vehicles', function (): void {
    $vehicle = Vehicle::factory()->create();

    // Staff authorization check
    expect($this->staff->can('viewAny', Vehicle::class))->toBeTrue();
    expect($this->staff->can('view', $vehicle))->toBeTrue();
    expect($this->staff->can('create', Vehicle::class))->toBeFalse();
    expect($this->staff->can('update', $vehicle))->toBeFalse();
    expect($this->staff->can('delete', $vehicle))->toBeFalse();

    // Owner authorization check
    expect($this->owner->can('viewAny', Vehicle::class))->toBeTrue();
    expect($this->owner->can('view', $vehicle))->toBeTrue();
    expect($this->owner->can('create', Vehicle::class))->toBeTrue();
    expect($this->owner->can('update', $vehicle))->toBeTrue();
    expect($this->owner->can('delete', $vehicle))->toBeTrue();
});

test('turnaround buffer must be between 30 and 720 minutes', function (): void {
    $service = app(VehicleService::class);

    // Valid buffer (90 minutes)
    $validVehicle = $service->createVehicle([
        'public_name' => 'Test Alphard',
        'internal_fleet_id' => 'FLEET-VAL-01',
        'make' => 'Toyota',
        'model' => 'Alphard',
        'year' => 2024,
        'license_plate' => 'WP-VAL-1111',
        'vehicle_type' => VehicleType::LuxuryVan->value,
        'passenger_capacity' => 5,
        'luggage_capacity' => 4,
        'transmission' => 'automatic',
        'turnaround_buffer_minutes' => 90,
    ], $this->owner);

    expect($validVehicle->turnaround_buffer_minutes)->toBe(90);

    // Invalid buffer: too low (< 30)
    expect(fn () => $service->createVehicle([
        'public_name' => 'Test Invalid Low',
        'internal_fleet_id' => 'FLEET-INV-01',
        'make' => 'Toyota',
        'model' => 'Alphard',
        'year' => 2024,
        'license_plate' => 'WP-INV-1111',
        'vehicle_type' => VehicleType::LuxuryVan->value,
        'passenger_capacity' => 5,
        'luggage_capacity' => 4,
        'transmission' => 'automatic',
        'turnaround_buffer_minutes' => 20,
    ], $this->owner))->toThrow(InvalidArgumentException::class);

    // Invalid buffer: too high (> 720)
    expect(fn () => $service->createVehicle([
        'public_name' => 'Test Invalid High',
        'internal_fleet_id' => 'FLEET-INV-02',
        'make' => 'Toyota',
        'model' => 'Alphard',
        'year' => 2024,
        'license_plate' => 'WP-INV-2222',
        'vehicle_type' => VehicleType::LuxuryVan->value,
        'passenger_capacity' => 5,
        'luggage_capacity' => 4,
        'transmission' => 'automatic',
        'turnaround_buffer_minutes' => 800,
    ], $this->owner))->toThrow(InvalidArgumentException::class);
});

test('sensitive operational fields are hidden from array serialization', function (): void {
    $vehicle = Vehicle::factory()->create([
        'license_plate' => 'WP-TEST-9999',
        'vin' => 'VIN-SECRET-12345',
        'internal_fleet_id' => 'FLEET-HIDDEN-01',
        'internal_notes' => 'Secret maintenance tracking notes',
    ]);

    $array = $vehicle->toArray();

    expect($array)->not->toHaveKey('license_plate');
    expect($array)->not->toHaveKey('vin');
    expect($array)->not->toHaveKey('internal_fleet_id');
    expect($array)->not->toHaveKey('internal_notes');
    expect($array)->toHaveKey('public_name');
    expect($array)->toHaveKey('make');
    expect($array)->toHaveKey('model');
});

test('retiring a vehicle automatically closes open-ended driver assignments', function (): void {
    $service = app(VehicleService::class);
    $vehicle = Vehicle::factory()->create(['status' => VehicleStatus::Active]);
    $driver = Driver::factory()->create();

    $assignment = VehicleDriverAssignment::create([
        'vehicle_id' => $vehicle->id,
        'driver_id' => $driver->id,
        'starts_at' => now()->subDays(5),
        'ends_at' => null,
        'is_permanent' => true,
    ]);

    expect($assignment->fresh()->ends_at)->toBeNull();

    $service->updateStatus($vehicle, VehicleStatus::Retired, 'Vehicle decommissioned', $this->owner);

    expect($vehicle->fresh()->status)->toBe(VehicleStatus::Retired);
    expect($assignment->fresh()->ends_at)->not->toBeNull();
});

test('cannot delete vehicle if driver assignments exist', function (): void {
    $vehicle = Vehicle::factory()->create();
    $driver = Driver::factory()->create();

    VehicleDriverAssignment::create([
        'vehicle_id' => $vehicle->id,
        'driver_id' => $driver->id,
        'starts_at' => now(),
        'ends_at' => null,
        'is_permanent' => true,
    ]);

    expect($this->owner->can('delete', $vehicle))->toBeFalse();
});

