<?php

declare(strict_types=1);

namespace App\Domain\Booking\Enums;

enum ReviewSource: string
{
    case Direct = 'direct';
    case Trustpilot = 'trustpilot';
    case Google = 'google';

    public function label(): string
    {
        return match ($this) {
            self::Direct => 'Verified Direct Review',
            self::Trustpilot => 'Trustpilot Verified Review',
            self::Google => 'Google Reviews',
        };
    }
}
