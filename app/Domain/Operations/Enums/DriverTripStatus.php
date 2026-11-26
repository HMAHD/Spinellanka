<?php

declare(strict_types=1);

namespace App\Domain\Operations\Enums;

enum DriverTripStatus: string
{
    case Assigned = 'assigned';
    case Acknowledged = 'acknowledged';
    case EnRoute = 'en_route';
    case Arrived = 'arrived';
    case Started = 'started';
    case Completed = 'completed';
    case Superseded = 'superseded';
    case Cancelled = 'cancelled';

    public function order(): int
    {
        return match ($this) {
            self::Assigned => 1,
            self::Acknowledged => 2,
            self::EnRoute => 3,
            self::Arrived => 4,
            self::Started => 5,
            self::Completed => 6,
            self::Superseded, self::Cancelled => 99,
        };
    }

    /**
     * @return array<int, self>
     */
    public function allowedNextStates(): array
    {
        return match ($this) {
            self::Assigned => [self::Acknowledged, self::Cancelled],
            self::Acknowledged => [self::EnRoute, self::Cancelled],
            self::EnRoute => [self::Arrived, self::Cancelled],
            self::Arrived => [self::Started, self::Cancelled],
            self::Started => [self::Completed],
            self::Completed, self::Superseded, self::Cancelled => [],
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Assigned => 'Assigned',
            self::Acknowledged => 'Acknowledged',
            self::EnRoute => 'En Route',
            self::Arrived => 'Arrived at Pickup',
            self::Started => 'Trip Started',
            self::Completed => 'Completed',
            self::Superseded => 'Superseded',
            self::Cancelled => 'Cancelled',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Assigned => 'heroicon-o-bell',
            self::Acknowledged => 'heroicon-o-hand-thumb-up',
            self::EnRoute => 'heroicon-o-truck',
            self::Arrived => 'heroicon-o-map-pin',
            self::Started => 'heroicon-o-play',
            self::Completed => 'heroicon-o-check-badge',
            self::Superseded => 'heroicon-o-arrow-path',
            self::Cancelled => 'heroicon-o-x-circle',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Superseded, self::Cancelled], true);
    }
}
