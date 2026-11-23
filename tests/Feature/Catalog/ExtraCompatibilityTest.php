<?php

use App\Domain\Booking\ServiceType;
use App\Domain\Catalog\Enums\VehicleType;
use App\Domain\Catalog\Services\ExtraService;
use App\Models\Extra;
use App\Models\ExtraCompatibility;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('extra without any compatibility rules is globally compatible', function (): void {
    $service = app(ExtraService::class);
    $extra = Extra::factory()->create(['is_active' => true]);
    $vehicle = Vehicle::factory()->create(['vehicle_type' => VehicleType::LuxuryVan]);

    expect($service->isCompatible($extra, ServiceType::AirportPickup, $vehicle))->toBeTrue();
    expect($service->isCompatible($extra, ServiceType::Rent, $vehicle))->toBeTrue();
    expect($service->isCompatible($extra, ServiceType::Ride, null))->toBeTrue();
});

test('extra with rules follows 4-tier precedence hierarchy and allowlist fallback', function (): void {
    $service = app(ExtraService::class);
    $extra = Extra::factory()->create(['is_active' => true]);

    $van = Vehicle::factory()->create(['vehicle_type' => VehicleType::LuxuryVan]);
    $sedan = Vehicle::factory()->create(['vehicle_type' => VehicleType::Sedan]);

    // Rule: Extra is compatible with LuxuryVan for AirportPickup
    ExtraCompatibility::create([
        'extra_id' => $extra->id,
        'service_type' => ServiceType::AirportPickup->value,
        'vehicle_type' => VehicleType::LuxuryVan->value,
        'is_compatible' => true,
    ]);

    // Compatible match
    expect($service->isCompatible($extra, ServiceType::AirportPickup, $van))->toBeTrue();

    // Mismatched service (Ride with LuxuryVan) -> Incompatible (fallback allowlist)
    expect($service->isCompatible($extra, ServiceType::Ride, $van))->toBeFalse();

    // Mismatched vehicle (Sedan with AirportPickup) -> Incompatible
    expect($service->isCompatible($extra, ServiceType::AirportPickup, $sedan))->toBeFalse();
});

test('tier 1 exact vehicle override takes precedence over vehicle type rule', function (): void {
    $service = app(ExtraService::class);
    $extra = Extra::factory()->create(['is_active' => true]);

    $van1 = Vehicle::factory()->create(['vehicle_type' => VehicleType::LuxuryVan]);
    $van2 = Vehicle::factory()->create(['vehicle_type' => VehicleType::LuxuryVan]);

    // Vehicle-wide rule: Incompatible with LuxuryVan
    ExtraCompatibility::create([
        'extra_id' => $extra->id,
        'vehicle_type' => VehicleType::LuxuryVan->value,
        'is_compatible' => false,
    ]);

    // Specific vehicle override: Van 1 is explicitly compatible
    ExtraCompatibility::create([
        'extra_id' => $extra->id,
        'vehicle_id' => $van1->id,
        'is_compatible' => true,
    ]);

    expect($service->isCompatible($extra, ServiceType::AirportPickup, $van1))->toBeTrue();
    expect($service->isCompatible($extra, ServiceType::AirportPickup, $van2))->toBeFalse();
});

test('inactive extra is never compatible regardless of rules', function (): void {
    $service = app(ExtraService::class);
    $extra = Extra::factory()->create(['is_active' => false]);
    $vehicle = Vehicle::factory()->create();

    expect($service->isCompatible($extra, ServiceType::AirportPickup, $vehicle))->toBeFalse();
});
