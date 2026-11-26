<?php

declare(strict_types=1);

namespace App\Domain\Booking\Enums;

enum PaymentOption: string
{
    case Full = 'full';
    case Deposit = 'deposit';
    case PayLater = 'pay_later';

    public function label(): string
    {
        return match ($this) {
            self::Full => 'Pay in Full Now',
            self::Deposit => 'Pay Security Deposit Now',
            self::PayLater => 'Pay on Arrival / Cash to Driver',
        };
    }

    public function requiresOnlinePayment(): bool
    {
        return $this !== self::PayLater;
    }
}
