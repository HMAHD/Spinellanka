<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Enums;

enum RecipientType: string
{
    case Booker = 'booker';
    case LeadTraveler = 'lead_traveler';
    case Staff = 'staff';

    public function label(): string
    {
        return match ($this) {
            self::Booker => 'Booker',
            self::LeadTraveler => 'Lead Traveler',
            self::Staff => 'Operations Staff',
        };
    }
}

