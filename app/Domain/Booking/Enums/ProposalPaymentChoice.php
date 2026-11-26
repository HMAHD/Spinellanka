<?php

declare(strict_types=1);

namespace App\Domain\Booking\Enums;

enum ProposalPaymentChoice: string
{
    case FullOnly = 'full_only';
    case DepositOnly = 'deposit_only';
    case FullOrDeposit = 'full_or_deposit';
    case PayLaterOnly = 'pay_later_only';
    case FullOrPayLater = 'full_or_pay_later';

    public function label(): string
    {
        return match ($this) {
            self::FullOnly => 'Full Payment Only',
            self::DepositOnly => 'Security Deposit Only',
            self::FullOrDeposit => 'Pay in Full or Deposit',
            self::PayLaterOnly => 'Pay Later / On Arrival Only',
            self::FullOrPayLater => 'Pay in Full or Pay Later',
        };
    }

    /**
     * @return array<int, PaymentOption>
     */
    public function allowedOptions(): array
    {
        return match ($this) {
            self::FullOnly => [PaymentOption::Full],
            self::DepositOnly => [PaymentOption::Deposit],
            self::FullOrDeposit => [PaymentOption::Full, PaymentOption::Deposit],
            self::PayLaterOnly => [PaymentOption::PayLater],
            self::FullOrPayLater => [PaymentOption::Full, PaymentOption::PayLater],
        };
    }

    public function allows(PaymentOption $option): bool
    {
        return in_array($option, $this->allowedOptions(), true);
    }
}
