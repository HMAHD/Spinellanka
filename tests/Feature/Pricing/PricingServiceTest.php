<?php

declare(strict_types=1);

use App\Domain\Booking\ServiceType;
use App\Domain\Catalog\Enums\ExtraPricingType;
use App\Domain\Catalog\Enums\VehicleStatus;
use App\Domain\Pricing\DTOs\EstimateRequestData;
use App\Domain\Pricing\Enums\EstimateStatus;
use App\Domain\Pricing\Enums\PricingComponentType;
use App\Domain\Pricing\Enums\PricingUnit;
use App\Domain\Pricing\Services\ExchangeRateService;
use App\Domain\Pricing\Services\PricingService;
use App\Models\Extra;
use App\Models\PricingRule;
use App\Models\PricingRuleComponent;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->pricingService = app(PricingService::class);
    $this->exchangeService = app(ExchangeRateService::class);
});

test('calculating estimate on empty database returns custom quote result gracefully', function (): void {
    $request = new EstimateRequestData(
        serviceType: ServiceType::AirportPickup,
        vehicleId: null,
        serviceDate: Carbon::now('Asia/Colombo')->addDays(3),
        distanceKm: 45.0
    );

    $result = $this->pricingService->calculateEstimate($request);

    expect($result->status)->toBe(EstimateStatus::CustomQuoteRequired)
        ->and($result->isCustomQuote)->toBeTrue()
        ->and($result->customQuoteReason)->toContain('No active pricing rule configured')
        ->and($result->grandTotal)->toBeNull()
        ->and($result->approximateLkr)->toBeNull();
});

test('missing required distance input for per_km rule degrades to custom quote', function (): void {
    $rule = PricingRule::factory()->create([
        'service_type' => ServiceType::Ride,
        'effective_from' => Carbon::now('Asia/Colombo')->subDays(1),
    ]);

    PricingRuleComponent::factory()->create([
        'pricing_rule_id' => $rule->id,
        'component_type' => PricingComponentType::Base,
        'unit' => PricingUnit::Flat,
        'amount_minor_units' => 3000, // $30.00
    ]);

    PricingRuleComponent::factory()->create([
        'pricing_rule_id' => $rule->id,
        'component_type' => PricingComponentType::Distance,
        'unit' => PricingUnit::PerKm,
        'amount_minor_units' => 150, // $1.50 / km
    ]);

    // Request with missing distanceKm (null)
    $request = new EstimateRequestData(
        serviceType: ServiceType::Ride,
        vehicleId: null,
        serviceDate: Carbon::now('Asia/Colombo')->addDays(1),
        distanceKm: null // missing!
    );

    $result = $this->pricingService->calculateEstimate($request);

    expect($result->isCustomQuote)->toBeTrue()
        ->and($result->customQuoteReason)->toContain('Distance in kilometers is required');
});

test('rent calculation segregates refundable security deposit from service total', function (): void {
    $vehicle = Vehicle::factory()->create([
        'eligible_for_rent' => true,
    ]);

    $rule = PricingRule::factory()->create([
        'service_type' => ServiceType::Rent,
        'vehicle_id' => $vehicle->id,
        'effective_from' => Carbon::now('Asia/Colombo')->subDays(1),
    ]);

    // Daily rate: $80/day = 8000 cents
    PricingRuleComponent::factory()->create([
        'pricing_rule_id' => $rule->id,
        'component_type' => PricingComponentType::Duration,
        'unit' => PricingUnit::PerDay,
        'amount_minor_units' => 8000,
        'is_taxable' => true,
        'is_deposit' => false,
    ]);

    // Refundable Security Deposit: $200 = 20000 cents
    PricingRuleComponent::factory()->create([
        'pricing_rule_id' => $rule->id,
        'component_type' => PricingComponentType::Deposit,
        'unit' => PricingUnit::Flat,
        'amount_minor_units' => 20000,
        'is_taxable' => false,
        'is_deposit' => true,
    ]);

    // 5-day rental hire
    $request = new EstimateRequestData(
        serviceType: ServiceType::Rent,
        vehicleId: $vehicle->id,
        serviceDate: Carbon::now('Asia/Colombo')->addDays(5),
        rentalDays: 5
    );

    $result = $this->pricingService->calculateEstimate($request);

    expect($result->isCustomQuote)->toBeFalse()
        ->and($result->subtotal->amountMinorUnits)->toBe(40000) // $400.00 hire
        ->and($result->depositTotal->amountMinorUnits)->toBe(20000) // $200.00 deposit
        ->and($result->taxTotal->amountMinorUnits)->toBe(0)
        ->and($result->grandTotal->amountMinorUnits)->toBe(60000) // $600.00 total due at booking
        ->and($result->items)->toHaveCount(2);

    $depositItem = collect($result->items)->firstWhere('isDeposit', true);
    expect($depositItem)->not->toBeNull()
        ->and($depositItem->totalAmount->amountMinorUnits)->toBe(20000);
});

test('percentage discount and tax calculation pipeline computes exact minor units', function (): void {
    $rule = PricingRule::factory()->create([
        'service_type' => ServiceType::AirportPickup,
        'effective_from' => Carbon::now('Asia/Colombo')->subDays(1),
    ]);

    // Base charge: $100.00
    PricingRuleComponent::factory()->create([
        'pricing_rule_id' => $rule->id,
        'component_type' => PricingComponentType::Base,
        'unit' => PricingUnit::Flat,
        'amount_minor_units' => 10000,
        'is_taxable' => true,
    ]);

    // Promotional discount: 10% (1000 basis points)
    PricingRuleComponent::factory()->create([
        'pricing_rule_id' => $rule->id,
        'component_type' => PricingComponentType::Discount,
        'unit' => PricingUnit::Percentage,
        'amount_minor_units' => 1000,
    ]);

    // Tax: 15% (1500 basis points) on net taxable base
    PricingRuleComponent::factory()->create([
        'pricing_rule_id' => $rule->id,
        'component_type' => PricingComponentType::Tax,
        'unit' => PricingUnit::Percentage,
        'amount_minor_units' => 1500,
    ]);

    // Record an indicative exchange rate: 300.0000 LKR per USD
    $this->exchangeService->recordRate(300.0000, 'Test CBSL rate');

    $request = new EstimateRequestData(
        serviceType: ServiceType::AirportPickup,
        vehicleId: null,
        serviceDate: Carbon::now('Asia/Colombo')->addDays(2)
    );

    $result = $this->pricingService->calculateEstimate($request);

    // Initial subtotal: $100.00
    // Discount 10%: -$10.00 -> Net subtotal = $90.00 (9000 cents)
    // Tax 15% on $90.00 = $13.50 (1350 cents)
    // Grand Total = $90.00 + $13.50 = $103.50 (10350 cents)
    // LKR = 10350 * 300 = 3,105,000 cents = LKR 31,050.00
    expect($result->subtotal->amountMinorUnits)->toBe(9000)
        ->and($result->taxTotal->amountMinorUnits)->toBe(1350)
        ->and($result->grandTotal->amountMinorUnits)->toBe(10350)
        ->and($result->approximateLkr->amountMinorUnits)->toBe(3105000);
});

test('waiting hours are evaluated independently from travel duration hours', function (): void {
    $rule = PricingRule::factory()->create([
        'service_type' => ServiceType::AirportPickup,
        'effective_from' => Carbon::now('Asia/Colombo')->subDays(1),
    ]);

    // Base fee: $40
    PricingRuleComponent::factory()->create([
        'pricing_rule_id' => $rule->id,
        'component_type' => PricingComponentType::Base,
        'unit' => PricingUnit::Flat,
        'amount_minor_units' => 4000,
    ]);

    // Duration: $20/hr
    PricingRuleComponent::factory()->create([
        'pricing_rule_id' => $rule->id,
        'component_type' => PricingComponentType::Duration,
        'unit' => PricingUnit::PerHour,
        'amount_minor_units' => 2000,
    ]);

    // Waiting: $15/hr
    PricingRuleComponent::factory()->create([
        'pricing_rule_id' => $rule->id,
        'component_type' => PricingComponentType::Waiting,
        'unit' => PricingUnit::PerHour,
        'amount_minor_units' => 1500,
    ]);

    // Request with 3 hours duration and 1 hour waiting
    $request = new EstimateRequestData(
        serviceType: ServiceType::AirportPickup,
        vehicleId: null,
        serviceDate: Carbon::now('Asia/Colombo')->addDays(1),
        durationHours: 3.0,
        waitingHours: 1
    );

    $result = $this->pricingService->calculateEstimate($request);

    // Expected:
    // Base = $40.00 (4000)
    // Duration = 3 hrs * $20 = $60.00 (6000)
    // Waiting = 1 hr * $15 = $15.00 (1500)
    // Subtotal = $115.00 (11500)
    expect($result->isCustomQuote)->toBeFalse()
        ->and($result->subtotal->amountMinorUnits)->toBe(11500);

    $waitingItem = collect($result->items)->firstWhere('componentType', PricingComponentType::Waiting);
    expect($waitingItem)->not->toBeNull()
        ->and($waitingItem->quantity)->toBe(1.0)
        ->and($waitingItem->totalAmount->amountMinorUnits)->toBe(1500);

    $durationItem = collect($result->items)->firstWhere('componentType', PricingComponentType::Duration);
    expect($durationItem)->not->toBeNull()
        ->and($durationItem->quantity)->toBe(3.0)
        ->and($durationItem->totalAmount->amountMinorUnits)->toBe(6000);
});

test('negative stop count degrades to custom quote', function (): void {
    $rule = PricingRule::factory()->create([
        'service_type' => ServiceType::Ride,
        'effective_from' => Carbon::now('Asia/Colombo')->subDays(1),
    ]);

    PricingRuleComponent::factory()->create([
        'pricing_rule_id' => $rule->id,
        'component_type' => PricingComponentType::Base,
        'unit' => PricingUnit::PerStop,
        'amount_minor_units' => 500,
    ]);

    $request = new EstimateRequestData(
        serviceType: ServiceType::Ride,
        vehicleId: null,
        serviceDate: Carbon::now('Asia/Colombo')->addDays(1),
        stopCount: -2
    );

    $result = $this->pricingService->calculateEstimate($request);

    expect($result->isCustomQuote)->toBeTrue()
        ->and($result->customQuoteReason)->toContain('non-negative');
});

test('component min_units and max_units clamp calculation quantities', function (): void {
    $rule = PricingRule::factory()->create([
        'service_type' => ServiceType::Rent,
        'effective_from' => Carbon::now('Asia/Colombo')->subDays(1),
    ]);

    // Minimum 3 days hire, maximum 14 days cap for this tier
    PricingRuleComponent::factory()->create([
        'pricing_rule_id' => $rule->id,
        'component_type' => PricingComponentType::Duration,
        'unit' => PricingUnit::PerDay,
        'amount_minor_units' => 5000, // $50/day
        'min_units' => 3.00,
        'max_units' => 14.00,
    ]);

    // Customer requests 1 day -> clamped to min 3 days ($150.00)
    $requestUnder = new EstimateRequestData(
        serviceType: ServiceType::Rent,
        vehicleId: null,
        serviceDate: Carbon::now('Asia/Colombo')->addDays(2),
        rentalDays: 1
    );

    $resultUnder = $this->pricingService->calculateEstimate($requestUnder);
    expect($resultUnder->subtotal->amountMinorUnits)->toBe(15000)
        ->and($resultUnder->items[0]->quantity)->toBe(3.0);

    // Customer requests 20 days -> clamped to max 14 days ($700.00)
    $requestOver = new EstimateRequestData(
        serviceType: ServiceType::Rent,
        vehicleId: null,
        serviceDate: Carbon::now('Asia/Colombo')->addDays(2),
        rentalDays: 20
    );

    $resultOver = $this->pricingService->calculateEstimate($requestOver);
    expect($resultOver->subtotal->amountMinorUnits)->toBe(70000)
        ->and($resultOver->items[0]->quantity)->toBe(14.0);
});

test('vehicle not in active status degrades to custom quote', function (): void {
    $vehicle = Vehicle::factory()->create([
        'status' => VehicleStatus::Maintenance,
        'eligible_for_rent' => true,
    ]);

    $rule = PricingRule::factory()->create([
        'service_type' => ServiceType::Rent,
        'vehicle_id' => $vehicle->id,
        'effective_from' => Carbon::now('Asia/Colombo')->subDays(1),
    ]);

    PricingRuleComponent::factory()->create([
        'pricing_rule_id' => $rule->id,
        'component_type' => PricingComponentType::Base,
        'unit' => PricingUnit::Flat,
        'amount_minor_units' => 10000,
    ]);

    $request = new EstimateRequestData(
        serviceType: ServiceType::Rent,
        vehicleId: $vehicle->id,
        serviceDate: Carbon::now('Asia/Colombo')->addDays(1),
        rentalDays: 2
    );

    $result = $this->pricingService->calculateEstimate($request);

    expect($result->isCustomQuote)->toBeTrue()
        ->and($result->customQuoteReason)->toContain('not currently active for service');
});

test('catalog extras are priced by type and included as line items in subtotal', function (): void {
    $vehicle = Vehicle::factory()->create([
        'status' => VehicleStatus::Active,
        'eligible_for_rent' => true,
    ]);

    $rule = PricingRule::factory()->create([
        'service_type' => ServiceType::Rent,
        'vehicle_id' => $vehicle->id,
        'effective_from' => Carbon::now('Asia/Colombo')->subDays(1),
    ]);

    PricingRuleComponent::factory()->create([
        'pricing_rule_id' => $rule->id,
        'component_type' => PricingComponentType::Duration,
        'unit' => PricingUnit::PerDay,
        'amount_minor_units' => 5000, // $50/day
        'is_taxable' => true,
    ]);

    // Extra 1: Baby Seat, $10/day
    $dailyExtra = Extra::create([
        'name' => 'Child Seat',
        'slug' => 'child-seat',
        'price_minor_units' => 1000,
        'currency' => 'USD',
        'pricing_type' => ExtraPricingType::PerDay,
        'is_active' => true,
    ]);

    // Extra 2: GPS Nav, $25 flat per booking
    $flatExtra = Extra::create([
        'name' => 'GPS Navigation',
        'slug' => 'gps-navigation',
        'price_minor_units' => 2500,
        'currency' => 'USD',
        'pricing_type' => ExtraPricingType::PerBooking,
        'is_active' => true,
    ]);

    $request = new EstimateRequestData(
        serviceType: ServiceType::Rent,
        vehicleId: $vehicle->id,
        serviceDate: Carbon::now('Asia/Colombo')->addDays(2),
        rentalDays: 4,
        extraIds: [$dailyExtra->id, $flatExtra->id]
    );

    $result = $this->pricingService->calculateEstimate($request);

    // Rental duration: 4 days * $50 = $200.00 (20000)
    // Daily Extra: 4 days * $10 = $40.00 (4000)
    // Flat Extra: 1 * $25 = $25.00 (2500)
    // Total subtotal: $265.00 (26500)
    expect($result->isCustomQuote)->toBeFalse()
        ->and($result->subtotal->amountMinorUnits)->toBe(26500)
        ->and($result->items)->toHaveCount(3);

    $childSeatItem = collect($result->items)->firstWhere('description', 'Extra: Child Seat');
    expect($childSeatItem)->not->toBeNull()
        ->and($childSeatItem->totalAmount->amountMinorUnits)->toBe(4000)
        ->and($childSeatItem->quantity)->toBe(4.0);

    $gpsItem = collect($result->items)->firstWhere('description', 'Extra: GPS Navigation');
    expect($gpsItem)->not->toBeNull()
        ->and($gpsItem->totalAmount->amountMinorUnits)->toBe(2500)
        ->and($gpsItem->quantity)->toBe(1.0);
});

test('waiting hours are evaluated independently from travel duration hours', function (): void {
    $rule = PricingRule::factory()->create([
        'service_type' => ServiceType::AirportPickup,
        'effective_from' => Carbon::now('Asia/Colombo')->subDays(1),
    ]);

    // Base fee: $40
    PricingRuleComponent::factory()->create([
        'pricing_rule_id' => $rule->id,
        'component_type' => PricingComponentType::Base,
        'unit' => PricingUnit::Flat,
        'amount_minor_units' => 4000,
    ]);

    // Duration: $20/hr
    PricingRuleComponent::factory()->create([
        'pricing_rule_id' => $rule->id,
        'component_type' => PricingComponentType::Duration,
        'unit' => PricingUnit::PerHour,
        'amount_minor_units' => 2000,
    ]);

    // Waiting: $15/hr
    PricingRuleComponent::factory()->create([
        'pricing_rule_id' => $rule->id,
        'component_type' => PricingComponentType::Waiting,
        'unit' => PricingUnit::PerHour,
        'amount_minor_units' => 1500,
    ]);

    // Request with 3 hours duration and 1 hour waiting
    $request = new EstimateRequestData(
        serviceType: ServiceType::AirportPickup,
        vehicleId: null,
        serviceDate: Carbon::now('Asia/Colombo')->addDays(1),
        durationHours: 3.0,
        waitingHours: 1
    );

    $result = $this->pricingService->calculateEstimate($request);

    // Expected:
    // Base = $40.00 (4000)
    // Duration = 3 hrs * $20 = $60.00 (6000)
    // Waiting = 1 hr * $15 = $15.00 (1500)
    // Subtotal = $115.00 (11500)
    expect($result->isCustomQuote)->toBeFalse()
        ->and($result->subtotal->amountMinorUnits)->toBe(11500);

    $waitingItem = collect($result->items)->firstWhere('componentType', PricingComponentType::Waiting);
    expect($waitingItem)->not->toBeNull()
        ->and($waitingItem->quantity)->toBe(1.0)
        ->and($waitingItem->totalAmount->amountMinorUnits)->toBe(1500);

    $durationItem = collect($result->items)->firstWhere('componentType', PricingComponentType::Duration);
    expect($durationItem)->not->toBeNull()
        ->and($durationItem->quantity)->toBe(3.0)
        ->and($durationItem->totalAmount->amountMinorUnits)->toBe(6000);
});

test('negative stop count degrades to custom quote', function (): void {
    $rule = PricingRule::factory()->create([
        'service_type' => ServiceType::Ride,
        'effective_from' => Carbon::now('Asia/Colombo')->subDays(1),
    ]);

    PricingRuleComponent::factory()->create([
        'pricing_rule_id' => $rule->id,
        'component_type' => PricingComponentType::Base,
        'unit' => PricingUnit::PerStop,
        'amount_minor_units' => 500,
    ]);

    $request = new EstimateRequestData(
        serviceType: ServiceType::Ride,
        vehicleId: null,
        serviceDate: Carbon::now('Asia/Colombo')->addDays(1),
        stopCount: -2
    );

    $result = $this->pricingService->calculateEstimate($request);

    expect($result->isCustomQuote)->toBeTrue()
        ->and($result->customQuoteReason)->toContain('non-negative');
});

test('component min_units and max_units clamp calculation quantities', function (): void {
    $rule = PricingRule::factory()->create([
        'service_type' => ServiceType::Rent,
        'effective_from' => Carbon::now('Asia/Colombo')->subDays(1),
    ]);

    // Minimum 3 days hire, maximum 14 days cap for this tier
    PricingRuleComponent::factory()->create([
        'pricing_rule_id' => $rule->id,
        'component_type' => PricingComponentType::Duration,
        'unit' => PricingUnit::PerDay,
        'amount_minor_units' => 5000, // $50/day
        'min_units' => 3.00,
        'max_units' => 14.00,
    ]);

    // Customer requests 1 day -> clamped to min 3 days ($150.00)
    $requestUnder = new EstimateRequestData(
        serviceType: ServiceType::Rent,
        vehicleId: null,
        serviceDate: Carbon::now('Asia/Colombo')->addDays(2),
        rentalDays: 1
    );

    $resultUnder = $this->pricingService->calculateEstimate($requestUnder);
    expect($resultUnder->subtotal->amountMinorUnits)->toBe(15000)
        ->and($resultUnder->items[0]->quantity)->toBe(3.0);

    // Customer requests 20 days -> clamped to max 14 days ($700.00)
    $requestOver = new EstimateRequestData(
        serviceType: ServiceType::Rent,
        vehicleId: null,
        serviceDate: Carbon::now('Asia/Colombo')->addDays(2),
        rentalDays: 20
    );

    $resultOver = $this->pricingService->calculateEstimate($requestOver);
    expect($resultOver->subtotal->amountMinorUnits)->toBe(70000)
        ->and($resultOver->items[0]->quantity)->toBe(14.0);
});

test('vehicle not in active status degrades to custom quote', function (): void {
    $vehicle = Vehicle::factory()->create([
        'status' => \App\Domain\Catalog\Enums\VehicleStatus::Maintenance,
        'eligible_for_rent' => true,
    ]);

    $rule = PricingRule::factory()->create([
        'service_type' => ServiceType::Rent,
        'vehicle_id' => $vehicle->id,
        'effective_from' => Carbon::now('Asia/Colombo')->subDays(1),
    ]);

    PricingRuleComponent::factory()->create([
        'pricing_rule_id' => $rule->id,
        'component_type' => PricingComponentType::Base,
        'unit' => PricingUnit::Flat,
        'amount_minor_units' => 10000,
    ]);

    $request = new EstimateRequestData(
        serviceType: ServiceType::Rent,
        vehicleId: $vehicle->id,
        serviceDate: Carbon::now('Asia/Colombo')->addDays(1),
        rentalDays: 2
    );

    $result = $this->pricingService->calculateEstimate($request);

    expect($result->isCustomQuote)->toBeTrue()
        ->and($result->customQuoteReason)->toContain('not currently active for service');
});

test('catalog extras are priced by type and included as line items in subtotal', function (): void {
    $vehicle = Vehicle::factory()->create([
        'status' => \App\Domain\Catalog\Enums\VehicleStatus::Active,
        'eligible_for_rent' => true,
    ]);

    $rule = PricingRule::factory()->create([
        'service_type' => ServiceType::Rent,
        'vehicle_id' => $vehicle->id,
        'effective_from' => Carbon::now('Asia/Colombo')->subDays(1),
    ]);

    PricingRuleComponent::factory()->create([
        'pricing_rule_id' => $rule->id,
        'component_type' => PricingComponentType::Duration,
        'unit' => PricingUnit::PerDay,
        'amount_minor_units' => 5000, // $50/day
        'is_taxable' => true,
    ]);

    // Extra 1: Baby Seat, $10/day
    $dailyExtra = \App\Models\Extra::create([
        'name' => 'Child Seat',
        'slug' => 'child-seat',
        'price_minor_units' => 1000,
        'currency' => 'USD',
        'pricing_type' => \App\Domain\Catalog\Enums\ExtraPricingType::PerDay,
        'is_active' => true,
    ]);

    // Extra 2: GPS Nav, $25 flat per booking
    $flatExtra = \App\Models\Extra::create([
        'name' => 'GPS Navigation',
        'slug' => 'gps-navigation',
        'price_minor_units' => 2500,
        'currency' => 'USD',
        'pricing_type' => \App\Domain\Catalog\Enums\ExtraPricingType::PerBooking,
        'is_active' => true,
    ]);

    $request = new EstimateRequestData(
        serviceType: ServiceType::Rent,
        vehicleId: $vehicle->id,
        serviceDate: Carbon::now('Asia/Colombo')->addDays(2),
        rentalDays: 4,
        extraIds: [$dailyExtra->id, $flatExtra->id]
    );

    $result = $this->pricingService->calculateEstimate($request);

    // Rental duration: 4 days * $50 = $200.00 (20000)
    // Daily Extra: 4 days * $10 = $40.00 (4000)
    // Flat Extra: 1 * $25 = $25.00 (2500)
    // Total subtotal: $265.00 (26500)
    expect($result->isCustomQuote)->toBeFalse()
        ->and($result->subtotal->amountMinorUnits)->toBe(26500)
        ->and($result->items)->toHaveCount(3);

    $childSeatItem = collect($result->items)->firstWhere('description', 'Extra: Child Seat');
    expect($childSeatItem)->not->toBeNull()
        ->and($childSeatItem->totalAmount->amountMinorUnits)->toBe(4000)
        ->and($childSeatItem->quantity)->toBe(4.0);

    $gpsItem = collect($result->items)->firstWhere('description', 'Extra: GPS Navigation');
    expect($gpsItem)->not->toBeNull()
        ->and($gpsItem->totalAmount->amountMinorUnits)->toBe(2500)
        ->and($gpsItem->quantity)->toBe(1.0);
});
