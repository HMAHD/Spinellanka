<?php

declare(strict_types=1);

namespace App\Domain\Booking\Enums;

enum PromotionScope: string
{
    case Global = 'global';
    case Service = 'service';
    case Deal = 'deal';

    public function label(): string
    {
        return match ($this) {
            self::Global => 'Global (All Services)',
            self::Service => 'Service-Specific',
            self::Deal => 'Deal-Specific',
        };
    }
}
