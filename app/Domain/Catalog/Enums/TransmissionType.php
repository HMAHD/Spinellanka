<?php

namespace App\Domain\Catalog\Enums;

use Filament\Support\Contracts\HasLabel;

enum TransmissionType: string implements HasLabel
{
    case Automatic = 'automatic';
    case Manual = 'manual';

    public function getLabel(): string
    {
        return match ($this) {
            self::Automatic => 'Automatic',
            self::Manual => 'Manual',
        };
    }
}
