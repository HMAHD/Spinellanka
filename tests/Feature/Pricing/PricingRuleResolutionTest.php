<?php

declare(strict_types=1);

use App\Domain\Booking\ServiceType;
use App\Domain\Pricing\Services\PricingService;
use App\Models\PricingRule;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->pricingService = app(PricingService::class);
});

test('vehicle specific rule takes precedence over service wide default rule regardless of priority', function (): void {
    $vehicle = Vehicle::factory()->create([
        'eligible_for_pickup' => true,
    ]);

    // Service-wide default rule with high priority
    $serviceRule = PricingRule::factory()->create([
        'service_type' => ServiceType::AirportPickup,
        'vehicle_id' => null,
        'priority' => 100,
        'effective_from' => Carbon::now('Asia/Colombo')->subDays(5),
    ]);

    // Vehicle-specific rule with low priority
    $vehicleRule = PricingRule::factory()->create([
        'service_type' => ServiceType::AirportPickup,
        'vehicle_id' => $vehicle->id,
        'priority' => 1,
        'effective_from' => Carbon::now('Asia/Colombo')->subDays(5),
    ]);

    $resolved = $this->pricingService->resolveRule(
        ServiceType::AirportPickup,
        $vehicle,
        Carbon::now('Asia/Colombo')
    );

    expect($resolved)->not->toBeNull()
        ->and($resolved->id)->toBe($vehicleRule->id);
});

test('service wide default rule is selected when no vehicle specific rule exists', function (): void {
    $vehicle = Vehicle::factory()->create([
        'eligible_for_pickup' => true,
    ]);

    $serviceRule = PricingRule::factory()->create([
        'service_type' => ServiceType::AirportPickup,
        'vehicle_id' => null,
        'priority' => 10,
        'effective_from' => Carbon::now('Asia/Colombo')->subDays(5),
    ]);

    $resolved = $this->pricingService->resolveRule(
        ServiceType::AirportPickup,
        $vehicle,
        Carbon::now('Asia/Colombo')
    );

    expect($resolved)->not->toBeNull()
        ->and($resolved->id)->toBe($serviceRule->id);
});

test('rule resolution respects effective date window boundaries in Asia/Colombo', function (): void {
    $from = Carbon::parse('2026-12-01 00:00:00', 'Asia/Colombo');
    $to = Carbon::parse('2026-12-31 23:59:59', 'Asia/Colombo');

    $rule = PricingRule::factory()->create([
        'service_type' => ServiceType::Ride,
        'vehicle_id' => null,
        'effective_from' => $from,
        'effective_to' => $to,
    ]);

    // Pre-activation check (1 second before)
    $beforeDate = Carbon::parse('2026-11-30 23:59:59', 'Asia/Colombo');
    expect($this->pricingService->resolveRule(ServiceType::Ride, null, $beforeDate))->toBeNull();

    // Active start boundary
    $startDate = Carbon::parse('2026-12-01 00:00:00', 'Asia/Colombo');
    expect($this->pricingService->resolveRule(ServiceType::Ride, null, $startDate)?->id)->toBe($rule->id);

    // Active mid-window
    $midDate = Carbon::parse('2026-12-15 12:00:00', 'Asia/Colombo');
    expect($this->pricingService->resolveRule(ServiceType::Ride, null, $midDate)?->id)->toBe($rule->id);

    // Post-expiration check (1 second after)
    $afterDate = Carbon::parse('2027-01-01 00:00:00', 'Asia/Colombo');
    expect($this->pricingService->resolveRule(ServiceType::Ride, null, $afterDate))->toBeNull();
});

test('ineligible vehicle returns null rule regardless of matching rules', function (): void {
    $vehicle = Vehicle::factory()->create([
        'eligible_for_pickup' => false, // explicitly not eligible
    ]);

    PricingRule::factory()->create([
        'service_type' => ServiceType::AirportPickup,
        'vehicle_id' => $vehicle->id,
        'effective_from' => Carbon::now('Asia/Colombo')->subDays(5),
    ]);

    $resolved = $this->pricingService->resolveRule(
        ServiceType::AirportPickup,
        $vehicle,
        Carbon::now('Asia/Colombo')
    );

    expect($resolved)->toBeNull();
});
