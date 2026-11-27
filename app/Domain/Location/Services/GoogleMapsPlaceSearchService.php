<?php

declare(strict_types=1);

namespace App\Domain\Location\Services;

use App\Domain\Location\Contracts\PlaceSearchContract;
use App\Domain\Location\DTOs\LocationCoordinate;
use App\Domain\Location\DTOs\PlaceSearchResult;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class GoogleMapsPlaceSearchService implements PlaceSearchContract
{
    public function __construct(
        protected NullPlaceSearchService $fallback,
        protected ?string $apiKey = null,
        protected int $timeoutSeconds = 5,
    ) {
        $this->apiKey = $apiKey ?? config('services.maps.key');
        $this->timeoutSeconds = max(1, min(10, (int) config('services.maps.timeout_seconds', $timeoutSeconds)));
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<int, PlaceSearchResult>
     */
    public function search(string $query, array $options = []): array
    {
        $sanitized = $this->sanitizeQuery($query);
        if ($sanitized === '') {
            return [];
        }

        if (empty($this->apiKey)) {
            return $this->fallback->search($sanitized, $options);
        }

        $ttlSeconds = in_array(strtolower($sanitized), ['airport', 'hotel'], true) ? 86400 : 900;
        $cacheKey = 'maps_places_search_'.sha1(strtolower($sanitized));

        /** @var array<int, PlaceSearchResult>|null $cached */
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        try {
            $headers = [
                'X-Goog-Api-Key' => (string) $this->apiKey,
                'X-Goog-FieldMask' => 'places.id,places.displayName,places.formattedAddress,places.location',
            ];
            $allowedOrigin = config('services.maps.allowed_origin');
            if (is_string($allowedOrigin) && $allowedOrigin !== '') {
                $headers['Referer'] = $allowedOrigin;
            }

            $response = Http::withHeaders($headers)
                ->connectTimeout(3)
                ->timeout($this->timeoutSeconds)
                ->post('https://places.googleapis.com/v1/places:searchText', [
                    'textQuery' => $sanitized,
                    'regionCode' => 'LK',
                    'languageCode' => 'en',
                    'maxResultCount' => 6,
                ]);

            if (! $response->successful()) {
                Log::warning('Google Places searchText returned non-2xx status; degrading to fallback.', [
                    'status' => $response->status(),
                ]);

                return $this->fallback->search($sanitized, $options);
            }

            $places = $response->json('places');
            if (! is_array($places)) {
                return $this->fallback->search($sanitized, $options);
            }

            $results = [];
            foreach ($places as $place) {
                if (! is_array($place) || empty($place['id']) || empty($place['formattedAddress'])) {
                    continue;
                }

                $lat = $place['location']['latitude'] ?? null;
                $lng = $place['location']['longitude'] ?? null;
                $coordinate = ($lat !== null && $lng !== null)
                    ? new LocationCoordinate((float) $lat, (float) $lng)
                    : null;

                $results[] = new PlaceSearchResult(
                    placeId: (string) $place['id'],
                    formattedAddress: (string) $place['formattedAddress'],
                    coordinate: $coordinate,
                    mainText: isset($place['displayName']['text']) ? (string) $place['displayName']['text'] : null,
                    secondaryText: (string) $place['formattedAddress'],
                );
            }

            Cache::put($cacheKey, $results, $ttlSeconds);

            return $results;
        } catch (Throwable $e) {
            Log::warning('Google Places searchText failed; degrading safely.', [
                'exception' => $this->redactSecrets($e->getMessage()),
            ]);

            return $this->fallback->search($sanitized, $options);
        }
    }

    public function getDetails(string $placeId): ?PlaceSearchResult
    {
        $trimmed = trim($placeId);
        if ($trimmed === '') {
            return null;
        }

        // Known offline preset place IDs delegate directly to local catalog without API cost
        $localFallback = $this->fallback->getDetails($trimmed);
        if ($localFallback !== null) {
            return $localFallback;
        }

        if (empty($this->apiKey)) {
            return null;
        }

        $cacheKey = 'maps_place_details_'.sha1($trimmed);
        $cached = Cache::get($cacheKey);
        if ($cached instanceof PlaceSearchResult) {
            return $cached;
        }

        $resourceId = str_starts_with($trimmed, 'places/') ? substr($trimmed, 7) : $trimmed;
        if (! preg_match('/^[A-Za-z0-9_\-]+$/', $resourceId)) {
            return null;
        }

        try {
            $response = Http::withHeaders([
                'X-Goog-Api-Key' => (string) $this->apiKey,
                'X-Goog-FieldMask' => 'id,displayName,formattedAddress,location',
            ])
                ->connectTimeout(3)
                ->timeout($this->timeoutSeconds)
                ->get("https://places.googleapis.com/v1/places/{$resourceId}");

            if (! $response->successful()) {
                Log::warning('Google Places getDetails returned non-2xx status.', [
                    'status' => $response->status(),
                ]);

                return null;
            }

            $body = $response->json();
            if (! is_array($body) || empty($body['formattedAddress'])) {
                return null;
            }

            $lat = $body['location']['latitude'] ?? null;
            $lng = $body['location']['longitude'] ?? null;

            $result = new PlaceSearchResult(
                placeId: (string) ($body['id'] ?? $resourceId),
                formattedAddress: (string) $body['formattedAddress'],
                coordinate: ($lat !== null && $lng !== null) ? new LocationCoordinate((float) $lat, (float) $lng) : null,
                mainText: isset($body['displayName']['text']) ? (string) $body['displayName']['text'] : null,
                secondaryText: (string) $body['formattedAddress'],
            );

            Cache::put($cacheKey, $result, 86400);

            return $result;
        } catch (Throwable $e) {
            Log::warning('Google Places getDetails failed safely.', [
                'exception' => $this->redactSecrets($e->getMessage()),
            ]);

            return null;
        }
    }

    protected function sanitizeQuery(string $query): string
    {
        $clean = trim($query);
        // Strip accidental email addresses or E.164 phone numbers before transmitting to third-party API
        $clean = (string) preg_replace('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', '', $clean);
        $clean = (string) preg_replace('/\+?\d{8,15}/', '', $clean);

        return trim((string) preg_replace('/\s+/', ' ', $clean));
    }

    protected function redactSecrets(string $message): string
    {
        if (! empty($this->apiKey)) {
            $message = str_replace((string) $this->apiKey, '[REDACTED_MAPS_KEY]', $message);
        }

        return mb_substr($message, 0, 300);
    }
}
