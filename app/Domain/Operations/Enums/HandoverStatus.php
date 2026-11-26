<?php

declare(strict_types=1);

namespace App\Domain\Operations\Enums;

enum HandoverStatus: string
{
    case Scheduled = 'scheduled';
    case DocumentsVerified = 'documents_verified';
    case ConditionCaptured = 'condition_captured';
    case SignaturesCaptured = 'signatures_captured';
    case Released = 'released';
    case ExceptionApproved = 'exception_approved';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Scheduled Appointment',
            self::DocumentsVerified => 'Documents Inspected',
            self::ConditionCaptured => 'Condition Recorded',
            self::SignaturesCaptured => 'Signatures Captured',
            self::Released => 'Vehicle Dispatched / Released',
            self::ExceptionApproved => 'Released via Authorized Exception',
            self::Completed => 'Handover Completed',
            self::Cancelled => 'Cancelled',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Cancelled], true);
    }
}
