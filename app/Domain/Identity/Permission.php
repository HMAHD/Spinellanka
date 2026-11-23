<?php

namespace App\Domain\Identity;

enum Permission: string
{
    case UsersView = 'users.view';
    case UsersManage = 'users.manage';
    case RolesManage = 'roles.manage';
    case AuditView = 'audit.view';
    case AuditExport = 'audit.export';
    case FleetView = 'fleet.view';
    case FleetManage = 'fleet.manage';
    case BookingView = 'booking.view';
    case BookingReview = 'booking.review';
    case BookingConfirm = 'booking.confirm';
    case BookingOverrideConflict = 'booking.override_conflict';
    case BookingCancel = 'booking.cancel';
    case PaymentView = 'payment.view';
    case PaymentRecordOffline = 'payment.record_offline';
    case PaymentRefund = 'payment.refund';
    case IncidentView = 'incident.view';
    case IncidentManage = 'incident.manage';
    case ExportCustomerData = 'export.customer_data';
    case ExportFinancialData = 'export.financial_data';
    case ConfigManage = 'config.manage';

    public function label(): string
    {
        return match ($this) {
            self::UsersView => 'View Staff Accounts',
            self::UsersManage => 'Manage Staff Accounts',
            self::RolesManage => 'Manage Roles & Permissions',
            self::AuditView => 'View Audit Log',
            self::AuditExport => 'Export Audit Log',
            self::FleetView => 'View Fleet',
            self::FleetManage => 'Manage Fleet',
            self::BookingView => 'View Bookings',
            self::BookingReview => 'Review Requests',
            self::BookingConfirm => 'Confirm Bookings',
            self::BookingOverrideConflict => 'Authorize Conflict Override',
            self::BookingCancel => 'Cancel Bookings',
            self::PaymentView => 'View Payments',
            self::PaymentRecordOffline => 'Record Offline Payment',
            self::PaymentRefund => 'Authorize Refund',
            self::IncidentView => 'View Incidents',
            self::IncidentManage => 'Manage Incidents',
            self::ExportCustomerData => 'Export Traveler Data',
            self::ExportFinancialData => 'Export Financial Ledger',
            self::ConfigManage => 'Manage Settings & Policies',
        };
    }
}

