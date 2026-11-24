<?php

declare(strict_types=1);

use App\Domain\Availability\Enums\ScheduleBlockKind;
use App\Domain\Availability\Enums\ScheduleBlockStatus;
use App\Domain\Booking\DTOs\AirportPickupCriteria;
use App\Domain\Booking\Enums\BookingSource;
use App\Domain\Booking\Enums\BookingStatus;
use App\Domain\Booking\Enums\ConfirmationChannel;
use App\Domain\Booking\Services\ManualBookingService;
use App\Domain\Identity\Permission;
use App\Models\AuditEvent;
use App\Models\Driver;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleDriverAssignment;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed();
    $this->manualBookingService = app(ManualBookingService::class);

    $this->staff = User::create([
        'name' => 'Operations Officer',
        'email' => 'ops@spinellanka.test',
        'password' => Hash::make('Password123456!'),
        'is_active' => true,
        'token_version' => 1,
    ]);
    $this->staff->givePermissionTo(Permission::BookingConfirm->value);
    $this->staff->givePermissionTo(Permission::BookingReview->value);
});

test('staff can create manual confirmed airport pickup booking', function (): void {
    $vehicle = Vehicle::factory()->create([
        'eligible_for_pickup' => true,
    ]);

    $driver = Driver::factory()->create();
    VehicleDriverAssignment::create([
        'vehicle_id' => $vehicle->id,
        'driver_id' => $driver->id,
        'is_permanent' => true,
        'starts_at' => Carbon::now('Asia/Colombo')->subDays(10),
    ]);

    $arrivalTime = Carbon::now('Asia/Colombo')->addDays(2)->setHour(11)->setMinute(0);
    $criteria = new AirportPickupCriteria(
        airport: 'CMB',
        flightNumber: 'EK650',
        arrivalTime: $arrivalTime,
        destinationText: 'Kandy View Hotel, Kandy',
        passengerCount: 2,
        luggageCount: 2,
    );

    $manualData = [
        'source' => BookingSource::ManualPhone,
        'criteria' => $criteria,
        'vehicle_id' => $vehicle->id,
        'estimate_reference' => null,
        'booker_first_name' => 'Sarah',
        'booker_last_name' => 'Connor',
        'booker_phone' => '+14155552671',
        'booker_is_whatsapp' => true,
        'booker_email' => 'sarah@example.com',
        'confirmation_channel' => ConfirmationChannel::Phone,
        'confirmation_notes' => 'Guest booked via phone call. Agreed to standard rate.',
    ];

    $booking = $this->manualBookingService->createManualAirportPickup($manualData, $this->staff);

    expect($booking->status)->toBe(BookingStatus::Confirmed)
        ->and($booking->source)->toBe(BookingSource::ManualPhone)
        ->and($booking->assigned_staff_id)->toBe($this->staff->id)
        ->and($booking->scheduleBlock->kind)->toBe(ScheduleBlockKind::ConfirmedBooking)
        ->and($booking->scheduleBlock->status)->toBe(ScheduleBlockStatus::Active)
        ->and($booking->airportPickupDetail->flight_number)->toBe('EK650')
        ->and($booking->contacts)->toHaveCount(2);

    expect(AuditEvent::where('action', 'booking.manual_created')->exists())->toBeTrue();
});
