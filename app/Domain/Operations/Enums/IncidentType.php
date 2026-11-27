<?php

declare(strict_types=1);

namespace App\Domain\Operations\Enums;

enum IncidentType: string
{
    case Accident = 'accident';
    case Breakdown = 'breakdown';
    case Damage = 'damage';
    case Theft = 'theft';
    case TrafficViolation = 'traffic_violation';
    case CustomerDispute = 'customer_dispute';
    case ServiceDelay = 'service_delay';

    public function label(): string
    {
        return match ($this) {
            self::Accident => 'Traffic Collision / Accident',
            self::Breakdown => 'Mechanical Breakdown',
            self::Damage => 'Vehicle Physical Damage',
            self::Theft => 'Theft / Vandalism',
            self::TrafficViolation => 'Traffic Fine / Police Violation',
            self::CustomerDispute => 'Customer Dispute',
            self::ServiceDelay => 'Major Service Interruption / Delay',
        };
    }
}
