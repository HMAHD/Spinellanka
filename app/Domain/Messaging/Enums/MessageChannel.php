<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Enums;

enum MessageChannel: string
{
    case WhatsApp = 'whatsapp';
    case Email = 'email';

    public function label(): string
    {
        return match ($this) {
            self::WhatsApp => 'WhatsApp',
            self::Email => 'Email',
        };
    }
}

