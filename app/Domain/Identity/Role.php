<?php

namespace App\Domain\Identity;

enum Role: string
{
    case OwnerAdmin = 'Owner/Admin';
    case Staff = 'Staff';

    public function label(): string
    {
        return match ($this) {
            self::OwnerAdmin => 'Owner / Admin',
            self::Staff => 'Staff Operations',
        };
    }
}
