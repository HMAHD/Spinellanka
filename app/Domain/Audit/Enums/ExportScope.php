<?php

declare(strict_types=1);

namespace App\Domain\Audit\Enums;

enum ExportScope: string
{
    case CustomerBookingHistory = 'customer_booking_history';
    case FinancialRecords = 'financial_records';
    case OperationsAudit = 'operations_audit';

    public function label(): string
    {
        return match ($this) {
            self::CustomerBookingHistory => 'Traveler Bookings & Profiles',
            self::FinancialRecords => 'Transactions & Receipts Ledger',
            self::OperationsAudit => 'Complete Operations Audit Trail',
        };
    }

    public function requiredPermission(): string
    {
        return match ($this) {
            self::CustomerBookingHistory => 'export.customer_data',
            self::FinancialRecords => 'export.financial_data',
            self::OperationsAudit => 'audit.export',
        };
    }
}
