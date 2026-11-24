<?php

declare(strict_types=1);

namespace App\Domain\Availability\Enums;

enum ScheduleBlockKind: string
{
    case CheckoutHold = 'checkout_hold';
    case SubmittedRequest = 'submitted_request';
    case ProposalHold = 'proposal_hold';
    case ConfirmedBooking = 'confirmed_booking';
    case InProgress = 'in_progress';
    case Handover = 'handover';
    case Return = 'return';
    case Buffer = 'buffer';
    case Maintenance = 'maintenance';
    case DriverUnavailability = 'driver_unavailability';
    case ManualBlock = 'manual_block';

    public function label(): string
    {
        return match ($this) {
            self::CheckoutHold => 'Checkout Hold',
            self::SubmittedRequest => 'Submitted Request',
            self::ProposalHold => 'Proposal Hold',
            self::ConfirmedBooking => 'Confirmed Booking',
            self::InProgress => 'In Progress',
            self::Handover => 'Handover',
            self::Return => 'Return',
            self::Buffer => 'Turnaround Buffer',
            self::Maintenance => 'Maintenance',
            self::DriverUnavailability => 'Driver Unavailability',
            self::ManualBlock => 'Manual Operational Block',
        };
    }

    public function isStaffCreatable(): bool
    {
        return match ($this) {
            self::Maintenance, self::DriverUnavailability, self::ManualBlock => true,
            default => false,
        };
    }

    public function isHold(): bool
    {
        return match ($this) {
            self::CheckoutHold, self::ProposalHold => true,
            default => false,
        };
    }
}
