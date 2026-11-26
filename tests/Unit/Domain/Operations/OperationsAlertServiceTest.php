<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Operations;

use App\Domain\Availability\Enums\ScheduleBlockKind;
use App\Domain\Availability\Enums\ScheduleBlockStatus;
use App\Domain\Booking\Enums\BookingStatus;
use App\Domain\Booking\Enums\ProposalStatus;
use App\Domain\Booking\ServiceType;
use App\Domain\Messaging\Enums\MessageDeliveryStatus;
use App\Domain\Operations\Enums\OperationalAlertSeverity;
use App\Domain\Operations\Services\OperationsAlertService;
use App\Models\AirportPickupDetail;
use App\Models\Booking;
use App\Models\MessageDelivery;
use App\Models\Proposal;
use App\Models\Vehicle;
use App\Models\VehicleScheduleBlock;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->alertService = app(OperationsAlertService::class);
});

test('alert service detects overlapping schedule conflicts on vehicle', function (): void {
    $vehicle = Vehicle::factory()->create(['public_name' => 'HiAce High Roof']);

    $now = Carbon::now('Asia/Colombo')->addHours(2);

    VehicleScheduleBlock::factory()->create([
        'vehicle_id' => $vehicle->id,
        'kind' => ScheduleBlockKind::ConfirmedBooking,
        'status' => ScheduleBlockStatus::Active,
        'starts_at' => $now,
        'ends_at' => $now->copy()->addHours(3),
        'effective_start' => $now,
        'effective_end' => $now->copy()->addHours(3),
        'group_id' => 'grp-1',
        'blocks_availability' => true,
    ]);

    VehicleScheduleBlock::factory()->create([
        'vehicle_id' => $vehicle->id,
        'kind' => ScheduleBlockKind::Maintenance,
        'status' => ScheduleBlockStatus::Active,
        'starts_at' => $now->copy()->addHour(),
        'ends_at' => $now->copy()->addHours(4),
        'effective_start' => $now->copy()->addHour(),
        'effective_end' => $now->copy()->addHours(4),
        'group_id' => 'grp-2',
        'blocks_availability' => true,
    ]);

    $conflicts = $this->alertService->getConflictAlerts();
    expect($conflicts)->not->toBeEmpty();
    expect($conflicts[0]->severity)->toBe(OperationalAlertSeverity::Critical);
    expect($conflicts[0]->title)->toContain('Schedule Conflict');
});

test('alert service detects imminent checkout and proposal deadlines', function (): void {
    // 1. Imminent checkout hold block expiring in 3 minutes
    $vehicle = Vehicle::factory()->create();
    VehicleScheduleBlock::factory()->create([
        'vehicle_id' => $vehicle->id,
        'kind' => ScheduleBlockKind::CheckoutHold,
        'status' => ScheduleBlockStatus::Active,
        'starts_at' => Carbon::now('Asia/Colombo'),
        'ends_at' => Carbon::now('Asia/Colombo')->addHours(1),
        'effective_start' => Carbon::now('Asia/Colombo'),
        'effective_end' => Carbon::now('Asia/Colombo')->addHours(1),
        'expires_at' => Carbon::now('Asia/Colombo')->addMinutes(3),
        'blocks_availability' => true,
    ]);

    // 2. Proposal expiring in 45 minutes
    $booking = Booking::factory()->create(['status' => BookingStatus::UnderReview]);
    Proposal::factory()->create([
        'booking_id' => $booking->id,
        'status' => ProposalStatus::Sent,
        'expires_at' => Carbon::now('Asia/Colombo')->addMinutes(45),
    ]);

    $deadlines = $this->alertService->getDeadlineAlerts();
    expect($deadlines)->toHaveCount(2);
    expect($deadlines[0]->severity)->toBe(OperationalAlertSeverity::Warning);
});

test('alert service detects unassigned confirmed chauffeured trips within 48 hours', function (): void {
    $vehicle = Vehicle::factory()->create();

    $serviceDate = Carbon::now('Asia/Colombo')->addHours(24);

    $booking = Booking::factory()->create([
        'vehicle_id' => $vehicle->id,
        'service_type' => ServiceType::AirportPickup->value,
        'status' => BookingStatus::Confirmed,
        'assigned_driver_id' => null,
    ]);

    AirportPickupDetail::create([
        'booking_id' => $booking->id,
        'airport' => 'CMB',
        'flight_number' => 'UL-102',
        'arrival_time' => $serviceDate,
        'passenger_count' => 2,
        'luggage_count' => 2,
        'destination_text' => 'Colombo City Center',
    ]);

    VehicleScheduleBlock::factory()->create([
        'vehicle_id' => $vehicle->id,
        'source_type' => Booking::class,
        'source_id' => $booking->id,
        'kind' => ScheduleBlockKind::ConfirmedBooking,
        'status' => ScheduleBlockStatus::Active,
        'starts_at' => $serviceDate,
        'ends_at' => $serviceDate->copy()->addHours(2),
        'effective_start' => $serviceDate,
        'effective_end' => $serviceDate->copy()->addHours(2),
        'blocks_availability' => true,
    ]);

    $unassigned = $this->alertService->getUnassignedTripAlerts();
    expect($unassigned)->toHaveCount(1);
    expect($unassigned[0]->severity)->toBe(OperationalAlertSeverity::Critical);
    expect($unassigned[0]->title)->toContain('Unassigned Chauffeured Trip');
});

test('alert service detects delivery failures and stale requests', function (): void {
    // Stale request older than 24h
    Booking::factory()->create([
        'status' => BookingStatus::Submitted,
        'created_at' => Carbon::now('Asia/Colombo')->subHours(30),
    ]);

    // Message delivery failure
    MessageDelivery::factory()->create([
        'status' => MessageDeliveryStatus::Failed,
    ]);

    $stale = $this->alertService->getStaleRequestAlerts();
    expect($stale)->toHaveCount(1);

    $failures = $this->alertService->getDeliveryFailureAlerts();
    expect($failures)->toHaveCount(1);
});
