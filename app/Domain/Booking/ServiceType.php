<?php

namespace App\Domain\Booking;

enum ServiceType: string
{
    case AirportPickup = 'airport_pickup';
    case AirportTransfer = 'airport_transfer';
    case Ride = 'ride';
    case Rent = 'rent';

    public function label(): string
    {
        return match ($this) {
            self::AirportPickup => 'Airport Pickup',
            self::AirportTransfer => 'Airport Transfer',
            self::Ride => 'Ride',
            self::Rent => 'Rent',
        };
    }

    public function summary(): string
    {
        return match ($this) {
            self::AirportPickup => 'Arrival airport to destination with a driver and meet-and-greet support.',
            self::AirportTransfer => 'Origin to departure airport with a driver and guided pickup timing.',
            self::Ride => 'Scheduled point-to-point or multi-stop travel with a driver.',
            self::Rent => 'Exact self-drive vehicle hire for a selected period, without a driver.',
        };
    }

    public function includesDriver(): bool
    {
        return $this !== self::Rent;
    }
}
