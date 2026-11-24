<?php

declare(strict_types=1);

namespace App\Domain\Booking\Enums;

enum ContactType: string
{
    case Booker = 'booker';
    case LeadTraveler = 'lead_traveler';

    public function label(): string
    {
        return match ($this) {
            self::Booker => 'Booker',
            self::LeadTraveler => 'Lead Traveler',
        };
    }
}
