<?php

declare(strict_types=1);

namespace App\Domain\Pricing\DTOs;

use App\Domain\Booking\Enums\PromotionDiscountType;
use App\Domain\Booking\Enums\PromotionScope;
use App\Domain\Booking\ServiceType;
use App\Models\Promotion;

final class PromotionResolutionResult
{
    public function __construct(
        public readonly Promotion $promotion,
        public readonly string $name,
        public readonly PromotionDiscountType $discountType,
        public readonly int $discountValue,
        public readonly ?string $currency = null,
        public readonly PromotionScope $scope = PromotionScope::Global,
        public readonly ?int $dealId = null,
        public readonly ?ServiceType $serviceType = null,
        public readonly bool $isAmbiguous = false,
    ) {}
}
