<?php

declare(strict_types=1);

namespace App\Domain\Pricing\DTOs;

use App\Domain\Pricing\Enums\EstimateStatus;
use App\Domain\Pricing\ValueObjects\Money;

final class EstimateResult
{
    /**
     * @param  array<EstimateItemData>  $items
     */
    public function __construct(
        public readonly EstimateStatus $status,
        public readonly bool $isCustomQuote,
        public readonly ?string $customQuoteReason,
        public readonly ?Money $subtotal = null,
        public readonly ?Money $taxTotal = null,
        public readonly ?Money $depositTotal = null,
        public readonly ?Money $grandTotal = null,
        public readonly ?Money $approximateLkr = null,
        public readonly ?int $pricingRuleId = null,
        public readonly ?int $ruleVersion = null,
        public readonly ?int $exchangeRateBasisId = null,
        public readonly array $items = [],
    ) {}

    /**
     * @param  array<EstimateItemData>  $items
     */
    public static function calculated(
        Money $subtotal,
        Money $taxTotal,
        Money $depositTotal,
        Money $grandTotal,
        ?Money $approximateLkr,
        int $pricingRuleId,
        int $ruleVersion,
        ?int $exchangeRateBasisId,
        array $items
    ): self {
        return new self(
            status: EstimateStatus::Calculated,
            isCustomQuote: false,
            customQuoteReason: null,
            subtotal: $subtotal,
            taxTotal: $taxTotal,
            depositTotal: $depositTotal,
            grandTotal: $grandTotal,
            approximateLkr: $approximateLkr,
            pricingRuleId: $pricingRuleId,
            ruleVersion: $ruleVersion,
            exchangeRateBasisId: $exchangeRateBasisId,
            items: $items,
        );
    }

    public static function customQuote(string $reason, ?int $pricingRuleId = null, ?int $ruleVersion = null): self
    {
        return new self(
            status: EstimateStatus::CustomQuoteRequired,
            isCustomQuote: true,
            customQuoteReason: $reason,
            subtotal: null,
            taxTotal: null,
            depositTotal: null,
            grandTotal: null,
            approximateLkr: null,
            pricingRuleId: $pricingRuleId,
            ruleVersion: $ruleVersion,
            exchangeRateBasisId: null,
            items: [],
        );
    }
}
