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
use Illuminate\Foundation\Testing\RefreshDatabase;

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
