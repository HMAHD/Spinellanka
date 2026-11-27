<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Services;

use App\Domain\Booking\Enums\PromotionDiscountType;
use App\Domain\Booking\ServiceType;
use App\Domain\Catalog\Enums\ExtraPricingType;
use App\Domain\Catalog\Enums\VehicleStatus;
use App\Domain\Pricing\DTOs\EstimateItemData;
use App\Domain\Pricing\DTOs\EstimateRequestData;
use App\Domain\Pricing\DTOs\EstimateResult;
use App\Domain\Pricing\Enums\PricingComponentType;
use App\Domain\Pricing\Enums\PricingUnit;
use App\Domain\Pricing\ValueObjects\Money;
use App\Models\Deal;
use App\Models\Extra;
use App\Models\PricingRule;
use App\Models\PricingRuleComponent;
use App\Models\Vehicle;
use Carbon\Carbon;

class PricingService
{
    public function __construct(
        protected ExchangeRateService $exchangeRateService,
        protected ?PromotionResolver $promotionResolver = null,
    ) {
        $this->promotionResolver ??= app(PromotionResolver::class);
    }

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

        // Check vehicle eligibility and active status first if vehicle provided
        if ($vehicle !== null && (! $vehicle->isEligibleForService($serviceType) || $vehicle->status !== VehicleStatus::Active)) {
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

        if ($vehicle !== null && $vehicle->status !== VehicleStatus::Active) {
            return EstimateResult::customQuote("Vehicle '{$vehicle->public_name}' is not currently active for service.");
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
            $sufficiencyCheck = $this->checkInputSufficiency($component, $request);
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

        // Pass 1: Base, distance, duration, stops, waiting, delivery, deposits
        foreach ($rule->components as $component) {
            if ($component->component_type === PricingComponentType::Tax || $component->component_type === PricingComponentType::Discount) {
                continue;
            }

            $quantity = $this->resolveQuantity($component, $request);

            // Enforce min_units and max_units if configured on rule component
            if ($component->min_units !== null && $quantity < (float) $component->min_units) {
                $quantity = (float) $component->min_units;
            }
            if ($component->max_units !== null && (float) $component->max_units > 0 && $quantity > (float) $component->max_units) {
                $quantity = (float) $component->max_units;
            }

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
                isDiscount: false,
                ruleComponentId: $component->id
            );
        }

        // Pass 1b: Catalog Extras requested by customer
        if (! empty($request->extraIds)) {
            $extras = Extra::whereIn('id', $request->extraIds)
                ->where('is_active', true)
                ->get();

            foreach ($extras as $extra) {
                $extraQuantity = $extra->pricing_type === ExtraPricingType::PerDay
                    ? (float) ($request->rentalDays ?? 1.0)
                    : 1.0;
                $extraUnitPrice = Money::of($extra->price_minor_units, $extra->currency);
                $extraLineTotal = $extra->pricing_type === ExtraPricingType::PerDay
                    ? Money::of((int) round($extra->price_minor_units * $extraQuantity), $extra->currency)
                    : $extraUnitPrice;

                $subtotal = $subtotal->add($extraLineTotal);
                $taxableBase = $taxableBase->add($extraLineTotal);

                $items[] = new EstimateItemData(
                    componentType: PricingComponentType::Extra,
                    description: "Extra: {$extra->name}",
                    unit: $extra->pricing_type === ExtraPricingType::PerDay ? PricingUnit::PerDay : PricingUnit::Flat,
                    quantity: $extraQuantity,
                    unitPrice: $extraUnitPrice,
                    totalAmount: $extraLineTotal,
                    sortOrder: $sortOrder++,
                    isDeposit: false,
                    isTax: false,
                    isDiscount: false,
                    ruleComponentId: null
                );
            }
        }

        // Pass 2: Discounts (reduce subtotal and taxable base)
        $hasRuleDiscount = false;
        foreach ($rule->components as $component) {
            if ($component->component_type !== PricingComponentType::Discount) {
                continue;
            }

            $hasRuleDiscount = true;

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
                isDiscount: true,
                ruleComponentId: $component->id
            );
        }

        // Pass 2b: Non-stacking Promotional Discount (applied only if pricing rule has no discount component)
        $appliedPromotionResult = null;
        if (! $hasRuleDiscount && $this->promotionResolver !== null) {
            $currency = $rule->components->first()->currency;
            $deal = $request->dealId !== null ? Deal::find($request->dealId) : null;
            $appliedPromotionResult = $this->promotionResolver->resolve(
                serviceType: $request->serviceType,
                deal: $deal,
                travelDate: $request->serviceDate,
                currency: $currency,
                requestedPromotionId: $request->promotionId
            );

            if ($appliedPromotionResult !== null && $subtotal->isPositive()) {
                $promoDiscount = $appliedPromotionResult->discountType === PromotionDiscountType::PercentageBasisPoints
                    ? $subtotal->multiplyByBasisPoints($appliedPromotionResult->discountValue)
                    : Money::of($appliedPromotionResult->discountValue, $currency);

                // Cap discount at subtotal
                if ($promoDiscount->isGreaterThan($subtotal)) {
                    $promoDiscount = $subtotal;
                }

                if ($promoDiscount->isPositive()) {
                    $subtotal = $subtotal->subtract($promoDiscount);
                    if ($promoDiscount->isGreaterThan($taxableBase)) {
                        $taxableBase = Money::zero();
                    } else {
                        $taxableBase = $taxableBase->subtract($promoDiscount);
                    }

                    $items[] = new EstimateItemData(
                        componentType: PricingComponentType::Discount,
                        description: "Promotion: {$appliedPromotionResult->name}",
                        unit: $appliedPromotionResult->discountType === PromotionDiscountType::PercentageBasisPoints
                            ? PricingUnit::Percentage
                            : PricingUnit::Flat,
                        quantity: 1.00,
                        unitPrice: $promoDiscount,
                        totalAmount: $promoDiscount,
                        sortOrder: $sortOrder++,
                        isDeposit: false,
                        isTax: false,
                        isDiscount: true,
                        ruleComponentId: null
                    );
                }
            }
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
                isDiscount: false,
                ruleComponentId: $component->id
            );
        }

        // Grand Total = Subtotal (Service) + Tax + Deposit (Segregated)
        $grandTotal = $subtotal->add($taxTotal)->add($depositTotal);

        // Approximate LKR conversion via ExchangeRateService at quotation time
        $conversion = $this->exchangeRateService->approximateLkrWithBasis($grandTotal, Carbon::now('Asia/Colombo'));

        $promotionSnapshot = $appliedPromotionResult !== null ? [
            'promotion_id' => $appliedPromotionResult->promotion->id,
            'name' => $appliedPromotionResult->name,
            'scope' => $appliedPromotionResult->scope->value,
            'discount_type' => $appliedPromotionResult->discountType->value,
            'discount_value' => $appliedPromotionResult->discountValue,
            'currency' => $appliedPromotionResult->currency,
            'deal_id' => $appliedPromotionResult->dealId,
            'service_type' => $appliedPromotionResult->serviceType?->value,
        ] : null;

        return EstimateResult::calculated(
            subtotal: $subtotal,
            taxTotal: $taxTotal,
            depositTotal: $depositTotal,
            grandTotal: $grandTotal,
            approximateLkr: $conversion['approximate_lkr'],
            pricingRuleId: $rule->id,
            ruleVersion: $rule->version,
            exchangeRateBasisId: $conversion['basis']?->id,
            items: $items,
            appliedDealId: $request->dealId,
            appliedPromotionId: $appliedPromotionResult?->promotion->id,
            promotionSnapshot: $promotionSnapshot,
        );
    }

    /**
     * @return array{sufficient: bool, reason: string|null}
     */
    protected function checkInputSufficiency(PricingRuleComponent $component, EstimateRequestData $request): array
    {
        if ($component->component_type === PricingComponentType::Waiting && $component->unit === PricingUnit::PerHour) {
            return ($request->waitingHours === null || $request->waitingHours < 0)
                ? ['sufficient' => false, 'reason' => 'Waiting duration in hours is required for waiting fee calculation.']
                : ['sufficient' => true, 'reason' => null];
        }

        return match ($component->unit) {
            PricingUnit::PerKm => ($request->distanceKm === null || $request->distanceKm <= 0)
                ? ['sufficient' => false, 'reason' => 'Distance in kilometers is required for distance-based pricing.']
                : ['sufficient' => true, 'reason' => null],
            PricingUnit::PerHour => ($request->serviceType === ServiceType::Rent && ($request->rentalDays ?? 0) > 0)
                ? ['sufficient' => true, 'reason' => null]
                : (($request->durationHours === null || $request->durationHours <= 0)
                    ? ['sufficient' => false, 'reason' => 'Duration in hours is required for hourly-based pricing.']
                    : ['sufficient' => true, 'reason' => null]),
            PricingUnit::PerDay => ($request->rentalDays === null || $request->rentalDays <= 0)
                ? ['sufficient' => false, 'reason' => 'Rental duration in 24-hour days is required for daily hire pricing.']
                : ['sufficient' => true, 'reason' => null],
            PricingUnit::PerStop => ($request->stopCount === null || $request->stopCount < 0)
                ? ['sufficient' => false, 'reason' => 'Stop count is required and must be non-negative for stop-based pricing.']
                : ['sufficient' => true, 'reason' => null],
            default => ['sufficient' => true, 'reason' => null],
        };
    }

    protected function resolveQuantity(PricingRuleComponent $component, EstimateRequestData $request): float
    {
        if ($component->component_type === PricingComponentType::Waiting && $component->unit === PricingUnit::PerHour) {
            return (float) ($request->waitingHours ?? 0.0);
        }

        return match ($component->unit) {
            PricingUnit::Flat => 1.00,
            PricingUnit::PerKm => (float) ($request->distanceKm ?? 1.00),
            PricingUnit::PerHour => $request->serviceType === ServiceType::Rent
                ? (float) ($request->durationHours ?? 0.0)
                : (float) ($request->durationHours ?? 1.00),
            PricingUnit::PerDay => (float) ($request->rentalDays ?? 1.00),
            PricingUnit::PerStop => (float) ($request->stopCount ?? 0.00),
            PricingUnit::Percentage => 1.00,
        };
    }
}
