<?php

declare(strict_types=1);

namespace App\Domain\Operations\Enums;

enum FuelLevel: string
{
    case Empty = 'empty';
    case Quarter = 'quarter';
    case Half = 'half';
    case ThreeQuarters = 'three_quarters';
    case Full = 'full';

    public function fraction(): float
    {
        return match ($this) {
            self::Empty => 0.0,
            self::Quarter => 0.25,
            self::Half => 0.50,
            self::ThreeQuarters => 0.75,
            self::Full => 1.0,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Empty => 'Empty (0%)',
            self::Quarter => '1/4 Tank (25%)',
            self::Half => '1/2 Tank (50%)',
            self::ThreeQuarters => '3/4 Tank (75%)',
            self::Full => 'Full Tank (100%)',
        };
    }
}
