<?php

declare(strict_types=1);

namespace Tests\Feature\Payment;

use App\Domain\Availability\Enums\ScheduleBlockKind;
use App\Domain\Availability\Enums\ScheduleBlockStatus;
use App\Domain\Availability\Services\AvailabilityService;
use App\Domain\Booking\Enums\BookingStatus;
use App\Domain\Booking\Enums\ProposalStatus;
use App\Domain\Booking\ServiceType;
use App\Domain\Catalog\Enums\VehicleStatus;
use App\Domain\Identity\Permission;
use App\Domain\Payment\DTOs\OfflinePaymentData;
use App\Domain\Payment\Enums\PaymentMethod;
use App\Domain\Payment\Enums\PaymentStatus;
use App\Domain\Payment\Enums\PaymentType;
use App\Domain\Payment\Services\OfflinePaymentService;
use App\Models\Booking;
use App\Models\Proposal;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleScheduleBlock;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed();
    $this->offlinePaymentService = app(OfflinePaymentService::class);
    $this->availabilityService = app(AvailabilityService::class);
});

test('staff with permission can record offline cash payment and advance booking to confirmed', function (): void {
    $staff = User::factory()->create();
    $staff->givePermissionTo(Permission::PaymentRecordOffline->value);
    $staff->givePermissionTo(Permission::BookingConfirm->value);

    $vehicle = Vehicle::factory()->create(['status' => VehicleStatus::Active]);
    $booking = Booking::factory()->create([
        'status' => BookingStatus::AwaitingPayment,
        'vehicle_id' => $vehicle->id,
        'service_type' => ServiceType::AirportPickup,
        'total_amount_minor_units' => 7500,
        'currency' => 'USD',
    ]);

    $proposal = Proposal::factory()->create([
        'booking_id' => $booking->id,
        'status' => ProposalStatus::Accepted,
        'vehicle_id' => $vehicle->id,
        'total_amount_minor_units' => 7500,
        'currency' => 'USD',
        'deposit_required' => false,
    ]);

    $booking->update(['current_proposal_id' => $proposal->id]);

    VehicleScheduleBlock::factory()->create([
        'vehicle_id' => $vehicle->id,
        'source_type' => Booking::class,
        'source_id' => $booking->id,
        'starts_at' => Carbon::now()->addDays(1),
        'ends_at' => Carbon::now()->addDays(2),
        'kind' => ScheduleBlockKind::ProposalHold,
        'status' => ScheduleBlockStatus::Active,
    ]);

    $offlineData = new OfflinePaymentData(
        amountMinorUnits: 7500,
        currency: 'USD',
        paymentMethod: PaymentMethod::Cash,
        paymentType: PaymentType::Full,
        notes: 'Collected directly at desk upon arrival',
        externalReference: 'Cash Receipt #CMB-4412',
    );

    $transaction = $this->offlinePaymentService->recordOfflinePayment($booking, $staff, $offlineData);

    expect($transaction->status)->toBe(PaymentStatus::Completed)
        ->and($transaction->amount_minor_units)->toBe(7500)
        ->and($transaction->payment_method)->toBe(PaymentMethod::Cash);

    // Booking confirmed
    $booking->refresh();
    expect($booking->status)->toBe(BookingStatus::Confirmed);

    // Document issued
    $this->assertDatabaseHas('financial_documents', [
        'booking_id' => $booking->id,
        'document_type' => 'receipt',
        'amount_minor_units' => 7500,
    ]);

    // Audit logged
    $this->assertDatabaseHas('audit_events', [
        'action' => 'payment.offline_recorded',
        'actor_id' => $staff->id,
    ]);
});

test('staff without permission is blocked from recording offline payment', function (): void {
    $staff = User::factory()->create();
    // No payment.record_offline permission

    $booking = Booking::factory()->create([
        'status' => BookingStatus::AwaitingPayment,
    ]);

    $offlineData = new OfflinePaymentData(
        amountMinorUnits: 5000,
        currency: 'USD',
        paymentMethod: PaymentMethod::Cash,
    );

    expect(fn () => $this->offlinePaymentService->recordOfflinePayment($booking, $staff, $offlineData))
        ->toThrow(AuthorizationException::class);
});
