<?php

declare(strict_types=1);

namespace App\Domain\Booking\Enums;

enum ConfirmationChannel: string
{
    case WhatsApp = 'whatsapp';
    case Phone = 'phone';
    case Email = 'email';

    public function label(): string
    {
        return match ($this) {
            self::WhatsApp => 'WhatsApp',
            self::Phone => 'Phone Call',
            self::Email => 'Email',
        };
    }
}
