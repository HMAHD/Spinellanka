<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Operations;

use App\Domain\Operations\Enums\DriverTripStatus;

test('driver trip status order is strictly monotonic', function (): void {
    expect(DriverTripStatus::Assigned->order())->toBe(1)
        ->and(DriverTripStatus::Acknowledged->order())->toBe(2)
        ->and(DriverTripStatus::EnRoute->order())->toBe(3)
        ->and(DriverTripStatus::Arrived->order())->toBe(4)
        ->and(DriverTripStatus::Started->order())->toBe(5)
        ->and(DriverTripStatus::Completed->order())->toBe(6)
        ->and(DriverTripStatus::Superseded->order())->toBe(99)
        ->and(DriverTripStatus::Cancelled->order())->toBe(99);
});

test('driver trip status allowed next states conform to progression rules', function (): void {
    expect(DriverTripStatus::Assigned->allowedNextStates())->toEqual([
        DriverTripStatus::Acknowledged,
        DriverTripStatus::Cancelled,
    ]);

    expect(DriverTripStatus::Acknowledged->allowedNextStates())->toEqual([
        DriverTripStatus::EnRoute,
        DriverTripStatus::Cancelled,
    ]);

    expect(DriverTripStatus::EnRoute->allowedNextStates())->toEqual([
        DriverTripStatus::Arrived,
        DriverTripStatus::Cancelled,
    ]);

    expect(DriverTripStatus::Arrived->allowedNextStates())->toEqual([
        DriverTripStatus::Started,
        DriverTripStatus::Cancelled,
    ]);

    expect(DriverTripStatus::Started->allowedNextStates())->toEqual([
        DriverTripStatus::Completed,
    ]);

    expect(DriverTripStatus::Completed->allowedNextStates())->toBeEmpty();
    expect(DriverTripStatus::Superseded->allowedNextStates())->toBeEmpty();
    expect(DriverTripStatus::Cancelled->allowedNextStates())->toBeEmpty();
});

test('driver trip status terminal states', function (): void {
    expect(DriverTripStatus::Completed->isTerminal())->toBeTrue();
    expect(DriverTripStatus::Superseded->isTerminal())->toBeTrue();
    expect(DriverTripStatus::Cancelled->isTerminal())->toBeTrue();

    expect(DriverTripStatus::Assigned->isTerminal())->toBeFalse();
    expect(DriverTripStatus::Acknowledged->isTerminal())->toBeFalse();
    expect(DriverTripStatus::EnRoute->isTerminal())->toBeFalse();
    expect(DriverTripStatus::Arrived->isTerminal())->toBeFalse();
    expect(DriverTripStatus::Started->isTerminal())->toBeFalse();
});

test('driver trip status provides human readable labels and icons', function (): void {
    foreach (DriverTripStatus::cases() as $case) {
        expect($case->label())->toBeString()->not->toBeEmpty();
        expect($case->icon())->toBeString()->toStartWith('heroicon-o-');
    }
});
