<?php

declare(strict_types=1);

use App\Domain\Booking\Enums\BookingStatus;
use App\Domain\Booking\Enums\ProposalPaymentChoice;
use App\Domain\Booking\ServiceType;
use App\Domain\Catalog\Enums\VehicleStatus;
use App\Domain\Identity\Permission;
use App\Domain\Identity\Role;
use App\Filament\Resources\Operations\Bookings\Pages\ManageBookings;
use App\Models\Booking;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed();

    $this->staff = User::create([
        'name' => 'Operations Manager',
        'email' => 'ops@spinellanka.test',
        'password' => Hash::make('Password123456!'),
        'is_active' => true,
        'token_version' => 1,
    ]);
    $this->staff->app_authentication_secret = 'mock_totp_secret_for_staff';
    $this->staff->save();
    $this->staff->assignRole(Role::Staff->value);
    $this->staff->givePermissionTo(Permission::BookingView->value);
    $this->staff->givePermissionTo(Permission::BookingReview->value);
    $this->staff->givePermissionTo(Permission::BookingConfirm->value);
});

test('staff can create and send proposal from filament actions', function (): void {
    $vehicle = Vehicle::factory()->create([
        'status' => VehicleStatus::Active,
        'make' => 'Toyota',
        'model' => 'Prius',
        'license_plate' => 'WP-CAD-5555',
    ]);

    $booking = Booking::factory()->create([
        'status' => BookingStatus::UnderReview,
        'vehicle_id' => $vehicle->id,
        'service_type' => ServiceType::AirportPickup,
    ]);

    Livewire::actingAs($this->staff)
        ->test(ManageBookings::class)
        ->callTableAction('create_proposal', $booking, [
            'vehicle_id' => $vehicle->id,
            'total_amount' => 85.00,
            'deposit_required' => false,
            'payment_choice' => ProposalPaymentChoice::FullOrPayLater->value,
            'expires_in_hours' => '48',
            'staff_notes' => 'Airport meet & greet with placard included.',
            'internal_notes' => 'Fleet allocation confirmed with driver.',
        ])
        ->assertHasNoTableActionErrors();

    $booking->refresh();
    expect($booking->status)->toBe(BookingStatus::ProposalSent)
        ->and($booking->currentProposal)->not->toBeNull()
        ->and($booking->currentProposal->version)->toBe(1)
        ->and($booking->currentProposal->total_amount_minor_units)->toBe(8500)
        ->and($booking->currentProposal->staff_notes)->toBe('Airport meet & greet with placard included.');
});

test('staff can rotate guest link from filament with password re-authentication', function (): void {
    $booking = Booking::factory()->create([
        'status' => BookingStatus::Submitted,
    ]);

    // Invalid password fails
    Livewire::actingAs($this->staff)
        ->test(ManageBookings::class)
        ->callTableAction('rotate_guest_link', $booking, [
            'reauth_password' => 'WrongPassword!',
        ])
        ->assertHasTableActionErrors(['reauth_password']);

    // Valid password succeeds
    Livewire::actingAs($this->staff)
        ->test(ManageBookings::class)
        ->callTableAction('rotate_guest_link', $booking, [
            'reauth_password' => 'Password123456!',
        ])
        ->assertHasNoTableActionErrors();

    $booking->refresh();
    expect($booking->guestAccessTokens)->toHaveCount(1)
        ->and($booking->guestAccessTokens->first()->isValid())->toBeTrue();
});
