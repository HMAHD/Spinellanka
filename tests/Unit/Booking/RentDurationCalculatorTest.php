<?php

declare(strict_types=1);

use App\Domain\Booking\Services\RentDurationCalculator;
use App\Models\RentPolicy;
use Carbon\Carbon;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    $this->calculator = new RentDurationCalculator;
    $this->policy = new RentPolicy([
        'grace_period_minutes' => 59,
        'extra_hours_threshold' => 5,
        'full_day_threshold_hours' => 6,
    ]);
});

test('sub-24-hour rental calculates as minimum 1 full day', function () {
    $pickup = Carbon::parse('2026-12-01 10:00:00', 'Asia/Colombo');
    $return = Carbon::parse('2026-12-01 18:00:00', 'Asia/Colombo'); // 8 hours

    $result = $this->calculator->calculate($pickup, $return, $this->policy);

    expect($result->days)->toBe(1)
        ->and($result->extraHours)->toBe(0)
        ->and($result->totalMinutes)->toBe(480);
});

test('exact 24-hour rental calculates as 1 day', function () {
    $pickup = Carbon::parse('2026-12-01 10:00:00', 'Asia/Colombo');
    $return = Carbon::parse('2026-12-02 10:00:00', 'Asia/Colombo'); // 24 hours

    $result = $this->calculator->calculate($pickup, $return, $this->policy);

    expect($result->days)->toBe(1)
        ->and($result->extraHours)->toBe(0)
        ->and($result->totalMinutes)->toBe(1440)
        ->and($result->isWithinGracePeriod)->toBeFalse();
});

test('return within 59 minute grace period is free of charge', function () {
    $pickup = Carbon::parse('2026-12-01 10:00:00', 'Asia/Colombo');
    $return = Carbon::parse('2026-12-02 10:55:00', 'Asia/Colombo'); // 24 hours 55 min

    $result = $this->calculator->calculate($pickup, $return, $this->policy);

    expect($result->days)->toBe(1)
        ->and($result->extraHours)->toBe(0)
        ->and($result->isWithinGracePeriod)->toBeTrue();
});

test('return at 1 hour past 24 hour cycle charges 1 extra hour', function () {
    $pickup = Carbon::parse('2026-12-01 10:00:00', 'Asia/Colombo');
    $return = Carbon::parse('2026-12-02 11:00:00', 'Asia/Colombo'); // 25 hours

    $result = $this->calculator->calculate($pickup, $return, $this->policy);

    expect($result->days)->toBe(1)
        ->and($result->extraHours)->toBe(1)
        ->and($result->isWithinGracePeriod)->toBeFalse();
});

test('return between 1 and 5 hours past cycle charges partial extra hours', function () {
    $pickup = Carbon::parse('2026-12-01 10:00:00', 'Asia/Colombo');
    $return = Carbon::parse('2026-12-02 14:15:00', 'Asia/Colombo'); // 28 hours 15 min -> 5 extra hours

    $result = $this->calculator->calculate($pickup, $return, $this->policy);

    expect($result->days)->toBe(1)
        ->and($result->extraHours)->toBe(5);
});

test('return 6 or more hours past cycle rounds up to full 24-hour day', function () {
    $pickup = Carbon::parse('2026-12-01 10:00:00', 'Asia/Colombo');
    $return = Carbon::parse('2026-12-02 16:00:00', 'Asia/Colombo'); // 30 hours -> 6 hours past cycle

    $result = $this->calculator->calculate($pickup, $return, $this->policy);

    expect($result->days)->toBe(2)
        ->and($result->extraHours)->toBe(0);
});

test('multi-day rental calculates accurately across days and extra hours', function () {
    $pickup = Carbon::parse('2026-12-01 10:00:00', 'Asia/Colombo');
    $return = Carbon::parse('2026-12-05 12:30:00', 'Asia/Colombo'); // 4 days + 2h 30m -> 4 days + 3 extra hours

    $result = $this->calculator->calculate($pickup, $return, $this->policy);

    expect($result->days)->toBe(4)
        ->and($result->extraHours)->toBe(3);
});

test('throws exception when return is before or equal to pickup', function () {
    $pickup = Carbon::parse('2026-12-02 10:00:00', 'Asia/Colombo');
    $return = Carbon::parse('2026-12-01 10:00:00', 'Asia/Colombo');

    $this->calculator->calculate($pickup, $return, $this->policy);
})->throws(InvalidArgumentException::class);
