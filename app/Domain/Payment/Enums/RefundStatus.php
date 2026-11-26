<?php

declare(strict_types=1);

namespace App\Domain\Payment\Enums;

enum RefundStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Rejected = 'rejected';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending Approval',
            self::Completed => 'Completed',
            self::Rejected => 'Rejected',
            self::Failed => 'Failed',
        };
    }
}
