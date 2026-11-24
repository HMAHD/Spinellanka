<?php

declare(strict_types=1);

namespace App\Domain\Availability\Enums;

enum ScheduleBlockStatus: string
{
    case Active = 'active';
    case Released = 'released';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
    case Superseded = 'superseded';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Released => 'Released',
            self::Expired => 'Expired',
            self::Cancelled => 'Cancelled',
            self::Superseded => 'Superseded',
        };
    }

    public function isActive(): bool
    {
        return $this === self::Active;
    }
}
