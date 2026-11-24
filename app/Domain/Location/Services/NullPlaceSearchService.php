<?php

declare(strict_types=1);

namespace App\Domain\Location\Services;

use App\Domain\Location\Contracts\PlaceSearchContract;
use App\Domain\Location\DTOs\LocationCoordinate;
use App\Domain\Location\DTOs\PlaceSearchResult;

class NullPlaceSearchService implements PlaceSearchContract
{
    /**
     * Standard destination database for Sri Lanka airport transit testing and fallback.
     *
     * @var array<string, array{name: string, region: string, lat: float, lng: float}>
     */
    protected array $knownPlaces = [
        'cmb_airport' => [
            'name' => 'Bandaranaike International Airport (CMB)',
            'region' => 'Katunayake, Western Province',
            'lat' => 7.1808,
            'lng' => 79.8841,
        ],
        'colombo_fort' => [
            'name' => 'Colombo Fort / Galle Face',
            'region' => 'Colombo 01, Western Province',
            'lat' => 6.9344,
            'lng' => 79.8428,
        ],
        'galle_fort' => [
            'name' => 'Galle Fort Heritage Area',
            'region' => 'Galle, Southern Province',
            'lat' => 6.0329,
            'lng' => 80.2168,
        ],
        'kandy_city' => [
            'name' => 'Kandy City Center / Temple of the Tooth',
            'region' => 'Kandy, Central Province',
            'lat' => 7.2906,
            'lng' => 80.6337,
        ],
        'mirissa_beach' => [
            'name' => 'Mirissa Beach',
            'region' => 'Mirissa, Southern Province',
            'lat' => 5.9482,
            'lng' => 80.4578,
        ],
        'sigiriya_rock' => [
            'name' => 'Sigiriya Ancient Rock Fortress',
            'region' => 'Dambulla, Central Province',
            'lat' => 7.9570,
            'lng' => 80.7603,
        ],
    ];

    public function search(string $query, array $options = []): array
    {
        $normalized = strtolower(trim($query));
        if ($normalized === '') {
            return [];
        }

        $results = [];
        foreach ($this->knownPlaces as $id => $place) {
            if (str_contains(strtolower($place['name']), $normalized) || str_contains(strtolower($place['region']), $normalized)) {
                $results[] = new PlaceSearchResult(
                    placeId: $id,
                    formattedAddress: "{$place['name']}, {$place['region']}, Sri Lanka",
                    coordinate: new LocationCoordinate($place['lat'], $place['lng']),
                    mainText: $place['name'],
                    secondaryText: "{$place['region']}, Sri Lanka"
                );
            }
        }

        return $results;
    }

    public function getDetails(string $placeId): ?PlaceSearchResult
    {
        if (! isset($this->knownPlaces[$placeId])) {
            return null;
        }

        $place = $this->knownPlaces[$placeId];

        return new PlaceSearchResult(
            placeId: $placeId,
            formattedAddress: "{$place['name']}, {$place['region']}, Sri Lanka",
            coordinate: new LocationCoordinate($place['lat'], $place['lng']),
            mainText: $place['name'],
            secondaryText: "{$place['region']}, Sri Lanka"
        );
    }
}

