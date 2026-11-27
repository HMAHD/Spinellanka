<?php

declare(strict_types=1);

namespace App\Domain\Payment\Enums;

enum PaymentType: string
{
    case Full = 'full';
    case Deposit = 'deposit';
    case Balance = 'balance';
    case RentExtension = 'rent_extension';
    case SecurityDeposit = 'security_deposit';
    case Refund = 'refund';
    case IncidentCharge = 'incident_charge';
    case ConditionSettlement = 'condition_settlement';
    case CleaningPenalty = 'cleaning_penalty';

    public function label(): string
    {
        return match ($this) {
            self::Full => 'Full Payment',
            self::Deposit => 'Booking Deposit',
            self::Balance => 'Remaining Balance',
            self::RentExtension => 'Hire Extension',
            self::SecurityDeposit => 'Security Deposit',
            self::Refund => 'Compensating Refund',
            self::IncidentCharge => 'Incident Charge',
            self::ConditionSettlement => 'Condition Settlement',
            self::CleaningPenalty => 'Cleaning Penalty',
        };
    }
}
