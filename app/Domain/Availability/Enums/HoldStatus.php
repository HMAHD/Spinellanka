<?php

declare(strict_types=1);

namespace App\Domain\Availability\Enums;

enum HoldStatus: string
{
    case Active = 'active';
    case Converted = 'converted';
    case Released = 'released';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Converted => 'Converted to Booking',
            self::Released => 'Released',
            self::Expired => 'Expired',
        };
    }

    public function isActive(): bool
    {
        return $this === self::Active;
    }
}
