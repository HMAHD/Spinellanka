<?php

declare(strict_types=1);

namespace Tests\Feature\Operations;

use App\Domain\Booking\Enums\BookingStatus;
use App\Domain\Booking\Services\BookingLifecycleService;
use App\Models\AuditEvent;
use App\Models\Booking;
use App\Models\OutboxEvent;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed();
    $this->lifecycleService = app(BookingLifecycleService::class);
});

test('complete transitions confirmed booking to completed with audit and outbox event', function (): void {
    $vehicle = Vehicle::factory()->create();

    $booking = Booking::factory()->create([
        'reference' => 'BK-COMPL-001',
        'vehicle_id' => $vehicle->id,
        'status' => BookingStatus::Confirmed,
        'completed_at' => null,
    ]);

    $completedBooking = $this->lifecycleService->complete($booking);

    expect($completedBooking->status)->toBe(BookingStatus::Completed)
        ->and($completedBooking->completed_at)->not->toBeNull();

    // Verify audit event
    $audit = AuditEvent::where('auditable_type', Booking::class)
        ->where('auditable_id', $booking->id)
        ->where('action', 'booking.completed')
        ->first();

    expect($audit)->not->toBeNull()
        ->and($audit->actor_type)->toBe('driver');

    // Verify outbox event
    $outbox = OutboxEvent::where('event_type', 'booking.completed')
        ->where('aggregate_id', $booking->id)
        ->first();

    expect($outbox)->not->toBeNull()
        ->and($outbox->idempotency_key)->toBe("evt_booking_{$booking->id}_completed_v1");
});

test('complete throws DomainException if booking is not confirmed', function (): void {
    $vehicle = Vehicle::factory()->create();

    $submittedBooking = Booking::factory()->create([
        'vehicle_id' => $vehicle->id,
        'status' => BookingStatus::Submitted,
    ]);

    expect(fn () => $this->lifecycleService->complete($submittedBooking))
        ->toThrow(InvalidArgumentException::class, 'cannot be completed from status submitted');

    $cancelledBooking = Booking::factory()->create([
        'vehicle_id' => $vehicle->id,
        'status' => BookingStatus::Cancelled,
    ]);

    expect(fn () => $this->lifecycleService->complete($cancelledBooking))
        ->toThrow(InvalidArgumentException::class, 'cannot be completed from status cancelled');
});

test('complete rejects concurrently cancelled booking under row lock', function (): void {
    $vehicle = Vehicle::factory()->create();

    $booking = Booking::factory()->create([
        'reference' => 'BK-CONCUR-01',
        'vehicle_id' => $vehicle->id,
        'status' => BookingStatus::Confirmed,
    ]);

    // Simulate in-memory copy looks confirmed, but database was updated to cancelled
    Booking::where('id', $booking->id)->update(['status' => BookingStatus::Cancelled]);

    expect(fn () => $this->lifecycleService->complete($booking))
        ->toThrow(InvalidArgumentException::class, 'cannot be completed from status cancelled');

    expect($booking->fresh()->status)->toBe(BookingStatus::Cancelled);
});
