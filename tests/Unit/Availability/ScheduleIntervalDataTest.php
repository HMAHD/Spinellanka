<?php

use App\Domain\Availability\DTOs\ScheduleIntervalData;
use Carbon\Carbon;

it('normalizes timestamps to Asia/Colombo and computes effective boundaries', function (): void {
    $utcStart = Carbon::parse('2026-12-01 04:30:00', 'UTC'); // 10:00 Asia/Colombo (+05:30)
    $utcEnd = Carbon::parse('2026-12-01 08:30:00', 'UTC');   // 14:00 Asia/Colombo (+05:30)

    $interval = new ScheduleIntervalData(
        startsAt: $utcStart,
        endsAt: $utcEnd,
        bufferMinutesBefore: 30,
        bufferMinutesAfter: 90,
    );

    expect($interval->startsAt->timezoneName)->toBe('Asia/Colombo')
        ->and($interval->startsAt->format('H:i'))->toBe('10:00')
        ->and($interval->endsAt->format('H:i'))->toBe('14:00')
        ->and($interval->effectiveStart()->format('H:i'))->toBe('09:30')
        ->and($interval->effectiveEnd()->format('H:i'))->toBe('15:30');
});

it('proves half-open interval overlap rules mathematically', function (): void {
    // Exact contiguous boundaries: [10:00, 12:00) and [12:00, 14:00)
    $aStart = Carbon::parse('2026-12-01 10:00:00', 'Asia/Colombo');
    $aEnd = Carbon::parse('2026-12-01 12:00:00', 'Asia/Colombo');
    $bStart = Carbon::parse('2026-12-01 12:00:00', 'Asia/Colombo');
    $bEnd = Carbon::parse('2026-12-01 14:00:00', 'Asia/Colombo');

    $overlaps = $aStart->lt($bEnd) && $aEnd->gt($bStart);
    expect($overlaps)->toBeFalse();

    // Overlapping by 1 second: [10:00, 12:00:01) and [12:00, 14:00)
    $aEndOverlap = Carbon::parse('2026-12-01 12:00:01', 'Asia/Colombo');
    $overlapsWith1Sec = $aStart->lt($bEnd) && $aEndOverlap->gt($bStart);
    expect($overlapsWith1Sec)->toBeTrue();

    // Strict subset: [10:30, 11:30) inside [10:00, 12:00)
    $subStart = Carbon::parse('2026-12-01 10:30:00', 'Asia/Colombo');
    $subEnd = Carbon::parse('2026-12-01 11:30:00', 'Asia/Colombo');
    $subsetOverlaps = $aStart->lt($subEnd) && $aEnd->gt($subStart);
    expect($subsetOverlaps)->toBeTrue();
});
