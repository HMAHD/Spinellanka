<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Services;

use App\Domain\Booking\Enums\PromotionDiscountType;
use App\Domain\Booking\Enums\PromotionScope;
use App\Domain\Booking\Enums\PromotionStatus;
use App\Domain\Booking\ServiceType;
use App\Domain\Pricing\DTOs\PromotionResolutionResult;
use App\Models\Deal;
use App\Models\Promotion;
use Carbon\Carbon;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

class PromotionResolver
{
    /**
     * Resolve the single authoritative applicable promotion.
     * Precedence: Deal > Service > Global.
     * Returns null if no promotion applies or if ambiguity is detected.
     */
    public function resolve(
        ServiceType $serviceType,
        ?Deal $deal = null,
        ?Carbon $travelDate = null,
        string $currency = 'USD',
        ?int $requestedPromotionId = null,
    ): ?PromotionResolutionResult {
        $date = ($travelDate ?? Carbon::now('Asia/Colombo'))->setTimezone('Asia/Colombo');

        // If an explicit promotion ID is requested, validate and return it if compatible
        if ($requestedPromotionId !== null) {
            $explicit = Promotion::find($requestedPromotionId);
            if ($explicit !== null && $this->isValidCandidate($explicit, $serviceType, $deal, $date, $currency)) {
                return $this->toResult($explicit);
            }
        }

        // Scope 1: Deal-scoped promotion
        if ($deal !== null) {
            $dealPromotion = $this->resolveForScope(
                scope: PromotionScope::Deal,
                filterCallback: function (Builder $query) use ($deal): void {
                    $query->where(function (Builder $sub) use ($deal): void {
                        $sub->where('deal_id', $deal->id);
                        if ($deal->promotion_id !== null) {
                            $sub->orWhere('id', $deal->promotion_id);
                        }
                    });
                },
                date: $date,
                currency: $currency,
                context: "Deal #{$deal->id}"
            );

            if ($dealPromotion !== null) {
                return $dealPromotion;
            }
        }

        // Scope 2: Service-scoped promotion
        $servicePromotion = $this->resolveForScope(
            scope: PromotionScope::Service,
            filterCallback: function (Builder $query) use ($serviceType): void {
                $query->where('service_type', $serviceType->value);
            },
            date: $date,
            currency: $currency,
            context: "Service {$serviceType->value}"
        );

        if ($servicePromotion !== null) {
            return $servicePromotion;
        }

        // Scope 3: Global-scoped promotion
        return $this->resolveForScope(
            scope: PromotionScope::Global,
            filterCallback: function (Builder $query): void {
                $query->whereNull('service_type')->whereNull('deal_id');
            },
            date: $date,
            currency: $currency,
            context: 'Global'
        );
    }

    /**
     * @param  Closure(Builder<Promotion>): void  $filterCallback
     */
    protected function resolveForScope(
        PromotionScope $scope,
        Closure $filterCallback,
        Carbon $date,
        string $currency,
        string $context
    ): ?PromotionResolutionResult {
        $query = Promotion::query()
            ->where('status', PromotionStatus::Active)
            ->where('scope', $scope)
            ->where(function (Builder $sub) use ($date): void {
                $sub->whereNull('starts_at')->orWhere('starts_at', '<=', $date);
            })
            ->where(function (Builder $sub) use ($date): void {
                $sub->whereNull('ends_at')->orWhere('ends_at', '>', $date);
            })
            ->where(function (Builder $sub) use ($currency): void {
                $sub->where('discount_type', PromotionDiscountType::PercentageBasisPoints)
                    ->orWhere(function (Builder $fixed) use ($currency): void {
                        $fixed->where('discount_type', PromotionDiscountType::FixedAmount)
                            ->where('currency', $currency);
                    });
            });

        $filterCallback($query);

        $candidates = $query->get();

        if ($candidates->isEmpty()) {
            return null;
        }

        $highestPriority = $candidates->max('priority');
        $topCandidates = $candidates->where('priority', $highestPriority);

        if ($topCandidates->count() > 1) {
            $ids = $topCandidates->pluck('id')->join(', ');
            Log::warning("Ambiguous promotion resolution in {$context} for scope '{$scope->value}'. Overlapping promotions with priority {$highestPriority}: IDs [{$ids}]. Fallback to unpromoted standard quote.");

            return null;
        }

        /** @var Promotion $winning */
        $winning = $topCandidates->first();

        return $this->toResult($winning);
    }

    protected function isValidCandidate(
        Promotion $promotion,
        ServiceType $serviceType,
        ?Deal $deal,
        Carbon $date,
        string $currency
    ): bool {
        if ($promotion->status !== PromotionStatus::Active) {
            return false;
        }

        if ($promotion->starts_at !== null && $promotion->starts_at->gt($date)) {
            return false;
        }

        if ($promotion->ends_at !== null && $promotion->ends_at->lte($date)) {
            return false;
        }

        if ($promotion->discount_type === PromotionDiscountType::FixedAmount && $promotion->currency !== $currency) {
            return false;
        }

        return match ($promotion->scope) {
            PromotionScope::Deal => $deal !== null && ($promotion->deal_id === $deal->id || $deal->promotion_id === $promotion->id),
            PromotionScope::Service => $promotion->service_type === $serviceType,
            PromotionScope::Global => true,
        };
    }

    protected function toResult(Promotion $promotion): PromotionResolutionResult
    {
        return new PromotionResolutionResult(
            promotion: $promotion,
            name: $promotion->name,
            discountType: $promotion->discount_type,
            discountValue: $promotion->discount_value,
            currency: $promotion->currency,
            scope: $promotion->scope,
            dealId: $promotion->deal_id,
            serviceType: $promotion->service_type,
            isAmbiguous: false,
        );
    }
}
