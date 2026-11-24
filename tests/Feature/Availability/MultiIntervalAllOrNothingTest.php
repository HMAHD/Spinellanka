<?php

declare(strict_types=1);

use App\Domain\Availability\DTOs\ScheduleIntervalData;
use App\Domain\Availability\Enums\ScheduleBlockKind;
use App\Domain\Availability\Enums\ScheduleBlockStatus;
use App\Domain\Availability\Exceptions\ScheduleConflictException;
use App\Domain\Availability\Services\AvailabilityService;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleScheduleBlock;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed();

    $this->service = app(AvailabilityService::class);

    $this->user = User::create([
        'name' => 'Dispatch Staff',
        'email' => 'dispatch@spinellanka.test',
        'password' => Hash::make('Password123456!'),
        'is_active' => true,
        'token_version' => 1,
    ]);
});

test('multi-interval command creates all legs atomically under common group_id', function (): void {
    $vehicle = Vehicle::factory()->create(['turnaround_buffer_minutes' => 60]);

    // Outbound leg: Dec 1, 08:00 - 12:00
    // Return leg:   Dec 1, 16:00 - 20:00
    $outbound = new ScheduleIntervalData(
        startsAt: Carbon::parse('2026-12-01 08:00:00', 'Asia/Colombo'),
        endsAt: Carbon::parse('2026-12-01 12:00:00', 'Asia/Colombo'),
        bufferMinutesBefore: 0,
        bufferMinutesAfter: 60,
        notes: 'Outbound transfer Colombo to Galle'
    );

    $return = new ScheduleIntervalData(
        startsAt: Carbon::parse('2026-12-01 16:00:00', 'Asia/Colombo'),
        endsAt: Carbon::parse('2026-12-01 20:00:00', 'Asia/Colombo'),
        bufferMinutesBefore: 0,
        bufferMinutesAfter: 60,
        notes: 'Return transfer Galle to Colombo'
    );

    $blocks = $this->service->createMultiIntervalBlocks(
        vehicle: $vehicle,
        kind: ScheduleBlockKind::ConfirmedBooking,
        intervals: [$outbound, $return],
        actor: $this->user
    );

    expect($blocks)->toHaveCount(2);

    $groupId = $blocks->first()->group_id;
    expect($groupId)->not->toBeNull()
        ->and($blocks->last()->group_id)->toBe($groupId)
        ->and($blocks->every(fn ($b) => $b->status === ScheduleBlockStatus::Active))->toBeTrue()
        ->and($blocks->every(fn ($b) => $b->blocks_availability === true))->toBeTrue();
});

test('one-leg conflict rolls back the entire command leaving zero partial blocks', function (): void {
    $vehicle = Vehicle::factory()->create(['turnaround_buffer_minutes' => 60]);

    // Existing conflicting booking on Dec 1, 17:00 - 19:00
    $this->service->createBlock(
        vehicle: $vehicle,
        kind: ScheduleBlockKind::ConfirmedBooking,
        start: Carbon::parse('2026-12-01 17:00:00', 'Asia/Colombo'),
        end: Carbon::parse('2026-12-01 19:00:00', 'Asia/Colombo'),
        bufferBefore: 0,
        bufferAfter: 60,
        actor: $this->user
    );

    $initialCount = VehicleScheduleBlock::count();

    // Now try to book outbound (free) and return (conflicts with 17:00 - 19:00)
    $outbound = new ScheduleIntervalData(
        startsAt: Carbon::parse('2026-12-01 08:00:00', 'Asia/Colombo'),
        endsAt: Carbon::parse('2026-12-01 12:00:00', 'Asia/Colombo'),
        bufferMinutesBefore: 0,
        bufferMinutesAfter: 60,
    );

    $return = new ScheduleIntervalData(
        startsAt: Carbon::parse('2026-12-01 16:00:00', 'Asia/Colombo'),
        endsAt: Carbon::parse('2026-12-01 20:00:00', 'Asia/Colombo'),
        bufferMinutesBefore: 0,
        bufferMinutesAfter: 60,
    );

    expect(fn () => $this->service->createMultiIntervalBlocks(
        vehicle: $vehicle,
        kind: ScheduleBlockKind::ConfirmedBooking,
        intervals: [$outbound, $return],
        actor: $this->user
    ))->toThrow(ScheduleConflictException::class);

    // ZERO partial blocks created!
    expect(VehicleScheduleBlock::count())->toBe($initialCount);
});

test('intra-batch interval collision throws InvalidArgumentException before database write', function (): void {
    $vehicle = Vehicle::factory()->create();

    // Two intervals overlapping each other
    $leg1 = new ScheduleIntervalData(
        startsAt: Carbon::parse('2026-12-01 10:00:00', 'Asia/Colombo'),
        endsAt: Carbon::parse('2026-12-01 14:00:00', 'Asia/Colombo'),
    );

    $leg2 = new ScheduleIntervalData(
        startsAt: Carbon::parse('2026-12-01 13:00:00', 'Asia/Colombo'),
        endsAt: Carbon::parse('2026-12-01 16:00:00', 'Asia/Colombo'),
    );

    $initialCount = VehicleScheduleBlock::count();

    expect(fn () => $this->service->createMultiIntervalBlocks(
        vehicle: $vehicle,
        kind: ScheduleBlockKind::ConfirmedBooking,
        intervals: [$leg1, $leg2],
        actor: $this->user
    ))->toThrow(InvalidArgumentException::class, 'overlap with each other');

    expect(VehicleScheduleBlock::count())->toBe($initialCount);
});

test('releaseGroup atomically releases all grouped intervals', function (): void {
    $vehicle = Vehicle::factory()->create(['turnaround_buffer_minutes' => 60]);

    $outbound = new ScheduleIntervalData(
        startsAt: Carbon::parse('2026-12-01 08:00:00', 'Asia/Colombo'),
        endsAt: Carbon::parse('2026-12-01 12:00:00', 'Asia/Colombo'),
        bufferMinutesBefore: 0,
        bufferMinutesAfter: 60,
    );

    $return = new ScheduleIntervalData(
        startsAt: Carbon::parse('2026-12-01 16:00:00', 'Asia/Colombo'),
        endsAt: Carbon::parse('2026-12-01 20:00:00', 'Asia/Colombo'),
        bufferMinutesBefore: 0,
        bufferMinutesAfter: 60,
    );

    $blocks = $this->service->createMultiIntervalBlocks(
        vehicle: $vehicle,
        kind: ScheduleBlockKind::ConfirmedBooking,
        intervals: [$outbound, $return],
        actor: $this->user
    );

    $groupId = $blocks->first()->group_id;

    $releasedCount = $this->service->releaseGroup($groupId, 'customer_cancelled_trip', $this->user);
    expect($releasedCount)->toBe(2);

    $freshBlocks = VehicleScheduleBlock::where('group_id', $groupId)->get();
    expect($freshBlocks->every(fn ($b) => $b->status === ScheduleBlockStatus::Released))->toBeTrue()
        ->and($freshBlocks->every(fn ($b) => $b->blocks_availability === false))->toBeTrue();
});
