<?php

declare(strict_types=1);

namespace App\Domain\Booking\DTOs;

use App\Models\Booking;

final class BookingSubmissionResult
{
    public function __construct(
        public readonly Booking $booking,
        public readonly string $plainGuestToken,
        public readonly string $privateUrl,
    ) {}
}

