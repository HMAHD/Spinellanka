<?php

declare(strict_types=1);

namespace App\Domain\Booking\Enums;

enum BookingStatus: string
{
    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case ProposalSent = 'proposal_sent';
    case AwaitingPayment = 'awaiting_payment';
    case Confirmed = 'confirmed';
    case Declined = 'declined';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Submitted => 'Submitted',
            self::UnderReview => 'Under Review',
            self::ProposalSent => 'Proposal Sent',
            self::AwaitingPayment => 'Awaiting Payment',
            self::Confirmed => 'Confirmed',
            self::Declined => 'Declined',
            self::Cancelled => 'Cancelled',
            self::Expired => 'Expired',
            self::Completed => 'Completed',
        };
    }

    public function isPending(): bool
    {
        return in_array($this, [self::Submitted, self::UnderReview, self::ProposalSent], true);
    }

    public function canBeConfirmed(): bool
    {
        return in_array($this, [self::Submitted, self::UnderReview, self::ProposalSent, self::AwaitingPayment], true);
    }

    public function canBeDeclined(): bool
    {
        return in_array($this, [self::Submitted, self::UnderReview, self::ProposalSent], true);
    }

    public function canBeCancelled(): bool
    {
        return ! $this->isTerminal();
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Declined, self::Cancelled, self::Expired, self::Completed], true);
    }
}
