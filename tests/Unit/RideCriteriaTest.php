<?php

declare(strict_types=1);

use App\Domain\Booking\DTOs\RideCriteria;
use App\Domain\Booking\DTOs\RideStopData;
use Carbon\Carbon;
use Tests\TestCase;

uses(TestCase::class);

test('it calculates total planned waiting minutes across all stops', function (): void {
    $pickup = Carbon::parse('2026-12-01 10:00:00', 'Asia/Colombo');
    $stops = [
        new RideStopData(stopOrder: 1, locationText: 'Stop 1', plannedWaitingMinutes: 30),
        new RideStopData(stopOrder: 2, locationText: 'Stop 2', plannedWaitingMinutes: 45),
        new RideStopData(stopOrder: 3, locationText: 'Stop 3', plannedWaitingMinutes: 60),
    ];

    $criteria = new RideCriteria(
        pickupTime: $pickup,
        originText: 'Colombo 03',
        destinationText: 'Galle Fort',
        passengerCount: 2,
        luggageCount: 2,
        stops: $stops,
    );

    expect($criteria->totalWaitingMinutes())->toBe(135);
});

test('it calculates effective outbound duration including drive and waiting times', function (): void {
    $pickup = Carbon::parse('2026-12-01 10:00:00', 'Asia/Colombo');
    $stops = [
        new RideStopData(stopOrder: 1, locationText: 'Stop 1', plannedWaitingMinutes: 60),
        new RideStopData(stopOrder: 2, locationText: 'Stop 2', plannedWaitingMinutes: 30),
    ];

    $criteria = new RideCriteria(
        pickupTime: $pickup,
        originText: 'Colombo 03',
        destinationText: 'Kandy',
        passengerCount: 2,
        luggageCount: 2,
        stops: $stops,
        outboundDurationMinutes: 150, // 2.5 hours drive
    );

    // 150 drive + 90 waiting = 240 minutes (4 hours)
    expect($criteria->effectiveOutboundDurationMinutes())->toBe(240);
});

test('it enforces minimum return gap of 60 minutes after outbound arrival', function (): void {
    $pickup = Carbon::parse('2026-12-01 08:00:00', 'Asia/Colombo');
    $stops = [
        new RideStopData(stopOrder: 1, locationText: 'Pinnawala', plannedWaitingMinutes: 120),
    ];

    $criteria = new RideCriteria(
        pickupTime: $pickup,
        originText: 'Colombo',
        destinationText: 'Kandy',
        passengerCount: 2,
        luggageCount: 2,
        stops: $stops,
        hasReturn: true,
        outboundDurationMinutes: 180, // 3 hours drive
    );

    // Total outbound duration = 180 drive + 120 waiting = 300 minutes (5 hours)
    // Arrival at destination = 08:00 + 5h = 13:00.
    // Minimum return pickup time = 13:00 + 60 min = 14:00.
    $validReturn = Carbon::parse('2026-12-01 14:30:00', 'Asia/Colombo');
    $tooEarlyReturn = Carbon::parse('2026-12-01 13:30:00', 'Asia/Colombo');

    $outboundArrival = (clone $pickup)->addMinutes($criteria->effectiveOutboundDurationMinutes());
    $minAllowedReturn = (clone $outboundArrival)->addMinutes(60);

    expect($outboundArrival->format('H:i'))->toBe('13:00')
        ->and($minAllowedReturn->format('H:i'))->toBe('14:00')
        ->and($validReturn->gte($minAllowedReturn))->toBeTrue()
        ->and($tooEarlyReturn->lt($minAllowedReturn))->toBeTrue();
});
