<?php

declare(strict_types=1);

namespace App\Domain\Location\DTOs;

final class LocationCoordinate
{
    public function __construct(
        public readonly float $latitude,
        public readonly float $longitude,
    ) {}
}

