<?php

declare(strict_types=1);

use App\Domain\Location\Contracts\PlaceSearchContract;
use App\Domain\Location\Contracts\RouteEstimateContract;
use App\Domain\Location\DTOs\LocationCoordinate;
use App\Domain\Location\Services\GoogleMapsPlaceSearchService;
use App\Domain\Location\Services\GoogleMapsRouteEstimateService;
use App\Domain\Location\Services\NullPlaceSearchService;
use App\Domain\Location\Services\NullRouteEstimateService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

beforeEach(function (): void {
    Cache::flush();
});

it('binds Null services by default and switches to Google Maps when configured', function (): void {
    Config::set('services.maps.provider', null);
    Config::set('services.maps.key', null);
    expect(app(PlaceSearchContract::class))->toBeInstanceOf(NullPlaceSearchService::class)
        ->and(app(RouteEstimateContract::class))->toBeInstanceOf(NullRouteEstimateService::class);

    Config::set('services.maps.provider', 'google_maps');
    Config::set('services.maps.key', 'test_google_maps_sandbox_key');
    expect(app(PlaceSearchContract::class))->toBeInstanceOf(GoogleMapsPlaceSearchService::class)
        ->and(app(RouteEstimateContract::class))->toBeInstanceOf(GoogleMapsRouteEstimateService::class);
});

it('queries Google Places v1 with LK restriction, strips PII from query, caches responses, and fetches details', function (): void {
    Http::fake([
        'https://places.googleapis.com/v1/places:searchText' => Http::response([
            'places' => [
                [
                    'id' => 'ChIJVeryLongGooglePlaceId1234567890',
                    'displayName' => ['text' => 'Galle Fort Hotel'],
                    'formattedAddress' => '28 Church St, Galle 80000, Sri Lanka',
                    'location' => ['latitude' => 6.0267, 'longitude' => 80.2170],
                ],
            ],
        ], 200),
        'https://places.googleapis.com/v1/places/ChIJVeryLongGooglePlaceId1234567890' => Http::response([
            'id' => 'ChIJVeryLongGooglePlaceId1234567890',
            'displayName' => ['text' => 'Galle Fort Hotel'],
            'formattedAddress' => '28 Church St, Galle 80000, Sri Lanka',
            'location' => ['latitude' => 6.0267, 'longitude' => 80.2170],
        ], 200),
    ]);

    $service = new GoogleMapsPlaceSearchService(new NullPlaceSearchService, 'AIzaTestFakeKey12345678901234567890');

    // Email and phone inside search query must be stripped before reaching Google
    $results = $service->search('Galle Fort Hotel john@traveler.com +94771234567');
    expect($results)->toHaveCount(1)
        ->and($results[0]->placeId)->toBe('ChIJVeryLongGooglePlaceId1234567890')
        ->and($results[0]->coordinate?->latitude)->toBe(6.0267);

    // Repeat call hits cache without extra HTTP call
    $cachedResults = $service->search('Galle Fort Hotel john@traveler.com +94771234567');
    expect($cachedResults)->toHaveCount(1);

    $details = $service->getDetails('places/ChIJVeryLongGooglePlaceId1234567890');
    expect($details)->not->toBeNull()
        ->and($details?->formattedAddress)->toContain('Galle');

    Http::assertSentCount(2);
    Http::assertSent(function ($request): bool {
        if ($request->url() !== 'https://places.googleapis.com/v1/places:searchText') {
            return true;
        }
        $body = $request->data();

        return $body['regionCode'] === 'LK'
            && $body['textQuery'] === 'Galle Fort Hotel'
            && ! str_contains($body['textQuery'], '@')
            && $request->header('X-Goog-Api-Key')[0] === 'AIzaTestFakeKey12345678901234567890';
    });
});

it('degrades Google Places to NullPlaceSearchService on error or timeout without logging secret keys', function (): void {
    Log::spy();
    Http::fake([
        'https://places.googleapis.com/v1/places:searchText' => fn () => throw new ConnectionException('Timeout with key AIzaSecretDoNotLog1234567890123456'),
    ]);

    $service = new GoogleMapsPlaceSearchService(new NullPlaceSearchService, 'AIzaSecretDoNotLog1234567890123456');
    $results = $service->search('Galle');

    expect($results)->not->toBeEmpty()
        ->and(collect($results)->pluck('placeId')->all())->toContain('galle_fort');

    Log::shouldHaveReceived('warning')->withArgs(function (string $msg, array $context): bool {
        $serialized = json_encode($context);

        return ! str_contains((string) $serialized, 'AIzaSecretDoNotLog1234567890123456');
    });
});

it('computes Google Routes v2 estimate and degrades safely to null on quota or network failure', function (): void {
    Http::fake([
        'https://routes.googleapis.com/directions/v2:computeRoutes' => Http::sequence()
            ->push([
                'routes' => [
                    [
                        'distanceMeters' => 142500,
                        'duration' => '7200s',
                    ],
                ],
            ], 200)
            ->push(['error' => ['code' => 429, 'message' => 'Quota exceeded']], 429),
    ]);

    $service = new GoogleMapsRouteEstimateService('AIzaTestFakeKey12345678901234567890');
    $cmb = new LocationCoordinate(7.1808, 79.8841);
    $galle = new LocationCoordinate(6.0329, 80.2168);

    $estimate = $service->estimateRoute($cmb, $galle);
    expect($estimate)->not->toBeNull()
        ->and($estimate?->distanceKm)->toBe(142.5)
        ->and($estimate?->durationMinutes)->toBe(120.0);

    // Different coordinate pair triggers 429 -> degrades cleanly to null (never Haversine guess)
    $kandy = new LocationCoordinate(7.2906, 80.6337);
    expect($service->estimateRoute($cmb, $kandy))->toBeNull();
});

it('forwards configured Referer origin header when maps.allowed_origin is set', function (): void {
    Config::set('services.maps.allowed_origin', 'https://staging.spinellanka.lk');
    Http::fake([
        'https://places.googleapis.com/v1/places:searchText' => Http::response(['places' => []], 200),
    ]);

    $service = new GoogleMapsPlaceSearchService(new NullPlaceSearchService, 'AIzaTestOriginKey1234567890');
    $service->search('Kandy');

    Http::assertSent(fn ($request): bool => ($request->header('Referer')[0] ?? null) === 'https://staging.spinellanka.lk');
});
