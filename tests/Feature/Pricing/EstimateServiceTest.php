<?php

declare(strict_types=1);

use App\Domain\Booking\ServiceType;
use App\Domain\Pricing\DTOs\EstimateRequestData;
use App\Domain\Pricing\Enums\PricingComponentType;
use App\Domain\Pricing\Enums\PricingUnit;
use App\Domain\Pricing\Services\EstimateService;
use App\Domain\Pricing\Services\PricingService;
use App\Models\AuditEvent;
use App\Models\Estimate;
use App\Models\PricingRule;
use App\Models\PricingRuleComponent;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->pricingService = app(PricingService::class);
    $this->estimateService = app(EstimateService::class);
});

test('snapshotEstimate persists immutable estimate and itemized lines with audit event', function (): void {
    $rule = PricingRule::factory()->create([
        'service_type' => ServiceType::AirportPickup,
        'version' => 3,
        'effective_from' => Carbon::now('Asia/Colombo')->subDays(2),
    ]);

    PricingRuleComponent::factory()->create([
        'pricing_rule_id' => $rule->id,
        'component_type' => PricingComponentType::Base,
        'unit' => PricingUnit::Flat,
        'amount_minor_units' => 6000,
    ]);

    $request = new EstimateRequestData(
        serviceType: ServiceType::AirportPickup,
        vehicleId: null,
        serviceDate: Carbon::now('Asia/Colombo')->addDays(2),
        assumptions: ['flight_number' => 'UL-102']
    );

    $result = $this->pricingService->calculateEstimate($request);
    $estimate = $this->estimateService->snapshotEstimate($result, $request);

    expect($estimate->id)->not->toBeNull()
        ->and($estimate->reference)->toStartWith('EST-')
        ->and($estimate->rule_version)->toBe(3)
        ->and($estimate->total_amount_minor_units)->toBe(6000)
        ->and($estimate->assumptions['flight_number'])->toBe('UL-102')
        ->and($estimate->items)->toHaveCount(1);

    // Audit event verification
    $audit = AuditEvent::where('action', 'pricing.estimate.created')
        ->where('auditable_id', $estimate->id)
        ->first();

    expect($audit)->not->toBeNull()
        ->and($audit->actor_type)->toBe('guest')
        ->and($audit->metadata['reference'])->toBe($estimate->reference);
});

test('findByReference retrieves persisted estimate with item relations', function (): void {
    $estimate = Estimate::factory()->create([
        'reference' => 'EST-TESTREF1',
        'subtotal_minor_units' => 5000,
        'total_amount_minor_units' => 5000,
    ]);

    $retrieved = $this->estimateService->findByReference('EST-TESTREF1');

    expect($retrieved)->not->toBeNull()
        ->and($retrieved->id)->toBe($estimate->id)
        ->and($retrieved->grandTotal->amountMinorUnits)->toBe(5000);
});
