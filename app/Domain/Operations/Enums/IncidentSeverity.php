<?php

declare(strict_types=1);

namespace App\Domain\Operations\Enums;

enum IncidentSeverity: string
{
    case Minor = 'minor';
    case Moderate = 'moderate';
    case Severe = 'severe';
    case Critical = 'critical';

    public function label(): string
    {
        return match ($this) {
            self::Minor => 'Minor (Cosmetic / No disruption)',
            self::Moderate => 'Moderate (Repairable / Brief delay)',
            self::Severe => 'Severe (Towing required / Police involved)',
            self::Critical => 'Critical (Injury / Total loss / Major legal)',
        };
    }
}
