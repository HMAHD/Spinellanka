<?php

declare(strict_types=1);

namespace App\Domain\Operations\Enums;

enum IncidentStatus: string
{
    case Reported = 'reported';
    case Investigating = 'investigating';
    case ActionRequired = 'action_required';
    case Resolved = 'resolved';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Reported => 'Reported (Under Assessment)',
            self::Investigating => 'Active Investigation',
            self::ActionRequired => 'Action Required (Police/Garage/Payment)',
            self::Resolved => 'Resolved (Repairs/Settlement Complete)',
            self::Closed => 'Closed (Audited & Archived)',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Resolved, self::Closed], true);
    }
}
