<?php

declare(strict_types=1);

namespace App\Domain\Operations\Enums;

enum ConditionAttachmentCategory: string
{
    case Odometer = 'odometer';
    case FuelGauge = 'fuel_gauge';
    case ExteriorScratch = 'exterior_scratch';
    case ExteriorDent = 'exterior_dent';
    case InteriorStain = 'interior_stain';
    case Windscreen = 'windscreen';
    case SpareWheel = 'spare_wheel';
    case Tools = 'tools';
    case OtherCondition = 'other_condition';

    public function label(): string
    {
        return match ($this) {
            self::Odometer => 'Odometer Cluster',
            self::FuelGauge => 'Fuel Gauge Display',
            self::ExteriorScratch => 'Exterior Scratch',
            self::ExteriorDent => 'Exterior Dent / Body Damage',
            self::InteriorStain => 'Interior Upholstery / Stain',
            self::Windscreen => 'Windscreen / Glass',
            self::SpareWheel => 'Spare Wheel Condition',
            self::Tools => 'Tool Kit & Jack',
            self::OtherCondition => 'Other Condition Detail',
        };
    }
}
