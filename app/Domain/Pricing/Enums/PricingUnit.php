<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Enums;

enum PricingUnit: string
{
    case Flat = 'flat';
    case PerKm = 'per_km';
    case PerHour = 'per_hour';
    case PerDay = 'per_day';
    case PerStop = 'per_stop';
    case Percentage = 'percentage';

    public function label(): string
    {
        return match ($this) {
            self::Flat => 'Flat Amount',
            self::PerKm => 'Per Kilometer',
            self::PerHour => 'Per Hour',
            self::PerDay => 'Per 24-Hour Day',
            self::PerStop => 'Per Additional Stop',
            self::Percentage => 'Percentage (Basis Points)',
        };
    }
}
