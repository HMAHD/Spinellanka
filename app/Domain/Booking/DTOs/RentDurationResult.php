<?php

declare(strict_types=1);

namespace App\Domain\Booking\DTOs;

final class RentDurationResult
{
    public function __construct(
        public readonly int $days,
        public readonly int $extraHours,
        public readonly int $totalMinutes,
        public readonly bool $isWithinGracePeriod,
        public readonly string $formattedSummary,
    ) {}
}
