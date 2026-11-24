<?php

declare(strict_types=1);

namespace App\Domain\Location\DTOs;

final class PlaceSearchResult
{
    public function __construct(
        public readonly string $placeId,
        public readonly string $formattedAddress,
        public readonly ?LocationCoordinate $coordinate = null,
        public readonly ?string $mainText = null,
        public readonly ?string $secondaryText = null,
    ) {}
}

