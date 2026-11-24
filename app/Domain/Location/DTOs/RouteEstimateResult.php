<?php

declare(strict_types=1);

namespace App\Domain\Location\DTOs;

final class RouteEstimateResult
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly float $distanceKm,
        public readonly float $durationMinutes,
        public readonly array $metadata = [],
    ) {}
}

