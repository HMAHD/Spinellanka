<?php

declare(strict_types=1);

use App\Domain\Availability\Enums\ScheduleBlockKind;
use App\Domain\Availability\Enums\ScheduleBlockStatus;
use App\Domain\Availability\Exceptions\ScheduleConflictException;
use App\Domain\Booking\DTOs\RideCriteria;
use App\Domain\Booking\DTOs\RideStopData;
use App\Domain\Booking\Enums\BookingSource;
use App\Domain\Booking\Enums\BookingStatus;
use App\Domain\Booking\Enums\ConfirmationChannel;
use App\Domain\Booking\Services\ManualBookingService;
use App\Domain\Booking\ServiceType;
use App\Domain\Catalog\Enums\DriverStatus;
use App\Domain\Catalog\Enums\VehicleStatus;
use App\Domain\Identity\Permission;
use App\Models\Booking;
use App\Models\Driver;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleDriverAssignment;
use App\Models\VehicleScheduleBlock;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed();
    $this->manualService = app(ManualBookingService::class);

    $this->staffUser = User::factory()->create();
    $this->staffUser->givePermissionTo(Permission::BookingConfirm->value);
});

test('staff can create a manual confirmed ride booking with stops', function (): void {
    $vehicle = Vehicle::factory()->create([
        'eligible_for_ride' => true,
        'status' => VehicleStatus::Active,
        'passenger_capacity' => 4,
        'luggage_capacity' => 4,
    ]);

    $driver = Driver::factory()->create(['status' => DriverStatus::Active]);
    VehicleDriverAssignment::create([
        'vehicle_id' => $vehicle->id,
        'driver_id' => $driver->id,
        'is_permanent' => true,
        'starts_at' => Carbon::now('Asia/Colombo')->subDays(5),
    ]);

    $pickupTime = Carbon::now('Asia/Colombo')->addDays(2)->setTime(9, 0);

    $criteria = new RideCriteria(
        pickupTime: $pickupTime,
        originText: 'Cinnamon Lakeside, Colombo',
        destinationText: 'Kandalama Hotel, Dambulla',
        passengerCount: 2,
        luggageCount: 2,
        stops: [
            new RideStopData(stopOrder: 1, locationText: 'Pinnawala Elephant Sanctuary', plannedWaitingMinutes: 90),
        ],
    );

    $booking = $this->manualService->createManualRide([
        'source' => BookingSource::ManualPhone,
        'criteria' => $criteria,
        'vehicle_id' => $vehicle->id,
        'booker_first_name' => 'Emma',
        'booker_last_name' => 'Watson',
        'booker_phone' => '+44 7911 123456',
        'confirmation_channel' => ConfirmationChannel::Phone,
        'confirmation_notes' => 'Confirmed via direct phone call. Client requested English-speaking chauffeur.',
    ], $this->staffUser);

    expect($booking)->not->toBeNull()
        ->and($booking->status)->toBe(BookingStatus::Confirmed)
        ->and($booking->service_type)->toBe(ServiceType::Ride)
        ->and($booking->assigned_staff_id)->toBe($this->staffUser->id)
        ->and($booking->confirmation_channel)->toBe(ConfirmationChannel::Phone);

    // Verify RideDetail and stops
    $booking->refresh();
    $detail = $booking->rideDetail;
    expect($detail)->not->toBeNull()
        ->and($detail->origin_text)->toBe('Cinnamon Lakeside, Colombo')
        ->and($detail->destination_text)->toBe('Kandalama Hotel, Dambulla')
        ->and($detail->stops)->toHaveCount(1)
        ->and($detail->stops[0]->location_text)->toBe('Pinnawala Elephant Sanctuary');

    // Verify schedule block exists
    $block = VehicleScheduleBlock::where('vehicle_id', $vehicle->id)
        ->where('source_id', $booking->id)
        ->where('source_type', Booking::class)
        ->where('kind', ScheduleBlockKind::ConfirmedBooking)
        ->first();
    expect($block)->not->toBeNull();
});

test('staff can create a manual confirmed ride booking with return leg atomically', function (): void {
    $vehicle = Vehicle::factory()->create([
        'eligible_for_ride' => true,
        'status' => VehicleStatus::Active,
        'passenger_capacity' => 4,
        'luggage_capacity' => 4,
    ]);

    $driver = Driver::factory()->create(['status' => DriverStatus::Active]);
    VehicleDriverAssignment::create([
        'vehicle_id' => $vehicle->id,
        'driver_id' => $driver->id,
        'is_permanent' => true,
        'starts_at' => Carbon::now('Asia/Colombo')->subDays(5),
    ]);

    $pickupTime = Carbon::now('Asia/Colombo')->addDays(3)->setTime(8, 0);
    $returnTime = Carbon::now('Asia/Colombo')->addDays(3)->setTime(17, 0);

    $criteria = new RideCriteria(
        pickupTime: $pickupTime,
        originText: 'Colombo Fort',
        destinationText: 'Kandy Lake Round',
        passengerCount: 2,
        luggageCount: 2,
        hasReturn: true,
        returnPickupTime: $returnTime,
    );

    $booking = $this->manualService->createManualRide([
        'source' => BookingSource::ManualWhatsApp,
        'criteria' => $criteria,
        'vehicle_id' => $vehicle->id,
        'booker_first_name' => 'Liam',
        'booker_last_name' => 'Neeson',
        'booker_phone' => '+44 7911 654321',
        'confirmation_channel' => ConfirmationChannel::WhatsApp,
        'confirmation_notes' => 'Confirmed via WhatsApp chat thread.',
    ], $this->staffUser);

    expect($booking->fresh()->rideDetail->has_return)->toBeTrue();

    // Verify two schedule blocks exist sharing a group_id
    $blocks = VehicleScheduleBlock::where('source_id', $booking->id)
        ->where('source_type', Booking::class)
        ->get();
    expect($blocks)->toHaveCount(2)
        ->and($blocks[0]->group_id)->not->toBeNull()
        ->and($blocks[0]->group_id)->toBe($blocks[1]->group_id);
});

test('manual booking fails if schedule conflict exists without override permission', function (): void {
    $vehicle = Vehicle::factory()->create([
        'eligible_for_ride' => true,
        'status' => VehicleStatus::Active,
        'passenger_capacity' => 4,
        'luggage_capacity' => 4,
        'turnaround_buffer_minutes' => 60,
    ]);

    $driver = Driver::factory()->create(['status' => DriverStatus::Active]);
    VehicleDriverAssignment::create([
        'vehicle_id' => $vehicle->id,
        'driver_id' => $driver->id,
        'is_permanent' => true,
        'starts_at' => Carbon::now('Asia/Colombo')->subDays(5),
    ]);

    $pickupTime = Carbon::now('Asia/Colombo')->addDays(2)->setTime(9, 0);

    // Create an existing conflicting block
    VehicleScheduleBlock::create([
        'vehicle_id' => $vehicle->id,
        'kind' => ScheduleBlockKind::ConfirmedBooking,
        'starts_at' => (clone $pickupTime)->subHours(1),
        'ends_at' => (clone $pickupTime)->addHours(4),
        'effective_start' => (clone $pickupTime)->subHours(2),
        'effective_end' => (clone $pickupTime)->addHours(5),
        'blocks_availability' => true,
        'status' => ScheduleBlockStatus::Active,
    ]);

    $criteria = new RideCriteria(
        pickupTime: $pickupTime,
        originText: 'Colombo',
        destinationText: 'Galle',
        passengerCount: 2,
        luggageCount: 2,
    );

    expect(fn () => $this->manualService->createManualRide([
        'source' => BookingSource::ManualPhone,
        'criteria' => $criteria,
        'vehicle_id' => $vehicle->id,
        'booker_first_name' => 'James',
        'booker_last_name' => 'Bond',
        'booker_phone' => '+44 7911 007007',
        'confirmation_channel' => ConfirmationChannel::Phone,
        'confirmation_notes' => 'Attempted booking over conflict.',
    ], $this->staffUser))->toThrow(ScheduleConflictException::class);
});
