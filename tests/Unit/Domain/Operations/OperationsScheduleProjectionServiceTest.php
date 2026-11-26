<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Operations;

use App\Domain\Availability\Enums\ScheduleBlockKind;
use App\Domain\Availability\Enums\ScheduleBlockStatus;
use App\Domain\Booking\Enums\BookingStatus;
use App\Domain\Operations\Services\OperationsScheduleProjectionService;
use App\Models\AirportPickupDetail;
use App\Models\Booking;
use App\Models\Vehicle;
use App\Models\VehicleScheduleBlock;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->projectionService = app(OperationsScheduleProjectionService::class);
});

test('operations schedule projection returns empty calendar structure when no blocks exist', function (): void {
    $month = Carbon::parse('2026-12-01 12:00:00', 'Asia/Colombo');
    $grid = $this->projectionService->projectMonth($month);

    expect($grid)->toBeArray();
});

test('operations schedule projection projects booking blocks with WCAG non-color encodings', function (): void {
    $vehicle = Vehicle::factory()->create(['public_name' => 'KDH Luxury Van']);

    $now = Carbon::parse('2026-12-10 10:00:00', 'Asia/Colombo');
    $startsAt = $now->copy();
    $endsAt = $now->copy()->addHours(3);

    $booking = Booking::factory()->create([
        'reference' => 'BK-TEST-1210',
        'vehicle_id' => $vehicle->id,
        'status' => BookingStatus::Confirmed,
    ]);

    AirportPickupDetail::create([
        'booking_id' => $booking->id,
        'airport' => 'CMB',
        'flight_number' => 'UL-102',
        'arrival_time' => $startsAt,
        'passenger_count' => 3,
        'luggage_count' => 3,
        'name_board_text' => 'Mr. Henderson',
        'destination_text' => 'Galle Fort Hotel, Galle',
    ]);

    $block = VehicleScheduleBlock::factory()->create([
        'vehicle_id' => $vehicle->id,
        'kind' => ScheduleBlockKind::ConfirmedBooking,
        'status' => ScheduleBlockStatus::Active,
        'starts_at' => $startsAt,
        'ends_at' => $endsAt,
        'effective_start' => $startsAt,
        'effective_end' => $endsAt,
        'source_type' => Booking::class,
        'source_id' => $booking->id,
        'blocks_availability' => true,
    ]);

    $grid = $this->projectionService->projectMonth($now);

    $dateKey = '2026-12-10';
    expect($grid)->toHaveKey($dateKey);

    $items = $grid[$dateKey];
    expect($items)->not->toBeEmpty();

    $item = $items[0];
    expect($item->vehicle_id)->toBe($vehicle->id)
        ->and($item->booking_reference)->toBe('BK-TEST-1210')
        ->and($item->border_style)->toBe('solid')
        ->and($item->kind_icon)->toBe('heroicon-o-check-circle')
        ->and($item->has_conflict)->toBeFalse();
});

test('operations schedule projection detects overlapping blocks on same vehicle as conflict', function (): void {
    $vehicle = Vehicle::factory()->create(['public_name' => 'KDH Luxury Van']);

    $now = Carbon::parse('2026-12-15 10:00:00', 'Asia/Colombo');
    $startsAt = $now->copy();
    $endsAt = $now->copy()->addHours(4);

    // Block 1
    VehicleScheduleBlock::factory()->create([
        'vehicle_id' => $vehicle->id,
        'kind' => ScheduleBlockKind::ConfirmedBooking,
        'status' => ScheduleBlockStatus::Active,
        'starts_at' => $startsAt,
        'ends_at' => $endsAt,
        'effective_start' => $startsAt,
        'effective_end' => $endsAt,
        'group_id' => 'group-alpha',
        'blocks_availability' => true,
    ]);

    // Block 2 overlapping
    VehicleScheduleBlock::factory()->create([
        'vehicle_id' => $vehicle->id,
        'kind' => ScheduleBlockKind::Maintenance,
        'status' => ScheduleBlockStatus::Active,
        'starts_at' => $startsAt->copy()->addHour(),
        'ends_at' => $endsAt->copy()->addHour(),
        'effective_start' => $startsAt->copy()->addHour(),
        'effective_end' => $endsAt->copy()->addHour(),
        'group_id' => 'group-beta',
        'blocks_availability' => true,
    ]);

    $grid = $this->projectionService->projectMonth($now);
    $items = $grid['2026-12-15'];

    expect(count($items))->toBe(2);
    expect($items[0]->has_conflict)->toBeTrue();
    expect($items[1]->has_conflict)->toBeTrue();
});

test('operations schedule projection projects day and agenda views', function (): void {
    $vehicle = Vehicle::factory()->create(['public_name' => 'Premio Sedan']);
    $day = Carbon::parse('2026-12-20 08:00:00', 'Asia/Colombo');

    $booking = Booking::factory()->create([
        'reference' => 'BK-DAY-001',
        'vehicle_id' => $vehicle->id,
        'status' => BookingStatus::Confirmed,
    ]);

    VehicleScheduleBlock::factory()->create([
        'vehicle_id' => $vehicle->id,
        'kind' => ScheduleBlockKind::ConfirmedBooking,
        'status' => ScheduleBlockStatus::Active,
        'starts_at' => $day->copy()->setHour(9),
        'ends_at' => $day->copy()->setHour(12),
        'effective_start' => $day->copy()->setHour(9),
        'effective_end' => $day->copy()->setHour(12),
        'source_type' => Booking::class,
        'source_id' => $booking->id,
        'blocks_availability' => true,
    ]);

    $dayItems = $this->projectionService->projectDay($day);
    expect($dayItems)->toHaveCount(1)
        ->and($dayItems[0]->booking_reference)->toBe('BK-DAY-001');

    $agenda = $this->projectionService->projectAgenda($day, 7);
    $dateKey = $day->format('Y-m-d');
    expect($agenda)->toHaveKey($dateKey)
        ->and($agenda[$dateKey])->toHaveCount(1)
        ->and($agenda[$dateKey][0]->booking_reference)->toBe('BK-DAY-001')
        ->and($agenda[$dateKey][0]->vehicle_name)->toContain('Toyota');
});
