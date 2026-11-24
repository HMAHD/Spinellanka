<?php

namespace App\Domain\Catalog\Enums;

use Filament\Support\Contracts\HasLabel;

enum VehicleType: string implements HasLabel
{
    case LuxuryVan = 'luxury_van';
    case Sedan = 'sedan';
    case Suv = 'suv';
    case Minivan = 'minivan';
    case Compact = 'compact';

    public function getLabel(): string
    {
        return match ($this) {
            self::LuxuryVan => 'Luxury Van',
            self::Sedan => 'Sedan',
            self::Suv => 'SUV',
            self::Minivan => 'Minivan',
            self::Compact => 'Compact',
        };
    }

    public function label(): string
    {
        return $this->getLabel();
    }
}
