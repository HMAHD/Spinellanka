<?php

declare(strict_types=1);

use App\Domain\Booking\ServiceType;
use App\Domain\Pricing\DTOs\EstimateRequestData;
use App\Domain\Pricing\Enums\PricingComponentType;
use App\Domain\Pricing\Enums\PricingUnit;
use App\Domain\Pricing\Services\EstimateService;
use App\Domain\Pricing\Services\PricingRuleService;
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

test('pricing rule update increments version and preserves historical estimate item component references', function (): void {
    $ruleService = app(PricingRuleService::class);

    $rule = $ruleService->createRule([
        'service_type' => ServiceType::AirportPickup,
        'effective_from' => Carbon::now('Asia/Colombo')->subDays(2)->toDateTimeString(),
        'priority' => 1,
        'components' => [
            [
                'component_type' => PricingComponentType::Base,
                'unit' => PricingUnit::Flat,
                'amount_minor_units' => 4500,
            ],
        ],
    ]);

    expect($rule->version)->toBe(1);
    $initialComponentId = $rule->components->first()->id;

    // Create an estimate snapshot pointing to this rule component
    $request = new EstimateRequestData(
        serviceType: ServiceType::AirportPickup,
        serviceDate: Carbon::now('Asia/Colombo')->addDays(1)
    );
    $result = $this->pricingService->calculateEstimate($request);
    $estimate = $this->estimateService->snapshotEstimate($result, $request);

    $snapshotItem = $estimate->items->first();
    expect($snapshotItem->rule_component_id)->toBe($initialComponentId);

    // Update the rule: modify notes and components
    $updatedRule = $ruleService->updateRule($rule, [
        'notes' => 'Updated airport pickup rate',
        'components' => [
            [
                'id' => $initialComponentId,
                'component_type' => PricingComponentType::Base,
                'unit' => PricingUnit::Flat,
                'amount_minor_units' => 5000,
            ],
            [
                'component_type' => PricingComponentType::Waiting,
                'unit' => PricingUnit::PerHour,
                'amount_minor_units' => 2000,
            ],
        ],
    ]);

    // Rule version must be bumped
    expect($updatedRule->version)->toBe(2);

    // Historical estimate item's rule_component_id must NOT be nullified
    $snapshotItem->refresh();
    expect($snapshotItem->rule_component_id)->toBe($initialComponentId);
});

test('pricing rule clone creates inactive draft with all components copied', function (): void {
    $ruleService = app(PricingRuleService::class);

    $rule = $ruleService->createRule([
        'service_type' => ServiceType::Ride,
        'effective_from' => Carbon::now('Asia/Colombo')->subDays(1)->toDateTimeString(),
        'priority' => 3,
        'notes' => 'Standard ride rule',
        'components' => [
            [
                'component_type' => PricingComponentType::Base,
                'unit' => PricingUnit::Flat,
                'amount_minor_units' => 2000,
            ],
            [
                'component_type' => PricingComponentType::Distance,
                'unit' => PricingUnit::PerKm,
                'amount_minor_units' => 150,
            ],
        ],
    ]);

    $cloned = $ruleService->cloneRule($rule);

    expect($cloned->id)->not->toBe($rule->id)
        ->and($cloned->is_active)->toBeFalse()
        ->and($cloned->version)->toBe(1)
        ->and($cloned->priority)->toBe(3)
        ->and($cloned->notes)->toContain("Cloned from rule #{$rule->id}")
        ->and($cloned->components)->toHaveCount(2);

    $cloneAudit = AuditEvent::where('action', 'pricing.rule.cloned')
        ->where('auditable_id', $cloned->id)
        ->first();

    expect($cloneAudit)->not->toBeNull()
        ->and($cloneAudit->metadata['source_rule_id'])->toBe($rule->id);
});
