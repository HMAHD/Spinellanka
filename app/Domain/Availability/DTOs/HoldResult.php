<?php

declare(strict_types=1);

namespace App\Domain\Availability\DTOs;

use App\Models\BookingHold;

class HoldResult
{
    public function __construct(
        public BookingHold $hold,
        public string $plainToken,
    ) {}
}
