<?php

declare(strict_types=1);

use App\Domain\Availability\DTOs\ScheduleIntervalData;
use App\Domain\Availability\Enums\HoldStatus;
use App\Domain\Availability\Enums\ScheduleBlockKind;
use App\Domain\Availability\Enums\ScheduleBlockStatus;
use App\Domain\Availability\Exceptions\ScheduleConflictException;
use App\Domain\Availability\Services\AvailabilityService;
use App\Domain\Booking\ServiceType;
use App\Models\BookingHold;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleScheduleBlock;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed();

    $this->service = app(AvailabilityService::class);

    $this->userA = User::create([
        'name' => 'Agent Alpha',
        'email' => 'alpha@spinellanka.test',
        'password' => Hash::make('Password123456!'),
        'is_active' => true,
        'token_version' => 1,
    ]);

    $this->userB = User::create([
        'name' => 'Agent Beta',
        'email' => 'beta@spinellanka.test',
        'password' => Hash::make('Password123456!'),
        'is_active' => true,
        'token_version' => 1,
    ]);
});

test('pessimistic lock serialization prevents double-booking in two-writer race', function (): void {
    $vehicle = Vehicle::factory()->create(['turnaround_buffer_minutes' => 60]);

    $start = Carbon::parse('2026-12-05 10:00:00', 'Asia/Colombo');
    $end = Carbon::parse('2026-12-05 14:00:00', 'Asia/Colombo');

    // Simulate two concurrent booking attempts on the exact same vehicle & window
    $attemptA = function () use ($vehicle, $start, $end) {
        return $this->service->createBlock(
            vehicle: $vehicle,
            kind: ScheduleBlockKind::ConfirmedBooking,
            start: $start,
            end: $end,
            bufferBefore: 0,
            bufferAfter: 60,
            actor: $this->userA
        );
    };

    $attemptB = function () use ($vehicle, $start, $end) {
        return $this->service->createBlock(
            vehicle: $vehicle,
            kind: ScheduleBlockKind::ConfirmedBooking,
            start: $start,
            end: $end,
            bufferBefore: 0,
            bufferAfter: 60,
            actor: $this->userB
        );
    };

    // First attempt succeeds
    $blockA = $attemptA();
    expect($blockA->id)->not->toBeNull()
        ->and($blockA->status)->toBe(ScheduleBlockStatus::Active);

    // Second attempt fails with ScheduleConflictException
    expect(fn () => $attemptB())->toThrow(ScheduleConflictException::class);

    // Exactly one block exists in database
    expect(VehicleScheduleBlock::where('vehicle_id', $vehicle->id)->count())->toBe(1);
});

test('hold expiry race: expiring hold frees the slot for competing writer in atomic transaction', function (): void {
    $vehicle = Vehicle::factory()->create(['turnaround_buffer_minutes' => 60]);

    $start = Carbon::parse('2026-12-05 10:00:00', 'Asia/Colombo');
    $end = Carbon::parse('2026-12-05 14:00:00', 'Asia/Colombo');

    // Traveler A created a checkout hold
    $holdResult = $this->service->createHold(
        vehicle: $vehicle,
        start: $start,
        end: $end,
        service: ServiceType::AirportPickup,
        durationMinutes: 15
    );

    // While hold is active, competing writer B cannot book
    expect(fn () => $this->service->createBlock(
        vehicle: $vehicle,
        kind: ScheduleBlockKind::ConfirmedBooking,
        start: $start,
        end: $end,
        bufferBefore: 0,
        bufferAfter: 60,
        actor: $this->userB
    ))->toThrow(ScheduleConflictException::class);

    // Advance clock past the 15-minute hold window (e.g. 15m 01s)
    Carbon::setTestNow(Carbon::now('Asia/Colombo')->addMinutes(16));

    // Competing writer B attempts booking now:
    // Step 2 acquires lock, Step 3 expires Traveler A's hold, Step 4 sees no conflicts, Step 6 writes block!
    $blockB = $this->service->createBlock(
        vehicle: $vehicle,
        kind: ScheduleBlockKind::ConfirmedBooking,
        start: $start,
        end: $end,
        bufferBefore: 0,
        bufferAfter: 60,
        actor: $this->userB
    );

    expect($blockB->id)->not->toBeNull()
        ->and($blockB->status)->toBe(ScheduleBlockStatus::Active);

    $holdResult->hold->refresh();
    expect($holdResult->hold->status)->toBe(HoldStatus::Expired)
        ->and($holdResult->hold->scheduleBlock->status)->toBe(ScheduleBlockStatus::Expired);

    Carbon::setTestNow();
});

test('transaction failure triggers full rollback without orphan blocks', function (): void {
    $vehicle = Vehicle::factory()->create(['turnaround_buffer_minutes' => 60]);

    $start = Carbon::parse('2026-12-05 10:00:00', 'Asia/Colombo');
    $end = Carbon::parse('2026-12-05 14:00:00', 'Asia/Colombo');

    $initialBlocks = VehicleScheduleBlock::count();
    $initialHolds = BookingHold::count();

    try {
        DB::transaction(function () use ($vehicle, $start, $end) {
            $this->service->createBlock(
                vehicle: $vehicle,
                kind: ScheduleBlockKind::ConfirmedBooking,
                start: $start,
                end: $end,
                actor: $this->userA
            );

            // Simulate catastrophic runtime failure midway before commit
            throw new RuntimeException('Simulated payment/network failure before transaction commit');
        });
    } catch (RuntimeException $e) {
        expect($e->getMessage())->toContain('Simulated payment/network failure');
    }

    // Verify full rollback: no new blocks or holds exist
    expect(VehicleScheduleBlock::count())->toBe($initialBlocks)
        ->and(BookingHold::count())->toBe($initialHolds);
});

test('two staff members applying conflicting updates serialize and reject overlapping update', function (): void {
    $vehicle = Vehicle::factory()->create(['turnaround_buffer_minutes' => 60]);

    $start = Carbon::parse('2026-12-05 12:00:00', 'Asia/Colombo');
    $end = Carbon::parse('2026-12-05 16:00:00', 'Asia/Colombo');

    // Staff A logs scheduled maintenance
    $blockA = $this->service->createBlock(
        vehicle: $vehicle,
        kind: ScheduleBlockKind::Maintenance,
        start: $start,
        end: $end,
        options: ['notes' => 'Fleet inspection'],
        actor: $this->userA
    );
    expect($blockA->id)->not->toBeNull();

    // Staff B logs an operational manual block overlapping Staff A
    expect(fn () => $this->service->createBlock(
        vehicle: $vehicle,
        kind: ScheduleBlockKind::ManualBlock,
        start: $start,
        end: $end,
        options: ['notes' => 'Reserved for VIP delegation'],
        actor: $this->userB
    ))->toThrow(ScheduleConflictException::class);

    // Only Staff A's block is active
    $activeBlocks = VehicleScheduleBlock::where('vehicle_id', $vehicle->id)
        ->where('status', ScheduleBlockStatus::Active->value)
        ->get();

    expect($activeBlocks)->toHaveCount(1)
        ->and($activeBlocks->first()->kind)->toBe(ScheduleBlockKind::Maintenance);
});

test('grouped multi-interval race guarantees all-or-nothing allocation', function (): void {
    $vehicle = Vehicle::factory()->create(['turnaround_buffer_minutes' => 60]);

    $leg1 = new ScheduleIntervalData(
        startsAt: Carbon::parse('2026-12-06 08:00:00', 'Asia/Colombo'),
        endsAt: Carbon::parse('2026-12-06 12:00:00', 'Asia/Colombo'),
        bufferMinutesBefore: 0,
        bufferMinutesAfter: 60,
    );

    $leg2 = new ScheduleIntervalData(
        startsAt: Carbon::parse('2026-12-06 16:00:00', 'Asia/Colombo'),
        endsAt: Carbon::parse('2026-12-06 20:00:00', 'Asia/Colombo'),
        bufferMinutesBefore: 0,
        bufferMinutesAfter: 60,
    );

    // Traveler 1 books round trip
    $blocks1 = $this->service->createMultiIntervalBlocks(
        vehicle: $vehicle,
        kind: ScheduleBlockKind::ConfirmedBooking,
        intervals: [$leg1, $leg2],
        actor: $this->userA
    );
    expect($blocks1)->toHaveCount(2);

    // Traveler 2 attempts to book overlapping leg2
    $competingLeg = new ScheduleIntervalData(
        startsAt: Carbon::parse('2026-12-06 17:00:00', 'Asia/Colombo'),
        endsAt: Carbon::parse('2026-12-06 21:00:00', 'Asia/Colombo'),
        bufferMinutesBefore: 0,
        bufferMinutesAfter: 60,
    );

    $legFree = new ScheduleIntervalData(
        startsAt: Carbon::parse('2026-12-07 08:00:00', 'Asia/Colombo'),
        endsAt: Carbon::parse('2026-12-07 12:00:00', 'Asia/Colombo'),
        bufferMinutesBefore: 0,
        bufferMinutesAfter: 60,
    );

    expect(fn () => $this->service->createMultiIntervalBlocks(
        vehicle: $vehicle,
        kind: ScheduleBlockKind::ConfirmedBooking,
        intervals: [$competingLeg, $legFree],
        actor: $this->userB
    ))->toThrow(ScheduleConflictException::class);

    // Only Traveler 1's blocks exist in database for this vehicle
    expect(VehicleScheduleBlock::where('vehicle_id', $vehicle->id)->count())->toBe(2);
});
