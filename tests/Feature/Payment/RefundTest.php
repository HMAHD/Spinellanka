<?php

declare(strict_types=1);

namespace Tests\Feature\Payment;

use App\Domain\Booking\Enums\BookingStatus;
use App\Domain\Identity\Permission;
use App\Domain\Payment\DTOs\RefundData;
use App\Domain\Payment\Enums\PaymentMethod;
use App\Domain\Payment\Enums\PaymentStatus;
use App\Domain\Payment\Enums\PaymentType;
use App\Domain\Payment\Enums\RefundStatus;
use App\Domain\Payment\Exceptions\RefundExceedsSettledAmountException;
use App\Domain\Payment\Services\RefundService;
use App\Models\Booking;
use App\Models\PaymentTransaction;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed();
    $this->refundService = app(RefundService::class);
});

test('compensating refund creates negative transaction row and updates settled balance', function (): void {
    $staff = User::factory()->create();
    $staff->givePermissionTo(Permission::PaymentRefund->value);

    $booking = Booking::factory()->create([
        'status' => BookingStatus::Confirmed,
        'currency' => 'USD',
    ]);

    // Initial settled payment of 100.00 USD
    PaymentTransaction::create([
        'booking_id' => $booking->id,
        'payment_method' => PaymentMethod::Cash,
        'payment_type' => PaymentType::Full,
        'amount_minor_units' => 10000,
        'currency' => 'USD',
        'status' => PaymentStatus::Completed,
        'idempotency_key' => 'initial-tx-01',
    ]);

    expect($booking->totalSettledAmount())->toBe(10000);

    // Issue partial refund of 30.00 USD
    $refundData = new RefundData(
        amountMinorUnits: 3000,
        reason: 'Customer requested cancellation of optional excursion'
    );

    $refund = $this->refundService->issueRefund($booking, $staff, $refundData);

    expect($refund->status)->toBe(RefundStatus::Completed)
        ->and($refund->amount_minor_units)->toBe(3000)
        ->and($refund->reason)->toBe('Customer requested cancellation of optional excursion');

    // Compensating negative row in payment_transactions
    $this->assertDatabaseHas('payment_transactions', [
        'booking_id' => $booking->id,
        'payment_type' => PaymentType::Refund->value,
        'amount_minor_units' => -3000,
        'status' => PaymentStatus::Completed->value,
    ]);

    // Total settled balance is now 70.00 USD
    expect($booking->totalSettledAmount())->toBe(7000);

    // Audit event recorded
    $this->assertDatabaseHas('audit_events', [
        'action' => 'payment.refund_issued',
        'actor_id' => $staff->id,
    ]);
});

test('refund exceeding settled amount is rejected with exception', function (): void {
    $staff = User::factory()->create();
    $staff->givePermissionTo(Permission::PaymentRefund->value);

    $booking = Booking::factory()->create([
        'status' => BookingStatus::Confirmed,
        'currency' => 'USD',
    ]);

    // Initial payment of 50.00 USD
    PaymentTransaction::create([
        'booking_id' => $booking->id,
        'payment_method' => PaymentMethod::Cash,
        'payment_type' => PaymentType::Full,
        'amount_minor_units' => 5000,
        'currency' => 'USD',
        'status' => PaymentStatus::Completed,
        'idempotency_key' => 'tx-small-01',
    ]);

    // Attempt refund of 60.00 USD
    $refundData = new RefundData(
        amountMinorUnits: 6000,
        reason: 'Attempted excessive refund'
    );

    expect(fn () => $this->refundService->issueRefund($booking, $staff, $refundData))
        ->toThrow(RefundExceedsSettledAmountException::class);
});

test('full 100 percent refund zeroes booking totalSettledAmount and does not result in negative balance', function (): void {
    $staff = User::factory()->create();
    $staff->givePermissionTo(Permission::PaymentRefund->value);

    $booking = Booking::factory()->create([
        'status' => BookingStatus::Confirmed,
        'currency' => 'USD',
    ]);

    // Initial settlement of 100.00 USD
    $transaction = PaymentTransaction::create([
        'booking_id' => $booking->id,
        'payment_method' => PaymentMethod::OnlineCard,
        'payment_type' => PaymentType::Full,
        'amount_minor_units' => 10000,
        'currency' => 'USD',
        'status' => PaymentStatus::Completed,
        'idempotency_key' => 'tx-full-01',
        'gateway_provider' => 'fake',
        'gateway_transaction_id' => 'GW-FULL-01',
    ]);

    expect($booking->totalSettledAmount())->toBe(10000);

    // Issue 100% full refund of 100.00 USD
    $refundData = new RefundData(
        amountMinorUnits: 10000,
        reason: 'Full trip cancellation before cutoff'
    );

    $refund = $this->refundService->issueRefund($booking, $staff, $refundData);

    expect($refund->status)->toBe(RefundStatus::Completed)
        ->and($refund->amount_minor_units)->toBe(10000);

    // Original transaction should be marked Refunded
    $transaction->refresh();
    expect($transaction->status)->toBe(PaymentStatus::Refunded);

    // Invariant: total settled amount MUST be exactly 0, NOT negative (-10000)
    expect($booking->totalSettledAmount())->toBe(0);
});

test('refund with invalid staff password confirmation throws authorization exception', function (): void {
    $staff = User::factory()->create([
        'password' => Hash::make('correct_password'),
    ]);
    $staff->givePermissionTo(Permission::PaymentRefund->value);

    $booking = Booking::factory()->create(['status' => BookingStatus::Confirmed]);
    PaymentTransaction::create([
        'booking_id' => $booking->id,
        'payment_method' => PaymentMethod::OnlineCard,
        'payment_type' => PaymentType::Full,
        'amount_minor_units' => 5000,
        'currency' => 'USD',
        'status' => PaymentStatus::Completed,
        'idempotency_key' => 'tx-pw-check-01',
    ]);

    $refundData = new RefundData(
        amountMinorUnits: 2000,
        reason: 'Cancellation',
        staffPasswordConfirmation: 'wrong_password'
    );

    expect(fn () => $this->refundService->issueRefund($booking, $staff, $refundData))
        ->toThrow(AuthorizationException::class, 'Staff password re-authentication failed.');
});
