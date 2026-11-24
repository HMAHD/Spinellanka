<?php

declare(strict_types=1);

namespace App\Domain\Location\Services;

use App\Domain\Location\Contracts\RouteEstimateContract;
use App\Domain\Location\DTOs\LocationCoordinate;
use App\Domain\Location\DTOs\RouteEstimateResult;

class NullRouteEstimateService implements RouteEstimateContract
{
    /**
     * Estimated distances from CMB airport (~7.1808, ~79.8841) to standard hubs.
     * Uses Haversine distance with Sri Lanka road routing multiplier (~1.3x) if not explicitly matched.
     */
    public function estimateRoute(
        LocationCoordinate $origin,
        LocationCoordinate $destination,
    ): ?RouteEstimateResult {
        // Approximate distance via Haversine
        $lat1 = deg2rad($origin->latitude);
        $lng1 = deg2rad($origin->longitude);
        $lat2 = deg2rad($destination->latitude);
        $lng2 = deg2rad($destination->longitude);

        $dLat = $lat2 - $lat1;
        $dLng = $lng2 - $lng1;

        $a = sin($dLat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($dLng / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        $earthRadiusKm = 6371.0;
        $straightLineKm = $earthRadiusKm * $c;

        if ($straightLineKm < 0.1) {
            return new RouteEstimateResult(distanceKm: 5.0, durationMinutes: 15.0);
        }

        // Road distance in Sri Lanka typically 1.25x - 1.45x straight-line distance
        $roadDistanceKm = round($straightLineKm * 1.35, 1);

        // Average highway / arterial speed: 45 km/h for mixed roads
        $durationMinutes = round(($roadDistanceKm / 45.0) * 60.0);

        return new RouteEstimateResult(
            distanceKm: $roadDistanceKm,
            durationMinutes: max(30.0, $durationMinutes),
            metadata: ['provider' => 'haversine_approximation']
        );
    }
}

