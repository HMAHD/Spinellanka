<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Services;

use App\Domain\Audit\Services\AuditService;
use App\Domain\Booking\ServiceType;
use App\Domain\Pricing\ValueObjects\Money;
use App\Models\EstimateItem;
use App\Models\PricingRule;
use App\Models\PricingRuleComponent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PricingRuleService
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Create a new pricing rule with components inside a database transaction.
     *
     * @param  array<string, mixed>  $data
     */
    public function createRule(array $data, ?User $actor = null): PricingRule
    {
        return DB::transaction(function () use ($data, $actor) {
            $effectiveFrom = Carbon::parse($data['effective_from'])->setTimezone('Asia/Colombo');
            $effectiveTo = isset($data['effective_to'])
                ? Carbon::parse($data['effective_to'])->setTimezone('Asia/Colombo')
                : null;

            if ($effectiveTo !== null && $effectiveTo->lte($effectiveFrom)) {
                throw new InvalidArgumentException('Effective to date must be after effective from date.');
            }

            $serviceType = $data['service_type'] instanceof ServiceType
                ? $data['service_type']
                : ServiceType::from($data['service_type']);

            $rule = PricingRule::create([
                'service_type' => $serviceType,
                'vehicle_id' => $data['vehicle_id'] ?? null,
                'version' => (int) ($data['version'] ?? 1),
                'priority' => (int) ($data['priority'] ?? 0),
                'is_active' => (bool) ($data['is_active'] ?? true),
                'effective_from' => $effectiveFrom,
                'effective_to' => $effectiveTo,
                'notes' => $data['notes'] ?? null,
                'metadata' => $data['metadata'] ?? null,
                'created_by' => $actor !== null ? $actor->id : auth()->id(),
            ]);

            if (! empty($data['components'])) {
                foreach ($data['components'] as $compData) {
                    $this->createComponent($rule, $compData);
                }
            }

            $this->auditService->record(
                action: 'pricing.rule.created',
                actor: $actor,
                summary: "Created pricing rule #{$rule->id} for {$rule->service_type->value}",
                metadata: [
                    'rule_id' => $rule->id,
                    'service_type' => $rule->service_type->value,
                    'vehicle_id' => $rule->vehicle_id,
                    'effective_from' => $effectiveFrom->toIso8601String(),
                    'effective_to' => $effectiveTo?->toIso8601String(),
                    'component_count' => count($data['components'] ?? []),
                ],
                auditable: $rule
            );

            return $rule->load('components');
        });
    }

    /**
     * Update an existing rule (cloning or updating components within transaction).
     *
     * @param  array<string, mixed>  $data
     */
    public function updateRule(PricingRule $rule, array $data, ?User $actor = null): PricingRule
    {
        return DB::transaction(function () use ($rule, $data, $actor) {
            $effectiveFrom = isset($data['effective_from'])
                ? Carbon::parse($data['effective_from'])->setTimezone('Asia/Colombo')
                : $rule->effective_from;

            $effectiveTo = isset($data['effective_to'])
                ? Carbon::parse($data['effective_to'])->setTimezone('Asia/Colombo')
                : $rule->effective_to;

            if ($effectiveTo !== null && $effectiveTo->lte($effectiveFrom)) {
                throw new InvalidArgumentException('Effective to date must be after effective from date.');
            }

            $rule->update([
                'service_type' => isset($data['service_type'])
                    ? ($data['service_type'] instanceof ServiceType ? $data['service_type'] : ServiceType::from($data['service_type']))
                    : $rule->service_type,
                'vehicle_id' => array_key_exists('vehicle_id', $data) ? $data['vehicle_id'] : $rule->vehicle_id,
                'version' => $rule->version + 1,
                'priority' => isset($data['priority']) ? (int) $data['priority'] : $rule->priority,
                'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : $rule->is_active,
                'effective_from' => $effectiveFrom,
                'effective_to' => $effectiveTo,
                'notes' => array_key_exists('notes', $data) ? $data['notes'] : $rule->notes,
                'metadata' => array_key_exists('metadata', $data) ? $data['metadata'] : $rule->metadata,
            ]);

            if (array_key_exists('components', $data)) {
                $keepIds = [];
                foreach ($data['components'] as $compData) {
                    if (! empty($compData['id'])) {
                        $comp = $rule->components()->find($compData['id']);
                        if ($comp !== null) {
                            $comp->update([
                                'component_type' => $compData['component_type'],
                                'unit' => $compData['unit'],
                                'amount_minor_units' => isset($compData['amount_minor_units'])
                                    ? (int) $compData['amount_minor_units']
                                    : (is_string($compData['amount_display'] ?? null)
                                        ? Money::ofDollars((string) $compData['amount_display'])->amountMinorUnits
                                        : (int) round(((float) ($compData['amount_display'] ?? 0)) * 100)),
                                'currency' => $compData['currency'] ?? 'USD',
                                'min_units' => isset($compData['min_units']) ? (float) $compData['min_units'] : null,
                                'max_units' => isset($compData['max_units']) ? (float) $compData['max_units'] : null,
                                'is_taxable' => (bool) ($compData['is_taxable'] ?? false),
                                'is_deposit' => (bool) ($compData['is_deposit'] ?? false),
                                'metadata' => $compData['metadata'] ?? null,
                            ]);
                            $keepIds[] = $comp->id;

                            continue;
                        }
                    }
                    $newComp = $this->createComponent($rule, $compData);
                    $keepIds[] = $newComp->id;
                }

                // Delete only components not retained that are not referenced by historical estimate items
                $rule->components()->whereNotIn('id', $keepIds)->each(function (PricingRuleComponent $comp): void {
                    if (! EstimateItem::where('rule_component_id', $comp->id)->exists()) {
                        $comp->delete();
                    }
                });
            }

            $this->auditService->record(
                action: 'pricing.rule.updated',
                actor: $actor,
                summary: "Updated pricing rule #{$rule->id} for {$rule->service_type->value} (v{$rule->version})",
                metadata: [
                    'rule_id' => $rule->id,
                    'version' => $rule->version,
                    'service_type' => $rule->service_type->value,
                    'vehicle_id' => $rule->vehicle_id,
                ],
                auditable: $rule
            );

            return $rule->load('components');
        });
    }

    /**
     * Clone an existing pricing rule and its components into a new inactive draft rule.
     */
    public function cloneRule(PricingRule $rule, ?User $actor = null): PricingRule
    {
        return DB::transaction(function () use ($rule, $actor) {
            $clonedRule = PricingRule::create([
                'service_type' => $rule->service_type,
                'vehicle_id' => $rule->vehicle_id,
                'version' => 1,
                'priority' => $rule->priority,
                'is_active' => false,
                'effective_from' => $rule->effective_from,
                'effective_to' => $rule->effective_to,
                'notes' => 'Cloned from rule #'.$rule->id.($rule->notes ? ': '.$rule->notes : ''),
                'metadata' => $rule->metadata,
                'created_by' => $actor !== null ? $actor->id : auth()->id(),
            ]);

            foreach ($rule->components as $component) {
                PricingRuleComponent::create([
                    'pricing_rule_id' => $clonedRule->id,
                    'component_type' => $component->component_type,
                    'unit' => $component->unit,
                    'amount_minor_units' => $component->amount_minor_units,
                    'currency' => $component->currency,
                    'min_units' => $component->min_units,
                    'max_units' => $component->max_units,
                    'is_taxable' => $component->is_taxable,
                    'is_deposit' => $component->is_deposit,
                    'metadata' => $component->metadata,
                ]);
            }

            $this->auditService->record(
                action: 'pricing.rule.cloned',
                actor: $actor,
                summary: "Cloned pricing rule #{$rule->id} into #{$clonedRule->id}",
                metadata: [
                    'source_rule_id' => $rule->id,
                    'new_rule_id' => $clonedRule->id,
                    'service_type' => $clonedRule->service_type->value,
                ],
                auditable: $clonedRule
            );

            return $clonedRule->load('components');
        });
    }

    /**
     * Deactivate a pricing rule.
     */
    public function deactivateRule(PricingRule $rule, ?User $actor = null): PricingRule
    {
        return DB::transaction(function () use ($rule, $actor) {
            $rule->update(['is_active' => false]);

            $this->auditService->record(
                action: 'pricing.rule.deactivated',
                actor: $actor,
                summary: "Deactivated pricing rule #{$rule->id}",
                metadata: [
                    'rule_id' => $rule->id,
                    'service_type' => $rule->service_type->value,
                ],
                auditable: $rule
            );

            return $rule;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function createComponent(PricingRule $rule, array $data): PricingRuleComponent
    {
        $isDeposit = (bool) ($data['is_deposit'] ?? false);
        $isTaxable = (bool) ($data['is_taxable'] ?? false);

        if ($isDeposit && $isTaxable) {
            throw new InvalidArgumentException('A refundable security deposit cannot be marked as taxable.');
        }

        // Support either amount_minor_units or amount_display (dehydration) without floating-point conversion
        $amountMinorUnits = isset($data['amount_minor_units'])
            ? (int) $data['amount_minor_units']
            : (is_string($data['amount_display'] ?? null)
                ? Money::ofDollars((string) $data['amount_display'])->amountMinorUnits
                : (int) round(((float) ($data['amount_display'] ?? 0)) * 100));

        return PricingRuleComponent::create([
            'pricing_rule_id' => $rule->id,
            'component_type' => $data['component_type'],
            'unit' => $data['unit'],
            'amount_minor_units' => $amountMinorUnits,
            'currency' => $data['currency'] ?? 'USD',
            'min_units' => isset($data['min_units']) ? (float) $data['min_units'] : null,
            'max_units' => isset($data['max_units']) ? (float) $data['max_units'] : null,
            'is_taxable' => $isTaxable,
            'is_deposit' => $isDeposit,
            'metadata' => $data['metadata'] ?? null,
        ]);
    }
}
