<?php

declare(strict_types=1);

namespace App\Domain\Booking\Enums;

enum BookingSource: string
{
    case Web = 'web';
    case ManualPhone = 'manual_phone';
    case ManualWhatsApp = 'manual_whatsapp';
    case ManualWalkin = 'manual_walkin';

    public function label(): string
    {
        return match ($this) {
            self::Web => 'Public Website',
            self::ManualPhone => 'Manual / Phone',
            self::ManualWhatsApp => 'Manual / WhatsApp',
            self::ManualWalkin => 'Manual / Walk-in',
        };
    }

    public function isManual(): bool
    {
        return $this !== self::Web;
    }
}

