<?php

declare(strict_types=1);

namespace App\Domain\Booking\Enums;

enum BookingStatus: string
{
    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case Confirmed = 'confirmed';
    case Declined = 'declined';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Submitted => 'Submitted',
            self::UnderReview => 'Under Review',
            self::Confirmed => 'Confirmed',
            self::Declined => 'Declined',
            self::Cancelled => 'Cancelled',
            self::Expired => 'Expired',
        };
    }

    public function isPending(): bool
    {
        return in_array($this, [self::Submitted, self::UnderReview], true);
    }

    public function canBeConfirmed(): bool
    {
        return $this->isPending();
    }

    public function canBeDeclined(): bool
    {
        return $this->isPending();
    }

    public function canBeCancelled(): bool
    {
        return ! $this->isTerminal();
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Declined, self::Cancelled, self::Expired], true);
    }
}
