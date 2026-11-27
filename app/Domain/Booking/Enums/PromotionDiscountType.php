<?php

declare(strict_types=1);

namespace App\Domain\Booking\Enums;

enum PromotionDiscountType: string
{
    case FixedAmount = 'fixed_amount';
    case PercentageBasisPoints = 'percentage_basis_points';

    public function label(): string
    {
        return match ($this) {
            self::FixedAmount => 'Fixed Amount Discount',
            self::PercentageBasisPoints => 'Percentage Discount',
        };
    }
}
