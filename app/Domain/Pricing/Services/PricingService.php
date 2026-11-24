<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Services;

use App\Domain\Booking\ServiceType;
use App\Domain\Pricing\DTOs\EstimateItemData;
use App\Domain\Pricing\DTOs\EstimateRequestData;
use App\Domain\Pricing\DTOs\EstimateResult;
use App\Domain\Pricing\Enums\PricingComponentType;
use App\Domain\Pricing\Enums\PricingUnit;
use App\Domain\Pricing\ValueObjects\Money;
use App\Models\PricingRule;
use App\Models\Vehicle;
use Carbon\Carbon;

class PricingService
{
    public function __construct(
        protected ExchangeRateService $exchangeRateService
    ) {}

    /**
     * Resolve the authoritative pricing rule for a service type, vehicle, and date.
     * Precedence: Vehicle-Specific Match > Service-Wide Match > Highest Priority > Newest ID.
     */
    public function resolveRule(
        ServiceType $serviceType,
        ?Vehicle $vehicle = null,
        ?Carbon $serviceDate = null
    ): ?PricingRule {
        $date = ($serviceDate ?? Carbon::now('Asia/Colombo'))->setTimezone('Asia/Colombo');

        // Check vehicle eligibility first if vehicle provided
        if ($vehicle !== null && ! $vehicle->isEligibleForService($serviceType)) {
            return null;
        }

        $baseQuery = PricingRule::query()
            ->with('components')
            ->where('service_type', $serviceType->value)
            ->where('is_active', true)
            ->where('effective_from', '<=', $date)
            ->where(function ($q) use ($date): void {
                $q->whereNull('effective_to')
                    ->orWhere('effective_to', '>', $date);
            });

        // 1. Vehicle-specific rule check
        if ($vehicle !== null) {
            $vehicleRule = (clone $baseQuery)
                ->where('vehicle_id', $vehicle->id)
                ->orderByDesc('priority')
                ->orderByDesc('id')
                ->first();

            if ($vehicleRule !== null) {
                return $vehicleRule;
            }
        }

        // 2. Service-wide default rule fallback
        return (clone $baseQuery)
            ->whereNull('vehicle_id')
            ->orderByDesc('priority')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Calculate an itemized estimate.
     * Evaluates input sufficiency and degrades transparently to Custom Quote when data is missing.
     */
    public function calculateEstimate(EstimateRequestData $request): EstimateResult
    {
        $vehicle = $request->vehicleId !== null ? Vehicle::find($request->vehicleId) : null;

        if ($request->vehicleId !== null && $vehicle === null) {
            return EstimateResult::customQuote('Selected vehicle not found in catalog.');
        }

        if ($vehicle !== null && ! $vehicle->isEligibleForService($request->serviceType)) {
            return EstimateResult::customQuote("Vehicle '{$vehicle->public_name}' is not eligible for {$request->serviceType->label()}.");
        }

        $rule = $this->resolveRule($request->serviceType, $vehicle, $request->serviceDate);
        if ($rule === null) {
            return EstimateResult::customQuote("No active pricing rule configured for {$request->serviceType->label()}.");
        }

        if ($rule->components->isEmpty()) {
            return EstimateResult::customQuote("Pricing rule #{$rule->id} has no defined pricing components.", $rule->id, $rule->version);
        }

        // Evaluate input sufficiency across all components in the matched rule
        foreach ($rule->components as $component) {
            $sufficiencyCheck = $this->checkInputSufficiency($component->unit, $request);
            if (! $sufficiencyCheck['sufficient']) {
                return EstimateResult::customQuote($sufficiencyCheck['reason'], $rule->id, $rule->version);
            }
        }

        // Calculation pipeline
        $subtotal = Money::zero();
        $taxableBase = Money::zero();
        $depositTotal = Money::zero();
        $taxTotal = Money::zero();
        $items = [];
        $sortOrder = 1;

        // Pass 1: Base, distance, duration, stops, waiting, delivery, deposits, extras
        foreach ($rule->components as $component) {
            if ($component->component_type === PricingComponentType::Tax || $component->component_type === PricingComponentType::Discount) {
                continue;
            }

            $quantity = $this->resolveQuantity($component->unit, $request);
            $unitPrice = $component->money;
            $lineTotal = $component->unit === PricingUnit::Flat
                ? $unitPrice
                : Money::of((int) round($unitPrice->amountMinorUnits * $quantity), $unitPrice->currency);

            $isDeposit = $component->is_deposit;

            if ($isDeposit) {
                $depositTotal = $depositTotal->add($lineTotal);
            } else {
                $subtotal = $subtotal->add($lineTotal);
                if ($component->is_taxable) {
                    $taxableBase = $taxableBase->add($lineTotal);
                }
            }

            $items[] = new EstimateItemData(
                componentType: $component->component_type,
                description: $component->component_type->label(),
                unit: $component->unit,
                quantity: $quantity,
                unitPrice: $unitPrice,
                totalAmount: $lineTotal,
                sortOrder: $sortOrder++,
                isDeposit: $isDeposit,
                isTax: false,
                ruleComponentId: $component->id
            );
        }

        // Pass 2: Discounts (reduce subtotal and taxable base)
        foreach ($rule->components as $component) {
            if ($component->component_type !== PricingComponentType::Discount) {
                continue;
            }

            $discountAmount = $component->unit === PricingUnit::Percentage
                ? $subtotal->multiplyByBasisPoints($component->amount_minor_units)
                : $component->money;

            // Cap discount at subtotal
            if ($discountAmount->isGreaterThan($subtotal)) {
                $discountAmount = $subtotal;
            }

            $subtotal = $subtotal->subtract($discountAmount);
            if ($discountAmount->isGreaterThan($taxableBase)) {
                $taxableBase = Money::zero();
            } else {
                $taxableBase = $taxableBase->subtract($discountAmount);
            }

            $items[] = new EstimateItemData(
                componentType: PricingComponentType::Discount,
                description: $component->component_type->label(),
                unit: $component->unit,
                quantity: 1.00,
                unitPrice: $discountAmount,
                totalAmount: $discountAmount,
                sortOrder: $sortOrder++,
                isDeposit: false,
                isTax: false,
                ruleComponentId: $component->id
            );
        }

        // Pass 3: Taxes (calculated on taxable base)
        foreach ($rule->components as $component) {
            if ($component->component_type !== PricingComponentType::Tax) {
                continue;
            }

            $taxAmount = $component->unit === PricingUnit::Percentage
                ? $taxableBase->multiplyByBasisPoints($component->amount_minor_units)
                : $component->money;

            $taxTotal = $taxTotal->add($taxAmount);

            $items[] = new EstimateItemData(
                componentType: PricingComponentType::Tax,
                description: $component->component_type->label(),
                unit: $component->unit,
                quantity: 1.00,
                unitPrice: $taxAmount,
                totalAmount: $taxAmount,
                sortOrder: $sortOrder++,
                isDeposit: false,
                isTax: true,
                ruleComponentId: $component->id
            );
        }

        // Grand Total = Subtotal (Service) + Tax + Deposit (Segregated)
        $grandTotal = $subtotal->add($taxTotal)->add($depositTotal);

        // Approximate LKR conversion via ExchangeRateService
        $approximateLkr = $this->exchangeRateService->approximateLkr($grandTotal, $request->serviceDate);
        $latestExchangeBasis = $this->exchangeRateService->getLatestRate($request->serviceDate);

        return EstimateResult::calculated(
            subtotal: $subtotal,
            taxTotal: $taxTotal,
            depositTotal: $depositTotal,
            grandTotal: $grandTotal,
            approximateLkr: $approximateLkr,
            pricingRuleId: $rule->id,
            ruleVersion: $rule->version,
            exchangeRateBasisId: $latestExchangeBasis?->id,
            items: $items
        );
    }

    /**
     * @return array{sufficient: bool, reason: string|null}
     */
    protected function checkInputSufficiency(PricingUnit $unit, EstimateRequestData $request): array
    {
        return match ($unit) {
            PricingUnit::PerKm => ($request->distanceKm === null || $request->distanceKm <= 0)
                ? ['sufficient' => false, 'reason' => 'Distance in kilometers is required for distance-based pricing.']
                : ['sufficient' => true, 'reason' => null],
            PricingUnit::PerHour => ($request->durationHours === null || $request->durationHours <= 0)
                ? ['sufficient' => false, 'reason' => 'Duration in hours is required for hourly-based pricing.']
                : ['sufficient' => true, 'reason' => null],
            PricingUnit::PerDay => ($request->rentalDays === null || $request->rentalDays <= 0)
                ? ['sufficient' => false, 'reason' => 'Rental duration in 24-hour days is required for daily hire pricing.']
                : ['sufficient' => true, 'reason' => null],
            PricingUnit::PerStop => ($request->stopCount === null)
                ? ['sufficient' => false, 'reason' => 'Stop count is required for stop-based pricing.']
                : ['sufficient' => true, 'reason' => null],
            default => ['sufficient' => true, 'reason' => null],
        };
    }

    protected function resolveQuantity(PricingUnit $unit, EstimateRequestData $request): float
    {
        return match ($unit) {
            PricingUnit::Flat => 1.00,
            PricingUnit::PerKm => (float) ($request->distanceKm ?? 1.00),
            PricingUnit::PerHour => (float) ($request->durationHours ?? 1.00),
            PricingUnit::PerDay => (float) ($request->rentalDays ?? 1.00),
            PricingUnit::PerStop => (float) ($request->stopCount ?? 0.00),
            PricingUnit::Percentage => 1.00,
        };
    }
}
