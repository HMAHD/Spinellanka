<?php

declare(strict_types=1);

namespace Tests\Feature\Payment;

use App\Domain\Booking\Enums\BookingStatus;
use App\Domain\Identity\Permission;
use App\Domain\Payment\Enums\PaymentMethod;
use App\Domain\Payment\Enums\PaymentStatus;
use App\Domain\Payment\Enums\PaymentType;
use App\Filament\Resources\Operations\Payments\PaymentTransactionResource;
use App\Models\Booking;
use App\Models\PaymentTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed();
});

test('staff with payment.view permission can view payment transactions ledger in filament', function (): void {
    $staff = User::factory()->create();
    $staff->givePermissionTo(Permission::PaymentView->value);

    $booking = Booking::factory()->create(['status' => BookingStatus::Confirmed]);
    PaymentTransaction::create([
        'booking_id' => $booking->id,
        'payment_method' => PaymentMethod::OnlineCard,
        'payment_type' => PaymentType::Full,
        'amount_minor_units' => 9900,
        'currency' => 'USD',
        'status' => PaymentStatus::Completed,
        'gateway_transaction_id' => 'GW-FILAMENT-01',
        'idempotency_key' => 'idemp-fil-01',
    ]);

    $this->actingAs($staff);

    expect(PaymentTransactionResource::canViewAny())->toBeTrue();
});

test('staff without permission is blocked from payment transactions ledger', function (): void {
    $staff = User::factory()->create();
    // No payment.view permission

    $this->actingAs($staff);

    expect(PaymentTransactionResource::canViewAny())->toBeFalse();
});
