<?php

declare(strict_types=1);

namespace App\Domain\Location\Contracts;

use App\Domain\Location\DTOs\PlaceSearchResult;

interface PlaceSearchContract
{
    /**
     * @param  array<string, mixed>  $options
     * @return array<int, PlaceSearchResult>
     */
    public function search(string $query, array $options = []): array;

    public function getDetails(string $placeId): ?PlaceSearchResult;
}
