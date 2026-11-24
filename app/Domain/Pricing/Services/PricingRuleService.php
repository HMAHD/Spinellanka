<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Services;

use App\Domain\Audit\Services\AuditService;
use App\Domain\Booking\ServiceType;
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
                'priority' => isset($data['priority']) ? (int) $data['priority'] : $rule->priority,
                'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : $rule->is_active,
                'effective_from' => $effectiveFrom,
                'effective_to' => $effectiveTo,
                'notes' => array_key_exists('notes', $data) ? $data['notes'] : $rule->notes,
                'metadata' => array_key_exists('metadata', $data) ? $data['metadata'] : $rule->metadata,
            ]);

            if (array_key_exists('components', $data)) {
                $rule->components()->delete();
                foreach ($data['components'] as $compData) {
                    $this->createComponent($rule, $compData);
                }
            }

            $this->auditService->record(
                action: 'pricing.rule.updated',
                actor: $actor,
                summary: "Updated pricing rule #{$rule->id} for {$rule->service_type->value}",
                metadata: [
                    'rule_id' => $rule->id,
                    'service_type' => $rule->service_type->value,
                    'vehicle_id' => $rule->vehicle_id,
                ],
                auditable: $rule
            );

            return $rule->load('components');
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

        // Support either amount_minor_units or amount_display (dehydration)
        $amountMinorUnits = isset($data['amount_minor_units'])
            ? (int) $data['amount_minor_units']
            : (int) round(((float) ($data['amount_display'] ?? 0)) * 100);

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
