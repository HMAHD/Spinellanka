<?php

declare(strict_types=1);

namespace Tests\Feature\Operations;

use App\Domain\Availability\Enums\ScheduleBlockKind;
use App\Domain\Availability\Enums\ScheduleBlockStatus;
use App\Domain\Booking\Enums\BookingStatus;
use App\Domain\Catalog\Enums\DriverStatus;
use App\Domain\Identity\Permission;
use App\Domain\Operations\Enums\DriverTripStatus;
use App\Domain\Operations\Services\DriverAssignmentService;
use App\Models\AuditEvent;
use App\Models\Booking;
use App\Models\Driver;
use App\Models\DriverAccessToken;
use App\Models\DriverAssignment;
use App\Models\OutboxEvent;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleDriverAssignment;
use App\Models\VehicleScheduleBlock;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed();
    $this->assignmentService = app(DriverAssignmentService::class);

    $this->staff = User::create([
        'name' => 'Dispatch Operator',
        'email' => 'dispatch@spinellanka.test',
        'password' => Hash::make('Password123456!'),
        'is_active' => true,
        'token_version' => 1,
    ]);
    $this->staff->givePermissionTo(Permission::BookingReview->value);
    $this->staff->givePermissionTo(Permission::BookingConfirm->value);
});

test('resolveTiedDriver resolves active driver tied to vehicle by effective date', function (): void {
    $vehicle = Vehicle::factory()->create();
    $driver = Driver::factory()->create([
        'status' => DriverStatus::Active,
        'public_name' => 'Sunil Perera',
    ]);

    $serviceDate = Carbon::parse('2026-12-10 10:00:00', 'Asia/Colombo');

    VehicleDriverAssignment::create([
        'vehicle_id' => $vehicle->id,
        'driver_id' => $driver->id,
        'starts_at' => Carbon::parse('2026-01-01', 'Asia/Colombo'),
        'ends_at' => Carbon::parse('2026-12-31', 'Asia/Colombo'),
        'is_permanent' => true,
    ]);

    $resolved = $this->assignmentService->resolveTiedDriver($vehicle, $serviceDate);
    expect($resolved)->not->toBeNull()
        ->and($resolved->id)->toBe($driver->id);
});

test('assignDriver assigns driver with governance, high-entropy token, audit and outbox events', function (): void {
    $vehicle = Vehicle::factory()->create();
    $driver = Driver::factory()->create([
        'status' => DriverStatus::Active,
        'public_name' => 'Kamal Silva',
    ]);

    $serviceStart = Carbon::now('Asia/Colombo')->addHours(24);
    $serviceEnd = $serviceStart->copy()->addHours(3);

    $block = VehicleScheduleBlock::factory()->create([
        'vehicle_id' => $vehicle->id,
        'kind' => ScheduleBlockKind::ConfirmedBooking,
        'status' => ScheduleBlockStatus::Active,
        'starts_at' => $serviceStart,
        'ends_at' => $serviceEnd,
        'effective_start' => $serviceStart,
        'effective_end' => $serviceEnd,
        'blocks_availability' => true,
    ]);

    $booking = Booking::factory()->create([
        'reference' => 'BK-ASSIGN-01',
        'vehicle_id' => $vehicle->id,
        'schedule_block_id' => $block->id,
        'status' => BookingStatus::Confirmed,
    ]);

    $result = $this->assignmentService->assignDriver(
        booking: $booking,
        driver: $driver,
        staff: $this->staff,
        isReplacement: false
    );

    expect($result)->toHaveKeys(['assignment', 'plainToken']);
    expect(strlen($result['plainToken']))->toBe(64); // 32 bytes hex = 64 characters

    $assignment = $result['assignment'];
    expect($assignment->booking_id)->toBe($booking->id)
        ->and($assignment->driver_id)->toBe($driver->id)
        ->and($assignment->status)->toBe(DriverTripStatus::Assigned)
        ->and($assignment->is_replacement)->toBeFalse();

    // Verify booking updated
    $booking->refresh();
    expect($booking->assigned_driver_id)->toBe($driver->id)
        ->and($booking->current_driver_assignment_id)->toBe($assignment->id);

    // Verify token stored as SHA-256 hash
    $tokenRecord = DriverAccessToken::where('driver_assignment_id', $assignment->id)->first();
    expect($tokenRecord)->not->toBeNull()
        ->and($tokenRecord->token_hash)->toBe(hash('sha256', $result['plainToken']))
        ->and($tokenRecord->isValid())->toBeTrue()
        ->and($tokenRecord->expires_at->gt($serviceEnd))->toBeTrue();

    // Verify audit recorded
    $audit = AuditEvent::where('auditable_type', DriverAssignment::class)
        ->where('auditable_id', $assignment->id)
        ->first();
    expect($audit)->not->toBeNull()
        ->and($audit->action)->toBe('driver.assigned');

    // Verify outbox recorded
    $outbox = OutboxEvent::where('event_type', 'driver.assigned')
        ->where('aggregate_id', $assignment->id)
        ->first();
    expect($outbox)->not->toBeNull();
});

test('assignDriver validates replacement reason and permissions for replacement driver', function (): void {
    $vehicle = Vehicle::factory()->create();
    $replacementDriver = Driver::factory()->create(['status' => DriverStatus::Active]);

    $booking = Booking::factory()->create([
        'vehicle_id' => $vehicle->id,
        'status' => BookingStatus::Confirmed,
    ]);

    // Missing reason should throw
    expect(fn () => $this->assignmentService->assignDriver(
        booking: $booking,
        driver: $replacementDriver,
        staff: $this->staff,
        isReplacement: true,
        replacementReason: null
    ))->toThrow(InvalidArgumentException::class, 'Replacement reason is required');

    // Staff without review/confirm permission should throw
    $unauthorizedStaff = User::create([
        'name' => 'Junior Staff',
        'email' => 'junior@spinellanka.test',
        'password' => Hash::make('Password123456!'),
        'is_active' => true,
        'token_version' => 1,
    ]);

    expect(fn () => $this->assignmentService->assignDriver(
        booking: $booking,
        driver: $replacementDriver,
        staff: $unauthorizedStaff,
        isReplacement: true,
        replacementReason: 'Tied driver ill'
    ))->toThrow(InvalidArgumentException::class, 'permission');

    // Authorized replacement succeeds
    $result = $this->assignmentService->assignDriver(
        booking: $booking,
        driver: $replacementDriver,
        staff: $this->staff,
        isReplacement: true,
        replacementReason: 'Tied driver on emergency medical leave'
    );

    expect($result['assignment']->is_replacement)->toBeTrue()
        ->and($result['assignment']->replacement_reason)->toBe('Tied driver on emergency medical leave')
        ->and($result['assignment']->replacement_authorized_by_user_id)->toBe($this->staff->id);
});

test('assignDriver rejects driver with conflicting assignment on another vehicle', function (): void {
    $vehicle1 = Vehicle::factory()->create();
    $vehicle2 = Vehicle::factory()->create();
    $driver = Driver::factory()->create(['status' => DriverStatus::Active]);

    $start = Carbon::now('Asia/Colombo')->addHours(10);
    $end = $start->copy()->addHours(4);

    // Active block on vehicle 2 already assigned to this driver
    VehicleScheduleBlock::factory()->create([
        'vehicle_id' => $vehicle2->id,
        'driver_id' => $driver->id,
        'kind' => ScheduleBlockKind::ConfirmedBooking,
        'status' => ScheduleBlockStatus::Active,
        'starts_at' => $start,
        'ends_at' => $end,
        'effective_start' => $start,
        'effective_end' => $end,
        'blocks_availability' => true,
    ]);

    $block1 = VehicleScheduleBlock::factory()->create([
        'vehicle_id' => $vehicle1->id,
        'kind' => ScheduleBlockKind::ConfirmedBooking,
        'status' => ScheduleBlockStatus::Active,
        'starts_at' => $start->copy()->addHour(),
        'ends_at' => $end->copy()->addHour(),
        'effective_start' => $start->copy()->addHour(),
        'effective_end' => $end->copy()->addHour(),
        'blocks_availability' => true,
    ]);

    $booking = Booking::factory()->create([
        'vehicle_id' => $vehicle1->id,
        'schedule_block_id' => $block1->id,
        'status' => BookingStatus::Confirmed,
    ]);

    expect(fn () => $this->assignmentService->assignDriver(
        booking: $booking,
        driver: $driver,
        staff: $this->staff
    ))->toThrow(InvalidArgumentException::class, 'already assigned to another vehicle');
});

test('rotateTripToken revokes old token and generates new valid token', function (): void {
    $vehicle = Vehicle::factory()->create();
    $driver = Driver::factory()->create(['status' => DriverStatus::Active]);

    $booking = Booking::factory()->create([
        'vehicle_id' => $vehicle->id,
        'status' => BookingStatus::Confirmed,
    ]);

    $initial = $this->assignmentService->assignDriver(
        booking: $booking,
        driver: $driver,
        staff: $this->staff
    );

    $oldTokenRecord = DriverAccessToken::where('driver_assignment_id', $initial['assignment']->id)->first();
    expect($oldTokenRecord->isValid())->toBeTrue();

    // Rotate
    $newPlainToken = $this->assignmentService->rotateTripToken(
        booking: $booking->fresh(),
        staff: $this->staff,
        reason: 'Driver misplaced WhatsApp link'
    );

    expect($newPlainToken)->not->toBe($initial['plainToken']);

    $oldTokenRecord->refresh();
    expect($oldTokenRecord->revoked_at)->not->toBeNull()
        ->and($oldTokenRecord->isValid())->toBeFalse();

    $newTokenRecord = DriverAccessToken::where('token_hash', hash('sha256', $newPlainToken))->first();
    expect($newTokenRecord)->not->toBeNull()
        ->and($newTokenRecord->isValid())->toBeTrue();
});

test('revokeDriverAccess invalidates tokens and marks assignment cancelled', function (): void {
    $vehicle = Vehicle::factory()->create();
    $driver = Driver::factory()->create(['status' => DriverStatus::Active]);

    $booking = Booking::factory()->create([
        'vehicle_id' => $vehicle->id,
        'status' => BookingStatus::Confirmed,
    ]);

    $initial = $this->assignmentService->assignDriver(
        booking: $booking,
        driver: $driver,
        staff: $this->staff
    );

    $this->assignmentService->revokeDriverAccess(
        booking: $booking->fresh(),
        staff: $this->staff,
        reason: 'Trip reassigned to alternative driver'
    );

    $assignment = $initial['assignment']->fresh();
    expect($assignment->status)->toBe(DriverTripStatus::Cancelled);

    $tokenRecord = DriverAccessToken::where('driver_assignment_id', $assignment->id)->first();
    expect($tokenRecord->revoked_at)->not->toBeNull()
        ->and($tokenRecord->isValid())->toBeFalse();
});

test('multi-leg grouped bookings calculate driver token expiry against final leg and check conflicts across all legs', function (): void {
    $v1 = Vehicle::factory()->create();
    $v2 = Vehicle::factory()->create();
    $driver = Driver::factory()->create(['status' => DriverStatus::Active]);

    $now = Carbon::now('Asia/Colombo');
    $leg1Start = $now->copy()->addDay()->setHour(9);
    $leg1End = $now->copy()->addDay()->setHour(12);

    $leg2Start = $now->copy()->addDays(3)->setHour(14);
    $leg2End = $now->copy()->addDays(3)->setHour(18);

    // Grouped blocks for Return Ride
    $block1 = VehicleScheduleBlock::factory()->create([
        'vehicle_id' => $v1->id,
        'kind' => ScheduleBlockKind::ConfirmedBooking,
        'status' => ScheduleBlockStatus::Active,
        'starts_at' => $leg1Start,
        'ends_at' => $leg1End,
        'effective_start' => $leg1Start,
        'effective_end' => $leg1End,
        'group_id' => 'grp-return-test',
        'blocks_availability' => true,
    ]);

    $block2 = VehicleScheduleBlock::factory()->create([
        'vehicle_id' => $v1->id,
        'kind' => ScheduleBlockKind::ConfirmedBooking,
        'status' => ScheduleBlockStatus::Active,
        'starts_at' => $leg2Start,
        'ends_at' => $leg2End,
        'effective_start' => $leg2Start,
        'effective_end' => $leg2End,
        'group_id' => 'grp-return-test',
        'blocks_availability' => true,
    ]);

    $booking = Booking::factory()->create([
        'vehicle_id' => $v1->id,
        'schedule_block_id' => $block1->id,
        'status' => BookingStatus::Confirmed,
    ]);

    $result = $this->assignmentService->assignDriver(
        booking: $booking,
        driver: $driver,
        staff: $this->staff
    );

    $token = DriverAccessToken::where('driver_assignment_id', $result['assignment']->id)->first();
    expect($token)->not->toBeNull();

    // Expiry must be 48 hours after Leg 2 end (not Leg 1)
    $expectedExpiry = $leg2End->copy()->addHours(48);
    expect($token->expires_at->format('Y-m-d H:i'))->toBe($expectedExpiry->format('Y-m-d H:i'));

    // Both blocks in the group must have driver_id assigned
    expect($block1->fresh()->driver_id)->toBe($driver->id)
        ->and($block2->fresh()->driver_id)->toBe($driver->id);
});
