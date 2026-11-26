<?php

declare(strict_types=1);

namespace App\Domain\Payment\Enums;

enum PaymentMethod: string
{
    case OnlineCard = 'online_card';
    case BankTransfer = 'bank_transfer';
    case Cash = 'cash';
    case CardTerminal = 'card_terminal';

    public function label(): string
    {
        return match ($this) {
            self::OnlineCard => 'Online Card',
            self::BankTransfer => 'Bank Wire / Transfer',
            self::Cash => 'Cash to Driver / Counter',
            self::CardTerminal => 'POS Card Terminal',
        };
    }

    public function isOnline(): bool
    {
        return $this === self::OnlineCard;
    }

    public function isOffline(): bool
    {
        return ! $this->isOnline();
    }
}
