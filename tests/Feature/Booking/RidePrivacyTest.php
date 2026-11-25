<?php

declare(strict_types=1);

test('ride views and client scripts contain zero geolocation api calls or device tracking state', function (): void {
    $viewsToScan = [
        resource_path('views/booking/ride/create.blade.php'),
        resource_path('views/booking/ride/vehicles.blade.php'),
        resource_path('views/booking/ride/contact.blade.php'),
        resource_path('views/booking/status.blade.php'),
    ];

    foreach ($viewsToScan as $path) {
        expect(file_exists($path))->toBeTrue("View file {$path} must exist.");

        $content = file_get_contents($path);

        // Assert no geolocation API calls
        expect($content)->not->toContain('navigator.geolocation')
            ->and($content)->not->toContain('getCurrentPosition')
            ->and($content)->not->toContain('watchPosition')
            ->and($content)->not->toContain('coords.latitude')
            ->and($content)->not->toContain('coords.longitude');

        // Assert no live dispatch or tracking simulation
        expect($content)->not->toContain('live-tracking')
            ->and($content)->not->toContain('driver-location-socket');
    }

    // Also scan all compiled/source js files in resources/js
    $jsFiles = glob(resource_path('js/**/*.js')) ?: [];
    foreach ($jsFiles as $jsFile) {
        $content = file_get_contents($jsFile);
        expect($content)->not->toContain('navigator.geolocation')
            ->and($content)->not->toContain('watchPosition');
    }
});

test('status view exposes the narrow presentation boundary with text fallback for phase 12', function (): void {
    $statusView = file_get_contents(resource_path('views/booking/status.blade.php'));

    // Boundary attributes present for Phase 12 enhancement
    expect($statusView)->toContain('id="ride-route-map"')
        ->and($statusView)->toContain('data-origin=')
        ->and($statusView)->toContain('data-destination=')
        ->and($statusView)->toContain('data-stops=')
        ->and($statusView)->toContain('class="route-text-itinerary"');
});

test('ride views do not contain hidden client coordinate inputs', function (): void {
    $createView = file_get_contents(resource_path('views/booking/ride/create.blade.php'));

    expect($createView)->not->toContain('type="hidden" name="user_latitude"')
        ->and($createView)->not->toContain('type="hidden" name="user_longitude"')
        ->and($createView)->not->toContain('name="current_location"');
});
