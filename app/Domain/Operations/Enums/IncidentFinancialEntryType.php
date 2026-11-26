<?php

declare(strict_types=1);

namespace App\Domain\Operations\Enums;

enum IncidentFinancialEntryType: string
{
    case CustomerDeduction = 'customer_deduction';
    case CustomerCharge = 'customer_charge';
    case ThirdPartyRecovery = 'third_party_recovery';
    case InsurancePayout = 'insurance_payout';
    case RepairCost = 'repair_cost';

    public function label(): string
    {
        return match ($this) {
            self::CustomerDeduction => 'Security Deposit Deduction',
            self::CustomerCharge => 'Direct Customer Surcharge',
            self::ThirdPartyRecovery => 'Third-Party Driver Recovery',
            self::InsurancePayout => 'Insurance Claim Settlement',
            self::RepairCost => 'Workshop Repair Expenditure',
        };
    }
}
