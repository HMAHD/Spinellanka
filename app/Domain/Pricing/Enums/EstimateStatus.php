<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Enums;

enum EstimateStatus: string
{
    case Calculated = 'calculated';
    case CustomQuoteRequired = 'custom_quote_required';
    case Expired = 'expired';
    case Superseded = 'superseded';

    public function label(): string
    {
        return match ($this) {
            self::Calculated => 'Calculated Estimate',
            self::CustomQuoteRequired => 'Custom Quote Required',
            self::Expired => 'Expired',
            self::Superseded => 'Superseded',
        };
    }
}
