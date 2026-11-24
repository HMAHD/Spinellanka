<?php

declare(strict_types=1);

use App\Domain\Availability\Enums\HoldStatus;
use App\Domain\Availability\Enums\ScheduleBlockKind;
use App\Domain\Availability\Enums\ScheduleBlockStatus;
use App\Domain\Availability\Exceptions\ScheduleConflictException;
use App\Domain\Availability\Services\AvailabilityService;
use App\Domain\Booking\Enums\BookingStatus;
use App\Domain\Booking\ServiceType;
use App\Domain\Identity\Permission;
use App\Models\AuditEvent;
use App\Models\Booking;
use App\Models\BookingHold;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed();
    $this->availabilityService = app(AvailabilityService::class);

    $this->admin = User::create([
        'name' => 'Admin User',
        'email' => 'admin@spinellanka.test',
        'password' => Hash::make('Password123456!'),
        'is_active' => true,
        'token_version' => 1,
    ]);
    $this->admin->givePermissionTo(Permission::BookingOverrideConflict->value);

    $this->staff = User::create([
        'name' => 'Staff Member',
        'email' => 'staff@spinellanka.test',
        'password' => Hash::make('Password123456!'),
        'is_active' => true,
        'token_version' => 1,
    ]);
    $this->staff->givePermissionTo(Permission::BookingReview->value);
    $this->staff->givePermissionTo(Permission::BookingConfirm->value);
});

test('active checkout hold converts atomically to submitted request block', function (): void {
    $vehicle = Vehicle::factory()->create([
        'eligible_for_pickup' => true,
        'turnaround_buffer_minutes' => 60,
    ]);

    $start = Carbon::now('Asia/Colombo')->addDay()->setHour(10)->setMinute(0);
    $end = (clone $start)->addHours(3);

    $holdResult = $this->availabilityService->createHold(
        vehicle: $vehicle,
        start: $start,
        end: $end,
        service: ServiceType::AirportPickup,
    );

    $booking = Booking::factory()->create([
        'vehicle_id' => $vehicle->id,
        'status' => BookingStatus::Submitted,
    ]);

    $pendingExpiresAt = Carbon::now('Asia/Colombo')->addHours(24);

    $block = $this->availabilityService->convertHoldToSubmittedBlock(
        plainHoldToken: $holdResult->plainToken,
        expiresAt: $pendingExpiresAt,
        sourceType: Booking::class,
        sourceId: $booking->id,
    );

    expect($block->kind)->toBe(ScheduleBlockKind::SubmittedRequest)
        ->and($block->status)->toBe(ScheduleBlockStatus::Active)
        ->and($block->blocks_availability)->toBeTrue()
        ->and($block->source_type)->toBe(Booking::class)
        ->and($block->source_id)->toBe($booking->id)
        ->and($block->expires_at->toDateTimeString())->toBe($pendingExpiresAt->toDateTimeString());

    $hold = BookingHold::where('id', $holdResult->hold->id)->first();
    expect($hold->status)->toBe(HoldStatus::Converted)
        ->and($hold->released_at)->not->toBeNull();

    expect(AuditEvent::where('action', 'availability.hold.converted_to_request')->exists())->toBeTrue();
});

test('expired checkout hold cannot be converted to submitted request', function (): void {
    $vehicle = Vehicle::factory()->create([
        'eligible_for_pickup' => true,
        'turnaround_buffer_minutes' => 60,
    ]);

    $start = Carbon::now('Asia/Colombo')->addDay()->setHour(10)->setMinute(0);
    $end = (clone $start)->addHours(3);

    $holdResult = $this->availabilityService->createHold(
        vehicle: $vehicle,
        start: $start,
        end: $end,
        service: ServiceType::AirportPickup,
    );

    // Fast-forward time past 15-minute hold TTL
    Carbon::setTestNow(Carbon::now('Asia/Colombo')->addMinutes(16));

    $booking = Booking::factory()->create([
        'vehicle_id' => $vehicle->id,
    ]);

    try {
        $this->availabilityService->convertHoldToSubmittedBlock(
            plainHoldToken: $holdResult->plainToken,
            expiresAt: Carbon::now('Asia/Colombo')->addHours(24),
            sourceType: Booking::class,
            sourceId: $booking->id,
        );
        $this->fail('Expected ScheduleConflictException was not thrown.');
    } catch (ScheduleConflictException $e) {
        expect($e->getMessage())->toContain('expired or is no longer active');
    } finally {
        Carbon::setTestNow();
    }
});

test('staff can confirm submitted request block into confirmed booking', function (): void {
    $vehicle = Vehicle::factory()->create([
        'eligible_for_pickup' => true,
        'turnaround_buffer_minutes' => 60,
    ]);

    $start = Carbon::now('Asia/Colombo')->addDay()->setHour(10)->setMinute(0);
    $end = (clone $start)->addHours(3);

    $holdResult = $this->availabilityService->createHold(
        vehicle: $vehicle,
        start: $start,
        end: $end,
        service: ServiceType::AirportPickup,
    );

    $booking = Booking::factory()->create([
        'vehicle_id' => $vehicle->id,
    ]);

    $block = $this->availabilityService->convertHoldToSubmittedBlock(
        plainHoldToken: $holdResult->plainToken,
        expiresAt: Carbon::now('Asia/Colombo')->addHours(24),
        sourceType: Booking::class,
        sourceId: $booking->id,
    );

    $confirmedBlock = $this->availabilityService->confirmSubmittedBlock(
        block: $block,
        actor: $this->staff,
    );

    expect($confirmedBlock->kind)->toBe(ScheduleBlockKind::ConfirmedBooking)
        ->and($confirmedBlock->status)->toBe(ScheduleBlockStatus::Active)
        ->and($confirmedBlock->expires_at)->toBeNull();

    expect(AuditEvent::where('action', 'availability.schedule_block.confirmed')->exists())->toBeTrue();
});
