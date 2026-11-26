<?php

declare(strict_types=1);

namespace App\Domain\Audit\Enums;

enum DataRequestStatus: string
{
    case PendingVerification = 'pending_verification';
    case Verified = 'verified';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::PendingVerification => 'Pending Email/SMS Verification',
            self::Verified => 'Subject Verified',
            self::InProgress => 'In Progress by Privacy Officer',
            self::Completed => 'Fulfilled / Completed',
            self::Rejected => 'Rejected / Exempted (e.g. Legal Hold)',
        };
    }
}
