<?php

declare(strict_types=1);

use App\Domain\Availability\Enums\ScheduleBlockKind;
use App\Domain\Availability\Enums\ScheduleBlockStatus;
use App\Domain\Availability\Services\AvailabilityService;
use App\Domain\Booking\Enums\BookingStatus;
use App\Domain\Booking\Enums\ConfirmationChannel;
use App\Domain\Booking\Services\BookingLifecycleService;
use App\Domain\Identity\Permission;
use App\Models\AuditEvent;
use App\Models\Booking;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleScheduleBlock;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed();
    $this->lifecycleService = app(BookingLifecycleService::class);
    $this->availabilityService = app(AvailabilityService::class);

    $this->staff = User::create([
        'name' => 'Support Staff',
        'email' => 'support@spinellanka.test',
        'password' => Hash::make('Password123456!'),
        'is_active' => true,
        'token_version' => 1,
    ]);
    $this->staff->givePermissionTo(Permission::BookingReview->value);
    $this->staff->givePermissionTo(Permission::BookingConfirm->value);
    $this->staff->givePermissionTo(Permission::BookingCancel->value);
});

test('staff lifecycle transitions: mark under review, confirm with communication notes, decline, cancel', function (): void {
    $vehicle = Vehicle::factory()->create([
        'eligible_for_pickup' => true,
    ]);

    $start = Carbon::now('Asia/Colombo')->addDay()->setHour(10)->setMinute(0);
    $end = (clone $start)->addHours(2);

    $block = VehicleScheduleBlock::factory()->create([
        'vehicle_id' => $vehicle->id,
        'kind' => ScheduleBlockKind::SubmittedRequest,
        'status' => ScheduleBlockStatus::Active,
        'starts_at' => $start,
        'ends_at' => $end,
        'effective_start' => $start,
        'effective_end' => $end,
        'blocks_availability' => true,
        'expires_at' => Carbon::now('Asia/Colombo')->addHours(24),
    ]);

    $booking = Booking::factory()->create([
        'vehicle_id' => $vehicle->id,
        'schedule_block_id' => $block->id,
        'status' => BookingStatus::Submitted,
    ]);

    // 1. Mark under review
    $this->lifecycleService->markUnderReview($booking, $this->staff);
    expect($booking->fresh()->status)->toBe(BookingStatus::UnderReview)
        ->and($booking->fresh()->assigned_staff_id)->toBe($this->staff->id);

    // 2. Staff confirms booking
    $confirmedBooking = $this->lifecycleService->confirm(
        booking: $booking->fresh(),
        staff: $this->staff,
        channel: ConfirmationChannel::WhatsApp,
        notes: 'Confirmed pickup details with lead traveler on WhatsApp (+447700900123).'
    );

    expect($confirmedBooking->status)->toBe(BookingStatus::Confirmed)
        ->and($confirmedBooking->confirmed_at)->not->toBeNull()
        ->and($confirmedBooking->confirmation_channel)->toBe(ConfirmationChannel::WhatsApp)
        ->and($confirmedBooking->confirmation_notes)->toContain('Confirmed pickup details')
        ->and($confirmedBooking->scheduleBlock->kind)->toBe(ScheduleBlockKind::ConfirmedBooking)
        ->and($confirmedBooking->scheduleBlock->expires_at)->toBeNull();

    expect(AuditEvent::where('action', 'booking.confirmed')->exists())->toBeTrue();
});

test('declining booking releases schedule block and records reason', function (): void {
    $vehicle = Vehicle::factory()->create();
    $start = Carbon::now('Asia/Colombo')->addDay();
    $end = (clone $start)->addHours(2);

    $block = VehicleScheduleBlock::factory()->create([
        'vehicle_id' => $vehicle->id,
        'kind' => ScheduleBlockKind::SubmittedRequest,
        'status' => ScheduleBlockStatus::Active,
        'starts_at' => $start,
        'ends_at' => $end,
        'effective_start' => $start,
        'effective_end' => $end,
        'blocks_availability' => true,
    ]);

    $booking = Booking::factory()->create([
        'vehicle_id' => $vehicle->id,
        'schedule_block_id' => $block->id,
        'status' => BookingStatus::Submitted,
    ]);

    $this->lifecycleService->decline($booking, $this->staff, 'No drivers available due to regional weather advisory.');

    expect($booking->fresh()->status)->toBe(BookingStatus::Declined)
        ->and($booking->fresh()->decline_reason)->toContain('weather advisory')
        ->and($block->fresh()->status)->toBe(ScheduleBlockStatus::Released)
        ->and($block->fresh()->blocks_availability)->toBeFalse();

    expect(AuditEvent::where('action', 'booking.declined')->exists())->toBeTrue();
});

test('expire-pending command releases schedule blocks for stale requests', function (): void {
    $vehicle = Vehicle::factory()->create();
    $block = VehicleScheduleBlock::factory()->create([
        'vehicle_id' => $vehicle->id,
        'kind' => ScheduleBlockKind::SubmittedRequest,
        'status' => ScheduleBlockStatus::Active,
        'blocks_availability' => true,
    ]);

    $staleBooking = Booking::factory()->create([
        'vehicle_id' => $vehicle->id,
        'schedule_block_id' => $block->id,
        'status' => BookingStatus::Submitted,
        'expires_at' => Carbon::now('Asia/Colombo')->subMinute(), // Stale
    ]);

    Artisan::call('booking:expire-pending');

    expect($staleBooking->fresh()->status)->toBe(BookingStatus::Expired)
        ->and($block->fresh()->status)->toBe(ScheduleBlockStatus::Released)
        ->and($block->fresh()->blocks_availability)->toBeFalse();

    expect(AuditEvent::where('action', 'booking.expired')->exists())->toBeTrue();
});

test('expire-pending command does not expire bookings under review', function (): void {
    $vehicle = Vehicle::factory()->create();
    $block = VehicleScheduleBlock::factory()->create([
        'vehicle_id' => $vehicle->id,
        'kind' => ScheduleBlockKind::SubmittedRequest,
        'status' => ScheduleBlockStatus::Active,
        'blocks_availability' => true,
    ]);

    $underReviewBooking = Booking::factory()->create([
        'vehicle_id' => $vehicle->id,
        'schedule_block_id' => $block->id,
        'status' => BookingStatus::UnderReview,
        'expires_at' => Carbon::now('Asia/Colombo')->subMinute(),
    ]);

    Artisan::call('booking:expire-pending');

    expect($underReviewBooking->fresh()->status)->toBe(BookingStatus::UnderReview)
        ->and($block->fresh()->status)->toBe(ScheduleBlockStatus::Active)
        ->and($block->fresh()->blocks_availability)->toBeTrue();
});

test('confirming expired booking not under review throws exception', function (): void {
    $vehicle = Vehicle::factory()->create();
    $block = VehicleScheduleBlock::factory()->create([
        'vehicle_id' => $vehicle->id,
        'kind' => ScheduleBlockKind::SubmittedRequest,
        'status' => ScheduleBlockStatus::Active,
        'blocks_availability' => true,
    ]);

    $expiredBooking = Booking::factory()->create([
        'vehicle_id' => $vehicle->id,
        'schedule_block_id' => $block->id,
        'status' => BookingStatus::Submitted,
        'expires_at' => Carbon::now('Asia/Colombo')->subMinute(),
    ]);

    expect(fn () => $this->lifecycleService->confirm(
        booking: $expiredBooking,
        staff: $this->staff,
        channel: ConfirmationChannel::WhatsApp,
        notes: 'Trying to confirm stale booking.'
    ))->toThrow(InvalidArgumentException::class, 'has expired and was not placed under review');
});

test('confirming booking with sibling group blocks confirms all blocks in group', function (): void {
    $vehicle = Vehicle::factory()->create(['eligible_for_ride' => true]);
    $groupId = (string) Str::uuid();

    $outboundBlock = VehicleScheduleBlock::factory()->create([
        'vehicle_id' => $vehicle->id,
        'group_id' => $groupId,
        'kind' => ScheduleBlockKind::SubmittedRequest,
        'status' => ScheduleBlockStatus::Active,
        'starts_at' => Carbon::now('Asia/Colombo')->addDays(2),
        'ends_at' => Carbon::now('Asia/Colombo')->addDays(2)->addHours(3),
        'effective_start' => Carbon::now('Asia/Colombo')->addDays(2),
        'effective_end' => Carbon::now('Asia/Colombo')->addDays(2)->addHours(3),
        'blocks_availability' => true,
        'expires_at' => Carbon::now('Asia/Colombo')->addHours(24),
    ]);

    $returnBlock = VehicleScheduleBlock::factory()->create([
        'vehicle_id' => $vehicle->id,
        'group_id' => $groupId,
        'kind' => ScheduleBlockKind::SubmittedRequest,
        'status' => ScheduleBlockStatus::Active,
        'starts_at' => Carbon::now('Asia/Colombo')->addDays(4),
        'ends_at' => Carbon::now('Asia/Colombo')->addDays(4)->addHours(3),
        'effective_start' => Carbon::now('Asia/Colombo')->addDays(4),
        'effective_end' => Carbon::now('Asia/Colombo')->addDays(4)->addHours(3),
        'blocks_availability' => true,
        'expires_at' => Carbon::now('Asia/Colombo')->addHours(24),
    ]);

    $booking = Booking::factory()->create([
        'vehicle_id' => $vehicle->id,
        'schedule_block_id' => $outboundBlock->id,
        'status' => BookingStatus::Submitted,
    ]);

    $this->lifecycleService->confirm(
        booking: $booking,
        staff: $this->staff,
        channel: ConfirmationChannel::WhatsApp,
        notes: 'Confirmed 2-leg booking with traveler.'
    );

    expect($outboundBlock->fresh()->kind)->toBe(ScheduleBlockKind::ConfirmedBooking)
        ->and($outboundBlock->fresh()->expires_at)->toBeNull()
        ->and($returnBlock->fresh()->kind)->toBe(ScheduleBlockKind::ConfirmedBooking)
        ->and($returnBlock->fresh()->expires_at)->toBeNull();
});

test('cancelling or declining booking with sibling group blocks releases all blocks in group', function (): void {
    $vehicle = Vehicle::factory()->create(['eligible_for_ride' => true]);
    $groupId = (string) Str::uuid();

    $outboundBlock = VehicleScheduleBlock::factory()->create([
        'vehicle_id' => $vehicle->id,
        'group_id' => $groupId,
        'kind' => ScheduleBlockKind::SubmittedRequest,
        'status' => ScheduleBlockStatus::Active,
        'blocks_availability' => true,
    ]);

    $returnBlock = VehicleScheduleBlock::factory()->create([
        'vehicle_id' => $vehicle->id,
        'group_id' => $groupId,
        'kind' => ScheduleBlockKind::SubmittedRequest,
        'status' => ScheduleBlockStatus::Active,
        'blocks_availability' => true,
    ]);

    $booking = Booking::factory()->create([
        'vehicle_id' => $vehicle->id,
        'schedule_block_id' => $outboundBlock->id,
        'status' => BookingStatus::Submitted,
    ]);

    $this->lifecycleService->decline($booking, $this->staff, 'No chauffeurs available for return leg.');

    expect($outboundBlock->fresh()->status)->toBe(ScheduleBlockStatus::Released)
        ->and($outboundBlock->fresh()->blocks_availability)->toBeFalse()
        ->and($returnBlock->fresh()->status)->toBe(ScheduleBlockStatus::Released)
        ->and($returnBlock->fresh()->blocks_availability)->toBeFalse();
});
