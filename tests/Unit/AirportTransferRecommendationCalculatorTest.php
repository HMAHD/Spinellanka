<?php

declare(strict_types=1);

use App\Domain\Booking\Services\AirportTransferRecommendationCalculator;
use Carbon\Carbon;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    $this->calculator = new AirportTransferRecommendationCalculator;
});

test('calculates recommended pickup time using 180 min airport buffer and 45 min contingency', function (): void {
    // Departure at 20:00 (8:00 PM)
    $departure = Carbon::create(2026, 12, 1, 20, 0, 0, 'Asia/Colombo');
    $estimatedDurationMinutes = 120; // 2 hours driving time (e.g. Galle to CMB)

    // Expected subtraction: 180 (airport buffer) + 120 (drive) + 45 (contingency) = 345 minutes (5h 45m)
    // 20:00 - 5h 45m = 14:15 (2:15 PM)
    $recommended = $this->calculator->calculateRecommendedPickupTime($departure, $estimatedDurationMinutes);

    expect($recommended->format('Y-m-d H:i'))->toBe('2026-12-01 14:15');
});

test('calculates latest allowed pickup time using 90 min cutoff buffer', function (): void {
    // Departure at 20:00
    $departure = Carbon::create(2026, 12, 1, 20, 0, 0, 'Asia/Colombo');
    $estimatedDurationMinutes = 120; // 2 hours driving time

    // Expected subtraction: 90 (cutoff buffer) + 120 (drive) = 210 minutes (3h 30m)
    // 20:00 - 3h 30m = 16:30 (4:30 PM)
    $latestAllowed = $this->calculator->calculateLatestAllowedPickupTime($departure, $estimatedDurationMinutes);

    expect($latestAllowed->format('Y-m-d H:i'))->toBe('2026-12-01 16:30');
});

test('identifies safe vs unsafe choices correctly', function (): void {
    $departure = Carbon::create(2026, 12, 1, 20, 0, 0, 'Asia/Colombo');
    $estimatedDurationMinutes = 120; // Recommended is 14:15

    // Case 1: At recommended time (14:15) -> Not unsafe
    $atRecommended = Carbon::create(2026, 12, 1, 14, 15, 0, 'Asia/Colombo');
    expect($this->calculator->isUnsafeChoice($departure, $atRecommended, $estimatedDurationMinutes))->toBeFalse();

    // Case 2: Earlier than recommended (13:30) -> Not unsafe (even safer)
    $earlier = Carbon::create(2026, 12, 1, 13, 30, 0, 'Asia/Colombo');
    expect($this->calculator->isUnsafeChoice($departure, $earlier, $estimatedDurationMinutes))->toBeFalse();

    // Case 3: Later than recommended (15:00) -> IS unsafe (flagged for review & warning)
    $later = Carbon::create(2026, 12, 1, 15, 0, 0, 'Asia/Colombo');
    expect($this->calculator->isUnsafeChoice($departure, $later, $estimatedDurationMinutes))->toBeTrue();
});

test('identifies permissible vs impermissible choices accurately against cutoff', function (): void {
    $departure = Carbon::create(2026, 12, 1, 20, 0, 0, 'Asia/Colombo');
    $estimatedDurationMinutes = 120; // Latest allowed cutoff is 16:30

    // Exactly at cutoff (16:30) -> Permissible
    $atCutoff = Carbon::create(2026, 12, 1, 16, 30, 0, 'Asia/Colombo');
    expect($this->calculator->isPermissibleChoice($departure, $atCutoff, $estimatedDurationMinutes))->toBeTrue();

    // Before cutoff (15:30) -> Permissible
    $beforeCutoff = Carbon::create(2026, 12, 1, 15, 30, 0, 'Asia/Colombo');
    expect($this->calculator->isPermissibleChoice($departure, $beforeCutoff, $estimatedDurationMinutes))->toBeTrue();

    // After cutoff (16:31 or 17:00) -> Impermissible
    $afterCutoff = Carbon::create(2026, 12, 1, 17, 0, 0, 'Asia/Colombo');
    expect($this->calculator->isPermissibleChoice($departure, $afterCutoff, $estimatedDurationMinutes))->toBeFalse();

    // After flight departure (21:00) -> Impermissible
    $afterDeparture = Carbon::create(2026, 12, 1, 21, 0, 0, 'Asia/Colombo');
    expect($this->calculator->isPermissibleChoice($departure, $afterDeparture, $estimatedDurationMinutes))->toBeFalse();
});
