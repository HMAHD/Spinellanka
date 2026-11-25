<?php

declare(strict_types=1);

use App\Domain\Booking\Enums\BookingStatus;
use App\Domain\Booking\ServiceType;
use App\Domain\Catalog\Enums\VehicleStatus;
use App\Domain\Identity\Permission;
use App\Filament\Resources\Operations\Bookings\Pages\ManageBookings;
use App\Filament\Resources\Operations\RentPolicies\Pages\ManageRentPolicies;
use App\Filament\Resources\Operations\RentPolicies\RentPolicyResource;
use App\Models\Booking;
use App\Models\RentDetail;
use App\Models\RentPolicy;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Spatie\Permission\Models\Permission::firstOrCreate(['name' => Permission::ConfigManage->value, 'guard_name' => 'web']);
    Spatie\Permission\Models\Permission::firstOrCreate(['name' => Permission::BookingView->value, 'guard_name' => 'web']);
    Spatie\Permission\Models\Permission::firstOrCreate(['name' => Permission::BookingConfirm->value, 'guard_name' => 'web']);

    $this->policy = RentPolicy::factory()->create([
        'min_driver_age' => 23,
        'max_driver_age' => 70,
        'min_licence_years' => 2,
        'security_deposit_minor_units' => 50000,
        'is_active' => true,
    ]);

    $this->vehicle = Vehicle::factory()->create([
        'public_name' => 'Toyota Land Cruiser Prado',
        'status' => VehicleStatus::Active,
        'eligible_for_rent' => true,
        'turnaround_buffer_minutes' => 60,
    ]);
});

test('staff with config.manage permission can access rent policy resource', function (): void {
    $staff = User::factory()->create();
    $staff->givePermissionTo(Permission::ConfigManage->value);

    $this->actingAs($staff);

    expect(RentPolicyResource::canViewAny())->toBeTrue();

    Livewire::test(ManageRentPolicies::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$this->policy]);
});

test('staff without config.manage cannot access rent policy resource', function (): void {
    $staff = User::factory()->create();

    $this->actingAs($staff);

    expect(RentPolicyResource::canViewAny())->toBeFalse();
});

test('admin can update rent policy and changes immediately update current policy', function (): void {
    $staff = User::factory()->create();
    $staff->givePermissionTo(Permission::ConfigManage->value);

    $this->actingAs($staff);

    // Update policy values
    $this->policy->update([
        'min_driver_age' => 25,
        'security_deposit_minor_units' => 60000, // $600 USD
        'daily_mileage_km' => 150,
    ]);

    $currentPolicy = RentPolicy::current();
    expect($currentPolicy->min_driver_age)->toBe(25)
        ->and($currentPolicy->security_deposit_minor_units)->toBe(60000)
        ->and($currentPolicy->daily_mileage_km)->toBe(150);
});

test('booking resource displays rent details for rent booking', function (): void {
    $staff = User::factory()->create();
    $staff->givePermissionTo(Permission::BookingView->value);

    $this->actingAs($staff);

    $booking = Booking::factory()->create([
        'service_type' => ServiceType::Rent,
        'status' => BookingStatus::Submitted,
        'vehicle_id' => $this->vehicle->id,
        'total_amount_minor_units' => 75000,
    ]);

    $pickup = Carbon::now('Asia/Colombo')->addDays(3)->setHour(10)->setMinute(0);
    $return = (clone $pickup)->addDays(4);

    RentDetail::create([
        'booking_id' => $booking->id,
        'pickup_datetime' => $pickup,
        'return_datetime' => $return,
        'duration_days' => 4,
        'extra_hours' => 0,
        'pickup_location_type' => 'office',
        'pickup_location_text' => 'Spinellanka Head Office, Colombo 03',
        'return_location_type' => 'office',
        'return_location_text' => 'Spinellanka Head Office, Colombo 03',
        'driver_age' => 32,
        'driver_licence_country' => 'United Kingdom',
        'driver_licence_years' => 8,
        'has_idp' => true,
        'has_additional_driver' => false,
        'included_distance_km' => 400,
        'security_deposit_minor_units' => 50000,
        'insurance_excess_minor_units' => 50000,
        'original_documents_acknowledged' => true,
    ]);

    Livewire::test(ManageBookings::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$booking]);
});

test('staff with booking.confirm can create manual rent booking from manage bookings page', function (): void {
    $staff = User::factory()->create();
    $staff->givePermissionTo(Permission::BookingView->value);
    $staff->givePermissionTo(Permission::BookingConfirm->value);

    $this->actingAs($staff);

    $pickup = Carbon::now('Asia/Colombo')->addDays(2)->setHour(10)->setMinute(0);
    $return = (clone $pickup)->addDays(3);

    Livewire::test(ManageBookings::class)
        ->callAction('manual_rent_booking', [
            'source' => 'manual_phone',
            'vehicle_id' => $this->vehicle->id,
            'pickup_datetime' => $pickup->format('Y-m-d H:i:s'),
            'return_datetime' => $return->format('Y-m-d H:i:s'),
            'pickup_location_type' => 'office',
            'pickup_location_text' => 'Spinellanka Head Office, Colombo 03',
            'return_location_type' => 'office',
            'return_location_text' => 'Spinellanka Head Office, Colombo 03',
            'driver_age' => 30,
            'driver_licence_country' => 'United Kingdom',
            'driver_licence_years' => 6,
            'booker_first_name' => 'Henry',
            'booker_last_name' => 'Cavill',
            'booker_phone' => '+44 7700 900555',
            'confirmation_channel' => 'phone',
            'confirmation_notes' => 'Confirmed customer telephone booking.',
        ])
        ->assertHasNoActionErrors();

    $booking = Booking::where('service_type', ServiceType::Rent)
        ->where('status', BookingStatus::Confirmed)
        ->latest('id')
        ->first();

    expect($booking)->not->toBeNull()
        ->and($booking->assigned_staff_id)->toBe($staff->id)
        ->and($booking->rentDetail)->not->toBeNull()
        ->and($booking->rentDetail->duration_days)->toBe(3)
        ->and($booking->scheduleBlock)->not->toBeNull()
        ->and($booking->scheduleBlock->driver_id)->toBeNull(); // Invariant 6
});

test('staff can record in-person original document inspection for rent booking', function (): void {
    $staff = User::factory()->create();
    $staff->givePermissionTo(Permission::BookingView->value);
    $staff->givePermissionTo(Permission::BookingConfirm->value);

    $this->actingAs($staff);

    $booking = Booking::factory()->create([
        'service_type' => ServiceType::Rent,
        'status' => BookingStatus::Submitted,
        'vehicle_id' => $this->vehicle->id,
    ]);

    $detail = RentDetail::factory()->create([
        'booking_id' => $booking->id,
        'original_documents_inspected' => false,
    ]);

    Livewire::test(ManageBookings::class)
        ->callTableAction('inspect_documents', $booking, [
            'original_documents_verified' => true,
            'inspection_notes' => 'Inspected physical UK driving licence and 1968 IDP at Colombo office.',
        ])
        ->assertHasNoTableActionErrors();

    $detail->refresh();
    expect($detail->original_documents_inspected)->toBeTrue()
        ->and($detail->original_documents_inspected_at)->not->toBeNull()
        ->and($detail->original_documents_inspected_by)->toBe($staff->id)
        ->and($detail->original_documents_inspection_notes)->toContain('Inspected physical UK driving licence');
});
