<?php

declare(strict_types=1);

use App\Domain\Availability\Enums\HoldStatus;
use App\Domain\Availability\Enums\ScheduleBlockKind;
use App\Domain\Availability\Enums\ScheduleBlockStatus;
use App\Domain\Availability\Exceptions\ScheduleConflictException;
use App\Domain\Availability\Exceptions\UnauthorizedOverrideException;
use App\Domain\Availability\Services\AvailabilityService;
use App\Domain\Booking\ServiceType;
use App\Domain\Identity\Permission;
use App\Models\AuditEvent;
use App\Models\BookingHold;
use App\Models\Driver;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleDriverAssignment;
use App\Models\VehicleScheduleBlock;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed();

    $this->service = app(AvailabilityService::class);

    $this->admin = User::create([
        'name' => 'Admin User',
        'email' => 'admin@spinellanka.test',
        'password' => Hash::make('Password123456!'),
        'is_active' => true,
        'token_version' => 1,
    ]);

    $this->privilegedUser = User::create([
        'name' => 'Operations Lead',
        'email' => 'ops@spinellanka.test',
        'password' => Hash::make('Password123456!'),
        'is_active' => true,
        'token_version' => 1,
    ]);
    $this->privilegedUser->givePermissionTo(Permission::BookingOverrideConflict->value);
    $this->privilegedUser->givePermissionTo(Permission::FleetManage->value);
});

test('non-overlapping intervals on same vehicle succeed without conflict', function (): void {
    $vehicle = Vehicle::factory()->create(['turnaround_buffer_minutes' => 60]);

    $start1 = Carbon::parse('2026-12-01 08:00:00', 'Asia/Colombo');
    $end1 = Carbon::parse('2026-12-01 12:00:00', 'Asia/Colombo');

    $start2 = Carbon::parse('2026-12-01 14:00:00', 'Asia/Colombo');
    $end2 = Carbon::parse('2026-12-01 18:00:00', 'Asia/Colombo');

    $block1 = $this->service->createBlock(
        vehicle: $vehicle,
        kind: ScheduleBlockKind::Maintenance,
        start: $start1,
        end: $end1,
        bufferBefore: 0,
        bufferAfter: 60,
        actor: $this->admin
    );

    $block2 = $this->service->createBlock(
        vehicle: $vehicle,
        kind: ScheduleBlockKind::ManualBlock,
        start: $start2,
        end: $end2,
        bufferBefore: 0,
        bufferAfter: 60,
        actor: $this->admin
    );

    expect($block1->id)->not->toBeNull()
        ->and($block2->id)->not->toBeNull()
        ->and($block1->status)->toBe(ScheduleBlockStatus::Active)
        ->and($block2->status)->toBe(ScheduleBlockStatus::Active);
});

test('overlapping interval throws ScheduleConflictException', function (): void {
    $vehicle = Vehicle::factory()->create(['turnaround_buffer_minutes' => 60]);

    $start1 = Carbon::parse('2026-12-01 10:00:00', 'Asia/Colombo');
    $end1 = Carbon::parse('2026-12-01 14:00:00', 'Asia/Colombo');

    $this->service->createBlock(
        vehicle: $vehicle,
        kind: ScheduleBlockKind::ConfirmedBooking,
        start: $start1,
        end: $end1,
        bufferBefore: 0,
        bufferAfter: 60,
        actor: $this->admin
    );

    $start2 = Carbon::parse('2026-12-01 12:00:00', 'Asia/Colombo');
    $end2 = Carbon::parse('2026-12-01 16:00:00', 'Asia/Colombo');

    expect(fn () => $this->service->createBlock(
        vehicle: $vehicle,
        kind: ScheduleBlockKind::ConfirmedBooking,
        start: $start2,
        end: $end2,
        bufferBefore: 0,
        bufferAfter: 60,
        actor: $this->admin
    ))->toThrow(ScheduleConflictException::class);
});

test('exact contiguous boundaries do not conflict', function (): void {
    $vehicle = Vehicle::factory()->create(['turnaround_buffer_minutes' => 0]);

    $start1 = Carbon::parse('2026-12-01 10:00:00', 'Asia/Colombo');
    $end1 = Carbon::parse('2026-12-01 12:00:00', 'Asia/Colombo');

    $start2 = Carbon::parse('2026-12-01 12:00:00', 'Asia/Colombo');
    $end2 = Carbon::parse('2026-12-01 14:00:00', 'Asia/Colombo');

    $block1 = $this->service->createBlock(
        vehicle: $vehicle,
        kind: ScheduleBlockKind::ConfirmedBooking,
        start: $start1,
        end: $end1,
        bufferBefore: 0,
        bufferAfter: 0,
        actor: $this->admin
    );

    $block2 = $this->service->createBlock(
        vehicle: $vehicle,
        kind: ScheduleBlockKind::ConfirmedBooking,
        start: $start2,
        end: $end2,
        bufferBefore: 0,
        bufferAfter: 0,
        actor: $this->admin
    );

    expect($block1->id)->not->toBeNull()
        ->and($block2->id)->not->toBeNull();
});

test('turnaround buffer creates conflict if within buffer window and passes outside', function (): void {
    $vehicle = Vehicle::factory()->create(['turnaround_buffer_minutes' => 90]);

    $start1 = Carbon::parse('2026-12-01 10:00:00', 'Asia/Colombo');
    $end1 = Carbon::parse('2026-12-01 12:00:00', 'Asia/Colombo');
    // effective_end will be 12:00 + 90 mins = 13:30

    $this->service->createBlock(
        vehicle: $vehicle,
        kind: ScheduleBlockKind::ConfirmedBooking,
        start: $start1,
        end: $end1,
        bufferBefore: 0,
        bufferAfter: 90,
        actor: $this->admin
    );

    // Starts at 13:00 (inside the 90m buffer) -> conflicts!
    $conflictStart = Carbon::parse('2026-12-01 13:00:00', 'Asia/Colombo');
    $conflictEnd = Carbon::parse('2026-12-01 15:00:00', 'Asia/Colombo');

    expect(fn () => $this->service->createBlock(
        vehicle: $vehicle,
        kind: ScheduleBlockKind::ConfirmedBooking,
        start: $conflictStart,
        end: $conflictEnd,
        bufferBefore: 0,
        bufferAfter: 90,
        actor: $this->admin
    ))->toThrow(ScheduleConflictException::class);

    // Starts at 13:30 (exactly after the 90m buffer) -> passes!
    $safeStart = Carbon::parse('2026-12-01 13:30:00', 'Asia/Colombo');
    $safeEnd = Carbon::parse('2026-12-01 15:30:00', 'Asia/Colombo');

    $block = $this->service->createBlock(
        vehicle: $vehicle,
        kind: ScheduleBlockKind::ConfirmedBooking,
        start: $safeStart,
        end: $safeEnd,
        bufferBefore: 0,
        bufferAfter: 90,
        actor: $this->admin
    );

    expect($block->id)->not->toBeNull();
});

test('15-minute checkout hold creates hold record, hashed token, and rejects non-15m duration', function (): void {
    $vehicle = Vehicle::factory()->create(['turnaround_buffer_minutes' => 60]);
    $start = Carbon::parse('2026-12-01 10:00:00', 'Asia/Colombo');
    $end = Carbon::parse('2026-12-01 12:00:00', 'Asia/Colombo');

    // Reject non-15m
    expect(fn () => $this->service->createHold(
        vehicle: $vehicle,
        start: $start,
        end: $end,
        service: ServiceType::AirportPickup,
        durationMinutes: 20
    ))->toThrow(InvalidArgumentException::class);

    // Valid 15m hold
    $result = $this->service->createHold(
        vehicle: $vehicle,
        start: $start,
        end: $end,
        service: ServiceType::AirportPickup,
        durationMinutes: 15
    );

    expect($result->plainToken)->not->toBeEmpty()
        ->and($result->hold->hold_token_hash)->toBe(hash('sha256', $result->plainToken))
        ->and($result->hold->status)->toBe(HoldStatus::Active)
        ->and($result->hold->scheduleBlock->kind)->toBe(ScheduleBlockKind::CheckoutHold)
        ->and($result->hold->scheduleBlock->blocks_availability)->toBeTrue();
});

test('stale hold is automatically expired when subsequent transaction runs', function (): void {
    $vehicle = Vehicle::factory()->create(['turnaround_buffer_minutes' => 60]);
    $start = Carbon::parse('2026-12-01 10:00:00', 'Asia/Colombo');
    $end = Carbon::parse('2026-12-01 12:00:00', 'Asia/Colombo');

    // Create hold in the past
    $holdResult = $this->service->createHold(
        vehicle: $vehicle,
        start: $start,
        end: $end,
        service: ServiceType::AirportPickup
    );

    // Travel in time 16 minutes later
    Carbon::setTestNow(Carbon::now('Asia/Colombo')->addMinutes(16));

    // Creating another block for same window now succeeds because stale hold is auto-expired!
    $block = $this->service->createBlock(
        vehicle: $vehicle,
        kind: ScheduleBlockKind::ConfirmedBooking,
        start: $start,
        end: $end,
        bufferBefore: 0,
        bufferAfter: 60,
        actor: $this->admin
    );

    expect($block->id)->not->toBeNull();

    $holdResult->hold->refresh();
    expect($holdResult->hold->status)->toBe(HoldStatus::Expired)
        ->and($holdResult->hold->scheduleBlock->status)->toBe(ScheduleBlockStatus::Expired)
        ->and($holdResult->hold->scheduleBlock->blocks_availability)->toBeFalse();

    Carbon::setTestNow();
});

test('unauthorized override attempt without permission or reason throws UnauthorizedOverrideException', function (): void {
    $vehicle = Vehicle::factory()->create(['turnaround_buffer_minutes' => 60]);
    $start = Carbon::parse('2026-12-01 10:00:00', 'Asia/Colombo');
    $end = Carbon::parse('2026-12-01 12:00:00', 'Asia/Colombo');

    $this->service->createBlock(
        vehicle: $vehicle,
        kind: ScheduleBlockKind::ConfirmedBooking,
        start: $start,
        end: $end,
        bufferBefore: 0,
        bufferAfter: 60,
        actor: $this->admin
    );

    // Unprivileged user trying override
    expect(fn () => $this->service->createBlock(
        vehicle: $vehicle,
        kind: ScheduleBlockKind::Maintenance,
        start: $start,
        end: $end,
        options: [
            'is_override' => true,
            'override_reason' => 'Emergency maintenance',
        ],
        actor: $this->admin // has no booking.override_conflict permission
    ))->toThrow(UnauthorizedOverrideException::class);

    // Privileged user without reason
    expect(fn () => $this->service->createBlock(
        vehicle: $vehicle,
        kind: ScheduleBlockKind::Maintenance,
        start: $start,
        end: $end,
        options: [
            'is_override' => true,
            'override_reason' => '',
        ],
        actor: $this->privilegedUser
    ))->toThrow(UnauthorizedOverrideException::class);
});

test('authorized override succeeds, flags block, and records audit event', function (): void {
    $vehicle = Vehicle::factory()->create(['turnaround_buffer_minutes' => 60]);
    $start = Carbon::parse('2026-12-01 10:00:00', 'Asia/Colombo');
    $end = Carbon::parse('2026-12-01 12:00:00', 'Asia/Colombo');

    $this->service->createBlock(
        vehicle: $vehicle,
        kind: ScheduleBlockKind::ConfirmedBooking,
        start: $start,
        end: $end,
        bufferBefore: 0,
        bufferAfter: 60,
        actor: $this->admin
    );

    $overrideBlock = $this->service->createBlock(
        vehicle: $vehicle,
        kind: ScheduleBlockKind::Maintenance,
        start: $start,
        end: $end,
        options: [
            'is_override' => true,
            'override_reason' => 'Urgent brake repair authorized by dispatch',
        ],
        actor: $this->privilegedUser
    );

    expect($overrideBlock->is_override)->toBeTrue()
        ->and($overrideBlock->override_reason)->toBe('Urgent brake repair authorized by dispatch')
        ->and($overrideBlock->override_authorized_by)->toBe($this->privilegedUser->id);

    $audit = AuditEvent::where('action', 'availability.schedule_block.override_created')->first();
    expect($audit)->not->toBeNull()
        ->and($audit->metadata['is_override'])->toBeTrue();
});

test('driver unavailability blocks chauffeured services but leaves Rent available', function (): void {
    $vehicle = Vehicle::factory()->create(['eligible_for_rent' => true, 'eligible_for_pickup' => true]);
    $driver = Driver::factory()->create();

    $start = Carbon::parse('2026-12-01 10:00:00', 'Asia/Colombo');
    $end = Carbon::parse('2026-12-01 18:00:00', 'Asia/Colombo');

    $block = $this->service->createDriverUnavailabilityBlock(
        vehicle: $vehicle,
        driver: $driver,
        start: $start,
        end: $end,
        reason: 'Driver medical leave',
        actor: $this->privilegedUser
    );

    expect($block->is_chauffeured_only)->toBeTrue()
        ->and($block->driver_id)->toBe($driver->id);

    // Check Rent (self-drive): should be available!
    $rentAvailable = $this->service->checkAvailability(
        vehicle: $vehicle,
        start: $start,
        end: $end,
        service: ServiceType::Rent
    );
    expect($rentAvailable)->toBeTrue();

    // Check AirportPickup (chauffeured): should NOT be available!
    $pickupAvailable = $this->service->checkAvailability(
        vehicle: $vehicle,
        start: $start,
        end: $end,
        service: ServiceType::AirportPickup
    );
    expect($pickupAvailable)->toBeFalse();

    // Approved replacement driver allows chauffeured booking to proceed!
    $replacementDriver = Driver::factory()->create();
    $replacementAvailable = $this->service->checkAvailability(
        vehicle: $vehicle,
        start: $start,
        end: $end,
        service: ServiceType::AirportPickup,
        approvedReplacementDriverId: $replacementDriver->id
    );
    expect($replacementAvailable)->toBeTrue();
});

test('releaseHold and releaseBlock deactivate availability blocking', function (): void {
    $vehicle = Vehicle::factory()->create(['turnaround_buffer_minutes' => 60]);
    $start = Carbon::parse('2026-12-01 10:00:00', 'Asia/Colombo');
    $end = Carbon::parse('2026-12-01 12:00:00', 'Asia/Colombo');

    // Create and release a hold
    $holdResult = $this->service->createHold(
        vehicle: $vehicle,
        start: $start,
        end: $end,
        service: ServiceType::AirportPickup
    );

    $released = $this->service->releaseHold($holdResult->plainToken, 'customer_cancelled', $this->admin);
    expect($released)->toBeTrue();

    $holdResult->hold->refresh();
    expect($holdResult->hold->status)->toBe(HoldStatus::Released)
        ->and($holdResult->hold->scheduleBlock->status)->toBe(ScheduleBlockStatus::Released)
        ->and($holdResult->hold->scheduleBlock->blocks_availability)->toBeFalse();

    // Create and release a manual block
    $block = $this->service->createBlock(
        vehicle: $vehicle,
        kind: ScheduleBlockKind::ManualBlock,
        start: $start,
        end: $end,
        actor: $this->admin
    );

    $releasedBlock = $this->service->releaseBlock($block, 'maintenance_completed', $this->admin);
    expect($releasedBlock->status)->toBe(ScheduleBlockStatus::Released)
        ->and($releasedBlock->blocks_availability)->toBeFalse();

    // Vehicle is now available again for that interval
    $isAvailable = $this->service->checkAvailability($vehicle, $start, $end);
    expect($isAvailable)->toBeTrue();
});

test('negative buffer minutes throw InvalidArgumentException', function (): void {
    $vehicle = Vehicle::factory()->create();
    $start = Carbon::parse('2026-12-01 10:00:00', 'Asia/Colombo');
    $end = Carbon::parse('2026-12-01 12:00:00', 'Asia/Colombo');

    expect(fn () => $this->service->createHold(
        vehicle: $vehicle,
        start: $start,
        end: $end,
        service: ServiceType::AirportPickup,
        bufferBefore: -10
    ))->toThrow(InvalidArgumentException::class, 'cannot be negative');

    expect(fn () => $this->service->createBlock(
        vehicle: $vehicle,
        kind: ScheduleBlockKind::ConfirmedBooking,
        start: $start,
        end: $end,
        bufferBefore: 0,
        bufferAfter: -30
    ))->toThrow(InvalidArgumentException::class, 'cannot be negative');

    expect(fn () => $this->service->checkAvailability(
        vehicle: $vehicle,
        start: $start,
        end: $end,
        bufferBefore: -15
    ))->toThrow(InvalidArgumentException::class, 'cannot be negative');
});

test('permanent driver unavailability cross-checks and blocks tied vehicle for chauffeured service', function (): void {
    $vehicle = Vehicle::factory()->create(['eligible_for_rent' => true, 'eligible_for_pickup' => true]);
    $driver = Driver::factory()->create();

    // Assign driver permanently to vehicle
    VehicleDriverAssignment::create([
        'vehicle_id' => $vehicle->id,
        'driver_id' => $driver->id,
        'starts_at' => Carbon::now('Asia/Colombo')->subDays(5),
        'ends_at' => null,
        'is_permanent' => true,
    ]);

    $start = Carbon::parse('2026-12-10 10:00:00', 'Asia/Colombo');
    $end = Carbon::parse('2026-12-10 18:00:00', 'Asia/Colombo');

    // Create unavailability block directly using auto-resolved vehicle
    $block = $this->service->createDriverUnavailabilityBlock(
        driver: $driver,
        start: $start,
        end: $end,
        reason: 'Family emergency'
    );

    expect($block->vehicle_id)->toBe($vehicle->id)
        ->and($block->driver_id)->toBe($driver->id)
        ->and($block->is_chauffeured_only)->toBeTrue();

    // Chauffeured service (AirportPickup) MUST be blocked on the tied vehicle!
    expect($this->service->checkAvailability(
        vehicle: $vehicle,
        start: $start,
        end: $end,
        service: ServiceType::AirportPickup
    ))->toBeFalse();

    // Rent (self-drive) MUST remain available!
    expect($this->service->checkAvailability(
        vehicle: $vehicle,
        start: $start,
        end: $end,
        service: ServiceType::Rent
    ))->toBeTrue();

    // Approved replacement driver unblocks the chauffeured service
    $replacement = Driver::factory()->create();
    expect($this->service->checkAvailability(
        vehicle: $vehicle,
        start: $start,
        end: $end,
        service: ServiceType::AirportPickup,
        approvedReplacementDriverId: $replacement->id
    ))->toBeTrue();
});

test('releaseBlock is idempotent and does not append duplicate notes', function (): void {
    $vehicle = Vehicle::factory()->create();
    $start = Carbon::parse('2026-12-01 10:00:00', 'Asia/Colombo');
    $end = Carbon::parse('2026-12-01 12:00:00', 'Asia/Colombo');

    $block = $this->service->createBlock(
        vehicle: $vehicle,
        kind: ScheduleBlockKind::ManualBlock,
        start: $start,
        end: $end,
        options: ['notes' => 'Original note'],
        actor: $this->admin
    );

    $released1 = $this->service->releaseBlock($block, 'first_release', $this->admin);
    expect($released1->status)->toBe(ScheduleBlockStatus::Released)
        ->and($released1->notes)->toBe('Original note | Released: first_release');

    // Calling releaseBlock again on already released block should return cleanly without duplicate notes
    $released2 = $this->service->releaseBlock($released1, 'second_release', $this->admin);
    expect($released2->notes)->toBe('Original note | Released: first_release');
});

test('expireStaleHolds sweeps across multiple vehicles in independent transactions', function (): void {
    $v1 = Vehicle::factory()->create();
    $v2 = Vehicle::factory()->create();

    $start = Carbon::parse('2026-12-01 10:00:00', 'Asia/Colombo');
    $end = Carbon::parse('2026-12-01 12:00:00', 'Asia/Colombo');

    $hold1 = $this->service->createHold($v1, $start, $end, ServiceType::AirportPickup);
    $hold2 = $this->service->createHold($v2, $start, $end, ServiceType::AirportTransfer);

    // Fast forward 16 minutes
    Carbon::setTestNow(Carbon::now('Asia/Colombo')->addMinutes(16));

    $expiredCount = $this->service->expireStaleHolds();
    expect($expiredCount)->toBe(2);

    $hold1->hold->refresh();
    $hold2->hold->refresh();

    expect($hold1->hold->status)->toBe(HoldStatus::Expired)
        ->and($hold2->hold->status)->toBe(HoldStatus::Expired);

    Carbon::setTestNow();
});

test('cleanupStaleBlocks purges expired blocks and associated holds older than retention threshold', function (): void {
    $vehicle = Vehicle::factory()->create();
    $start = Carbon::parse('2026-10-01 10:00:00', 'Asia/Colombo');
    $end = Carbon::parse('2026-10-01 12:00:00', 'Asia/Colombo');

    $block = VehicleScheduleBlock::factory()->create([
        'vehicle_id' => $vehicle->id,
        'kind' => ScheduleBlockKind::CheckoutHold,
        'status' => ScheduleBlockStatus::Expired,
        'blocks_availability' => false,
        'updated_at' => Carbon::now('Asia/Colombo')->subDays(45),
    ]);

    $hold = BookingHold::factory()->create([
        'vehicle_id' => $vehicle->id,
        'schedule_block_id' => $block->id,
        'status' => HoldStatus::Expired,
        'updated_at' => Carbon::now('Asia/Colombo')->subDays(45),
    ]);

    $cleaned = $this->service->cleanupStaleBlocks(Carbon::now('Asia/Colombo')->subDays(30));
    expect($cleaned)->toBe(1);

    expect(VehicleScheduleBlock::where('id', $block->id)->exists())->toBeFalse()
        ->and(BookingHold::where('id', $hold->id)->exists())->toBeFalse();
});
