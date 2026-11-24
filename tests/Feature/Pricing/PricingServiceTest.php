<?php

declare(strict_types=1);

use App\Domain\Booking\ServiceType;
use App\Domain\Pricing\DTOs\EstimateRequestData;
use App\Domain\Pricing\Enums\EstimateStatus;
use App\Domain\Pricing\Enums\PricingComponentType;
use App\Domain\Pricing\Enums\PricingUnit;
use App\Domain\Pricing\Services\ExchangeRateService;
use App\Domain\Pricing\Services\PricingService;
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
