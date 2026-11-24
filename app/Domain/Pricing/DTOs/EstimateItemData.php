<?php

declare(strict_types=1);

namespace App\Domain\Pricing\DTOs;

use App\Domain\Pricing\Enums\PricingComponentType;
use App\Domain\Pricing\Enums\PricingUnit;
use App\Domain\Pricing\ValueObjects\Money;

final class EstimateItemData
{
    public function __construct(
        public readonly PricingComponentType $componentType,
        public readonly string $description,
        public readonly PricingUnit $unit,
        public readonly float $quantity,
        public readonly Money $unitPrice,
        public readonly Money $totalAmount,
        public readonly int $sortOrder = 0,
        public readonly bool $isDeposit = false,
        public readonly bool $isTax = false,
        public readonly ?int $ruleComponentId = null,
    ) {}
}
