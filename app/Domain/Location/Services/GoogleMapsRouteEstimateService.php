<?php

declare(strict_types=1);

namespace App\Domain\Location\Services;

use App\Domain\Location\Contracts\RouteEstimateContract;
use App\Domain\Location\DTOs\LocationCoordinate;
use App\Domain\Location\DTOs\RouteEstimateResult;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class GoogleMapsRouteEstimateService implements RouteEstimateContract
{
    public function __construct(
        protected ?string $apiKey = null,
        protected int $timeoutSeconds = 5,
    ) {
        $this->apiKey = $apiKey ?? config('services.maps.key');
        $this->timeoutSeconds = max(1, min(10, (int) config('services.maps.timeout_seconds', $timeoutSeconds)));
    }

    /**
     * Compute route distance and duration using Google Routes API v2.
     * Returns null on missing configuration or API error so callers degrade to custom-quote / route-unavailable flows.
     */
    public function estimateRoute(
        LocationCoordinate $origin,
        LocationCoordinate $destination,
    ): ?RouteEstimateResult {
        if (empty($this->apiKey)) {
            return null;
        }

        $cacheKey = sprintf(
            'maps_route_%.4f_%.4f_%.4f_%.4f',
            $origin->latitude,
            $origin->longitude,
            $destination->latitude,
            $destination->longitude,
        );

        $cached = Cache::get($cacheKey);
        if ($cached instanceof RouteEstimateResult) {
            return $cached;
        }

        try {
            $headers = [
                'X-Goog-Api-Key' => (string) $this->apiKey,
                'X-Goog-FieldMask' => 'routes.distanceMeters,routes.duration',
            ];
            $allowedOrigin = config('services.maps.allowed_origin');
            if (is_string($allowedOrigin) && $allowedOrigin !== '') {
                $headers['Referer'] = $allowedOrigin;
            }

            $response = Http::withHeaders($headers)
                ->connectTimeout(3)
                ->timeout($this->timeoutSeconds)
                ->post('https://routes.googleapis.com/directions/v2:computeRoutes', [
                    'origin' => [
                        'location' => [
                            'latLng' => [
                                'latitude' => $origin->latitude,
                                'longitude' => $origin->longitude,
                            ],
                        ],
                    ],
                    'destination' => [
                        'location' => [
                            'latLng' => [
                                'latitude' => $destination->latitude,
                                'longitude' => $destination->longitude,
                            ],
                        ],
                    ],
                    'travelMode' => 'DRIVE',
                    'routingPreference' => 'TRAFFIC_UNAWARE',
                    'units' => 'METRIC',
                ]);

            if (! $response->successful()) {
                Log::warning('Google Routes computeRoutes returned non-2xx status; degrading to null.', [
                    'status' => $response->status(),
                ]);

                return null;
            }

            $route = $response->json('routes.0');
            if (! is_array($route) || ! isset($route['distanceMeters'], $route['duration'])) {
                return null;
            }

            $distanceKm = round(((float) $route['distanceMeters']) / 1000.0, 1);
            $durationSeconds = (float) rtrim((string) $route['duration'], 's');
            $durationMinutes = max(1.0, round($durationSeconds / 60.0, 1));

            $result = new RouteEstimateResult(
                distanceKm: $distanceKm,
                durationMinutes: $durationMinutes,
                metadata: ['provider' => 'google_routes_v2'],
            );

            Cache::put($cacheKey, $result, 900);

            return $result;
        } catch (Throwable $e) {
            Log::warning('Google Routes computeRoutes failed; degrading to null.', [
                'exception' => $this->redactSecrets($e->getMessage()),
            ]);

            return null;
        }
    }

    protected function redactSecrets(string $message): string
    {
        if (! empty($this->apiKey)) {
            $message = str_replace((string) $this->apiKey, '[REDACTED_MAPS_KEY]', $message);
        }

        return mb_substr($message, 0, 300);
    }
}
