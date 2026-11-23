<?php

namespace App\Domain\Catalog\Enums;

use Filament\Support\Contracts\HasLabel;

enum MediaType: string implements HasLabel
{
    case ExteriorFront = 'exterior_front';
    case ExteriorRear = 'exterior_rear';
    case Cabin = 'cabin';
    case Luggage = 'luggage';
    case Dashboard = 'dashboard';
    case Feature = 'feature';

    public function getLabel(): string
    {
        return match ($this) {
            self::ExteriorFront => 'Exterior (Front)',
            self::ExteriorRear => 'Exterior (Rear)',
            self::Cabin => 'Passenger Cabin',
            self::Luggage => 'Luggage Space',
            self::Dashboard => 'Dashboard / Controls',
            self::Feature => 'Special Feature',
        };
    }
}
