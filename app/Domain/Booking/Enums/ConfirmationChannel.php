<?php

declare(strict_types=1);

namespace App\Domain\Booking\Enums;

enum ConfirmationChannel: string
{
    case WhatsApp = 'whatsapp';
    case Phone = 'phone';
    case Email = 'email';
    case GuestPrivateLink = 'guest_private_link';
    case OnlinePayment = 'online_payment';

    public function label(): string
    {
        return match ($this) {
            self::WhatsApp => 'WhatsApp',
            self::Phone => 'Phone Call',
            self::Email => 'Email',
            self::GuestPrivateLink => 'Guest Private Link',
            self::OnlinePayment => 'Online Payment Gateway',
        };
    }
}
