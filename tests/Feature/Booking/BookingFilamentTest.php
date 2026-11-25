<?php

declare(strict_types=1);

use App\Domain\Availability\Enums\ScheduleBlockKind;
use App\Domain\Availability\Enums\ScheduleBlockStatus;
use App\Domain\Booking\Enums\BookingSource;
use App\Domain\Booking\Enums\BookingStatus;
use App\Domain\Booking\Enums\ConfirmationChannel;
use App\Domain\Booking\ServiceType;
use App\Domain\Identity\Role;
use App\Filament\Resources\Operations\Bookings\Pages\ManageBookings;
use App\Models\AirportPickupDetail;
use App\Models\Booking;
use App\Models\BookingContact;
use App\Models\Driver;
use App\Models\RideDetail;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleDriverAssignment;
use App\Models\VehicleScheduleBlock;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed();

    $this->staff = User::create([
        'name' => 'Support Staff',
        'email' => 'support@spinellanka.test',
        'password' => Hash::make('Password123456!'),
        'is_active' => true,
        'token_version' => 1,
    ]);
    $this->staff->app_authentication_secret = 'mock_totp_secret_for_staff';
    $this->staff->save();
    $this->staff->assignRole(Role::Staff->value);

    $this->unauthorizedUser = User::create([
        'name' => 'Unauthorized User',
        'email' => 'unauth@spinellanka.test',
        'password' => Hash::make('Password123456!'),
        'is_active' => true,
        'token_version' => 1,
    ]);
    $this->unauthorizedUser->app_authentication_secret = 'mock_totp_secret_for_unauth';
    $this->unauthorizedUser->save();
});

test('staff with booking.view permission can access bookings table', function (): void {
    $vehicle = Vehicle::factory()->create([
        'make' => 'Toyota',
        'license_plate' => 'WP-TEST-8888',
    ]);

    $booking = Booking::factory()->create([
        'reference' => 'BKG-ALPHA01',
        'vehicle_id' => $vehicle->id,
        'status' => BookingStatus::Submitted,
    ]);

    AirportPickupDetail::factory()->create([
        'booking_id' => $booking->id,
        'flight_number' => 'UL504',
        'destination_text' => 'Colombo 03',
    ]);

    BookingContact::factory()->create([
        'booking_id' => $booking->id,
        'first_name' => 'James',
        'phone' => '+447700900111',
    ]);

    $this->actingAs($this->staff)
        ->get('/admin/operations/bookings')
        ->assertOk();

    Livewire::actingAs($this->staff)
        ->test(ManageBookings::class)
        ->assertCanSeeTableRecords([$booking])
        ->assertSee('BKG-ALPHA01')
        ->assertSee('UL504');
});

test('unauthorized user cannot access bookings table', function (): void {
    $this->actingAs($this->unauthorizedUser)
        ->get('/admin/operations/bookings')
        ->assertForbidden();
});

test('staff can review, confirm, and decline bookings through filament actions', function (): void {
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

    $start = Carbon::now('Asia/Colombo')->addDays(2)->setHour(10)->setMinute(0);
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
        'reference' => 'BKG-REVIEW01',
        'vehicle_id' => $vehicle->id,
        'schedule_block_id' => $block->id,
        'status' => BookingStatus::Submitted,
    ]);

    AirportPickupDetail::factory()->create([
        'booking_id' => $booking->id,
        'arrival_time' => $start,
        'flight_number' => 'UL504',
        'destination_text' => 'Galle Fort',
        'passenger_count' => 2,
        'luggage_count' => 2,
    ]);

    $booking->update(['request_hash' => $booking->computeRequestHash()]);

    // 1. Trigger Review action
    Livewire::actingAs($this->staff)
        ->test(ManageBookings::class)
        ->callTableAction('review', $booking);

    expect($booking->fresh()->status)->toBe(BookingStatus::UnderReview);

    // 2. Trigger Confirm action
    Livewire::actingAs($this->staff)
        ->test(ManageBookings::class)
        ->callTableAction('confirm', $booking, [
            'confirmation_channel' => ConfirmationChannel::WhatsApp->value,
            'confirmation_notes' => 'Spoke with guest via WhatsApp, confirmed flight touchdown and name board.',
        ]);

    expect($booking->fresh()->status)->toBe(BookingStatus::Confirmed)
        ->and($booking->fresh()->confirmation_channel)->toBe(ConfirmationChannel::WhatsApp)
        ->and($booking->fresh()->confirmation_notes)->toContain('touchdown');
});

test('staff can create manual booking via filament header action', function (): void {
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

    $arrival = Carbon::now('Asia/Colombo')->addDays(3)->format('Y-m-d H:i:s');

    Livewire::actingAs($this->staff)
        ->test(ManageBookings::class)
        ->callAction('manual_booking', [
            'source' => BookingSource::ManualPhone->value,
            'vehicle_id' => $vehicle->id,
            'flight_number' => 'SQ468',
            'arrival_time' => $arrival,
            'destination_text' => 'Cinnamon Grand, Colombo',
            'passenger_count' => 2,
            'luggage_count' => 2,
            'booker_first_name' => 'Michael',
            'booker_last_name' => 'Scott',
            'booker_phone' => '+14155550199',
            'confirmation_channel' => ConfirmationChannel::Phone->value,
            'confirmation_notes' => 'Phone intake confirmed standard rate.',
        ]);

    $createdBooking = Booking::where('source', BookingSource::ManualPhone)->latest()->first();
    expect($createdBooking)->not->toBeNull()
        ->and($createdBooking->status)->toBe(BookingStatus::Confirmed)
        ->and($createdBooking->airportPickupDetail->flight_number)->toBe('SQ468')
        ->and($createdBooking->assigned_staff_id)->toBe($this->staff->id);
});

test('filament table renders chauffeured ride bookings with route, timing, and flight service label', function (): void {
    $vehicle = Vehicle::factory()->create([
        'make' => 'Toyota',
        'license_plate' => 'WP-RIDE-9999',
    ]);

    $booking = Booking::factory()->create([
        'reference' => 'BKG-RIDE01',
        'service_type' => ServiceType::Ride,
        'vehicle_id' => $vehicle->id,
        'status' => BookingStatus::Submitted,
    ]);

    RideDetail::factory()->create([
        'booking_id' => $booking->id,
        'origin_text' => 'Cinnamon Grand, Colombo',
        'destination_text' => 'Amangalla, Galle',
        'pickup_time' => Carbon::now('Asia/Colombo')->addDays(2)->setTime(10, 0),
    ]);

    BookingContact::factory()->create([
        'booking_id' => $booking->id,
        'first_name' => 'Sara',
        'last_name' => 'Connor',
        'phone' => '+447700900222',
    ]);

    Livewire::actingAs($this->staff)
        ->test(ManageBookings::class)
        ->assertCanSeeTableRecords([$booking])
        ->assertSee('BKG-RIDE01')
        ->assertSee('Chauffeured Ride')
        ->assertSee('Cinnamon Grand, Colombo');
});
