<?php

declare(strict_types=1);

namespace Tests\Feature\Payment;

use App\Domain\Booking\Enums\BookingStatus;
use App\Domain\Payment\Enums\PaymentMethod;
use App\Domain\Payment\Enums\PaymentStatus;
use App\Domain\Payment\Enums\PaymentType;
use App\Domain\Payment\Services\PaymentReconciliationService;
use App\Models\Booking;
use App\Models\PaymentTransaction;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed();
    $this->reconciliationService = app(PaymentReconciliationService::class);
});

test('reconciliation service sweeps pending transactions older than timeout', function (): void {
    $booking = Booking::factory()->create(['status' => BookingStatus::AwaitingPayment]);

    // Stale transaction created 3 hours ago
    $staleTx = PaymentTransaction::create([
        'booking_id' => $booking->id,
        'payment_method' => PaymentMethod::OnlineCard,
        'payment_type' => PaymentType::Full,
        'amount_minor_units' => 4500,
        'currency' => 'USD',
        'status' => PaymentStatus::Pending,
        'idempotency_key' => 'stale-tx-01',
        'created_at' => Carbon::now()->subHours(3),
    ]);

    // Fresh transaction created 30 minutes ago
    $freshTx = PaymentTransaction::create([
        'booking_id' => $booking->id,
        'payment_method' => PaymentMethod::OnlineCard,
        'payment_type' => PaymentType::Full,
        'amount_minor_units' => 4500,
        'currency' => 'USD',
        'status' => PaymentStatus::Pending,
        'idempotency_key' => 'fresh-tx-01',
        'created_at' => Carbon::now()->subMinutes(30),
    ]);

    $count = $this->reconciliationService->sweepStalePendingTransactions(timeoutHours: 2);

    expect($count)->toBe(1);

    $staleTx->refresh();
    expect($staleTx->status)->toBe(PaymentStatus::Cancelled);

    $freshTx->refresh();
    expect($freshTx->status)->toBe(PaymentStatus::Pending);
});

test('console command payments:reconcile triggers sweep', function (): void {
    $this->artisan('payments:reconcile --timeout-hours=2')
        ->expectsOutputToContain('Sweeping stale pending payment transactions')
        ->assertSuccessful();
});
