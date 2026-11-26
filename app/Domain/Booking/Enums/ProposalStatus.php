<?php

declare(strict_types=1);

namespace App\Domain\Booking\Enums;

enum ProposalStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Superseded = 'superseded';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Sent => 'Sent to Guest',
            self::Accepted => 'Accepted',
            self::Declined => 'Declined',
            self::Superseded => 'Superseded by Newer Version',
            self::Expired => 'Expired',
        };
    }

    public function isActionable(): bool
    {
        return $this === self::Sent;
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Accepted, self::Declined, self::Superseded, self::Expired], true);
    }
}
