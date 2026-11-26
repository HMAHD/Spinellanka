<?php

declare(strict_types=1);

namespace App\Domain\Operations\Enums;

enum IncidentFinancialStatus: string
{
    case Pending = 'pending';
    case Recorded = 'recorded';
    case Settled = 'settled';
    case Waived = 'waived';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending Assessment',
            self::Recorded => 'Recorded in Incident',
            self::Settled => 'Settled via Payment Ledger',
            self::Waived => 'Waived / Discharged',
        };
    }
}
