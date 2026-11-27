<?php

declare(strict_types=1);

use App\Domain\Booking\Enums\DealStatus;
use App\Domain\Booking\Enums\PromotionDiscountType;
use App\Domain\Booking\Enums\PromotionScope;
use App\Domain\Booking\Enums\PromotionStatus;
use App\Domain\Booking\ServiceType;
use App\Domain\Pricing\DTOs\EstimateRequestData;
use App\Domain\Pricing\Enums\PricingComponentType;
use App\Domain\Pricing\Enums\PricingUnit;
use App\Domain\Pricing\Services\EstimateService;
use App\Domain\Pricing\Services\PricingService;
use App\Models\Deal;
use App\Models\PricingRule;
use App\Models\PricingRuleComponent;
use App\Models\Promotion;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->pricingService = app(PricingService::class);
    $this->estimateService = app(EstimateService::class);
});

test('promotional percentage discount applies to subtotal and reduces taxable base without touching security deposit', function (): void {
    $rule = PricingRule::factory()->create([
        'service_type' => ServiceType::AirportPickup,
        'effective_from' => Carbon::now('Asia/Colombo')->subDays(1),
    ]);

    // Base: $100.00 (taxable)
    PricingRuleComponent::factory()->create([
        'pricing_rule_id' => $rule->id,
        'component_type' => PricingComponentType::Base,
        'unit' => PricingUnit::Flat,
        'amount_minor_units' => 10000,
        'is_taxable' => true,
    ]);

    // Tax: 10% (1000 basis points)
    PricingRuleComponent::factory()->create([
        'pricing_rule_id' => $rule->id,
        'component_type' => PricingComponentType::Tax,
        'unit' => PricingUnit::Percentage,
        'amount_minor_units' => 1000,
    ]);

    // Security Deposit: $50.00 (segregated, non-taxable, non-discountable)
    PricingRuleComponent::factory()->create([
        'pricing_rule_id' => $rule->id,
        'component_type' => PricingComponentType::Deposit,
        'unit' => PricingUnit::Flat,
        'amount_minor_units' => 5000,
        'is_deposit' => true,
        'is_taxable' => false,
    ]);

    // 20% Global Promotion (2000 basis points)
    $promo = Promotion::factory()->create([
        'name' => 'Spring 20% Off',
        'scope' => PromotionScope::Global,
        'discount_type' => PromotionDiscountType::PercentageBasisPoints,
        'discount_value' => 2000,
        'status' => PromotionStatus::Active,
        'priority' => 1,
    ]);

    $request = new EstimateRequestData(
        serviceType: ServiceType::AirportPickup,
        serviceDate: Carbon::now('Asia/Colombo')->addDays(2),
    );

    $result = $this->pricingService->calculateEstimate($request);

    // Subtotal: 100.00 - 20.00 = 80.00 ($80.00 = 8000 minor units)
    expect($result->subtotal->amountMinorUnits)->toBe(8000)
        // Tax is 10% of 80.00 = 8.00 ($8.00 = 800 minor units)
        ->and($result->taxTotal->amountMinorUnits)->toBe(800)
        // Deposit is untouched = $50.00 = 5000 minor units
        ->and($result->depositTotal->amountMinorUnits)->toBe(5000)
        // Grand total: 80.00 + 8.00 + 50.00 = 138.00 ($138.00 = 13800 minor units)
        ->and($result->grandTotal->amountMinorUnits)->toBe(13800)
        ->and($result->appliedPromotionId)->toBe($promo->id)
        ->and($result->promotionSnapshot)->not->toBeNull()
        ->and($result->promotionSnapshot['name'])->toBe('Spring 20% Off');

    // Test snapshot persistence
    $snapshot = $this->estimateService->snapshotEstimate($result, $request);
    expect($snapshot->applied_promotion_id)->toBe($promo->id)
        ->and($snapshot->promotion_snapshot['name'])->toBe('Spring 20% Off')
        ->and($snapshot->subtotal_minor_units)->toBe(8000)
        ->and($snapshot->deposit_minor_units)->toBe(5000)
        ->and($snapshot->total_amount_minor_units)->toBe(13800);
});

test('promotional discount does not stack if pricing rule already contains a discount component', function (): void {
    $rule = PricingRule::factory()->create([
        'service_type' => ServiceType::Ride,
        'effective_from' => Carbon::now('Asia/Colombo')->subDays(1),
    ]);

    // Base: $100.00
    PricingRuleComponent::factory()->create([
        'pricing_rule_id' => $rule->id,
        'component_type' => PricingComponentType::Base,
        'unit' => PricingUnit::Flat,
        'amount_minor_units' => 10000,
    ]);

    // Rule-level discount: $10.00
    PricingRuleComponent::factory()->create([
        'pricing_rule_id' => $rule->id,
        'component_type' => PricingComponentType::Discount,
        'unit' => PricingUnit::Flat,
        'amount_minor_units' => 1000,
    ]);

    // Active Global Promotion: $15.00
    Promotion::factory()->create([
        'name' => 'Global Extra $15',
        'scope' => PromotionScope::Global,
        'discount_type' => PromotionDiscountType::FixedAmount,
        'discount_value' => 1500,
        'currency' => 'USD',
        'status' => PromotionStatus::Active,
    ]);

    $request = new EstimateRequestData(
        serviceType: ServiceType::Ride,
        serviceDate: Carbon::now('Asia/Colombo')->addDays(1),
    );

    $result = $this->pricingService->calculateEstimate($request);

    // Subtotal should only reflect the rule discount: $100 - $10 = $90 (9000 minor units)
    expect($result->subtotal->amountMinorUnits)->toBe(9000)
        ->and($result->appliedPromotionId)->toBeNull();
});

test('promotional discount is capped at subtotal and cannot result in negative subtotal', function (): void {
    $rule = PricingRule::factory()->create([
        'service_type' => ServiceType::AirportPickup,
        'effective_from' => Carbon::now('Asia/Colombo')->subDays(1),
    ]);

    // Base: $40.00
    PricingRuleComponent::factory()->create([
        'pricing_rule_id' => $rule->id,
        'component_type' => PricingComponentType::Base,
        'unit' => PricingUnit::Flat,
        'amount_minor_units' => 4000,
    ]);

    // Giant promotion: $100.00 off
    Promotion::factory()->create([
        'name' => 'Huge Voucher',
        'scope' => PromotionScope::Global,
        'discount_type' => PromotionDiscountType::FixedAmount,
        'discount_value' => 10000,
        'currency' => 'USD',
        'status' => PromotionStatus::Active,
    ]);

    $request = new EstimateRequestData(
        serviceType: ServiceType::AirportPickup,
        serviceDate: Carbon::now('Asia/Colombo')->addDays(1),
    );

    $result = $this->pricingService->calculateEstimate($request);

    // Subtotal is capped at 0, not negative
    expect($result->subtotal->amountMinorUnits)->toBe(0)
        ->and($result->grandTotal->amountMinorUnits)->toBe(0);
});

test('deal-linked promotion is applied when dealId is provided in estimate request', function (): void {
    $deal = Deal::factory()->create([
        'status' => DealStatus::Published,
        'service_type' => ServiceType::AirportTransfer,
    ]);

    $dealPromo = Promotion::factory()->create([
        'name' => 'Exclusive Deal Special',
        'scope' => PromotionScope::Deal,
        'deal_id' => $deal->id,
        'discount_type' => PromotionDiscountType::FixedAmount,
        'discount_value' => 1200, // $12.00
        'currency' => 'USD',
        'status' => PromotionStatus::Active,
    ]);

    $rule = PricingRule::factory()->create([
        'service_type' => ServiceType::AirportTransfer,
        'effective_from' => Carbon::now('Asia/Colombo')->subDays(1),
    ]);

    // Base: $50.00
    PricingRuleComponent::factory()->create([
        'pricing_rule_id' => $rule->id,
        'component_type' => PricingComponentType::Base,
        'unit' => PricingUnit::Flat,
        'amount_minor_units' => 5000,
    ]);

    $request = new EstimateRequestData(
        serviceType: ServiceType::AirportTransfer,
        serviceDate: Carbon::now('Asia/Colombo')->addDays(1),
        dealId: $deal->id,
    );

    $result = $this->pricingService->calculateEstimate($request);

    expect($result->subtotal->amountMinorUnits)->toBe(3800) // $50 - $12 = $38
        ->and($result->appliedDealId)->toBe($deal->id)
        ->and($result->appliedPromotionId)->toBe($dealPromo->id)
        ->and($result->promotionSnapshot['name'])->toBe('Exclusive Deal Special');
});
