<?php

namespace App\Domain\Catalog\Enums;

use Filament\Support\Contracts\HasLabel;

enum ExtraPricingType: string implements HasLabel
{
    case PerBooking = 'per_booking';
    case PerDay = 'per_day';

    public function getLabel(): string
    {
        return match ($this) {
            self::PerBooking => 'Per Booking',
            self::PerDay => 'Per Day',
        };
    }
}
