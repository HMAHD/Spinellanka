<?php

declare(strict_types=1);

namespace App\Domain\Operations\Enums;

enum HandoverType: string
{
    case Collection = 'collection';
    case Return = 'return';

    public function label(): string
    {
        return match ($this) {
            self::Collection => 'Vehicle Collection / Handover',
            self::Return => 'Vehicle Return / Check-in',
        };
    }
}
