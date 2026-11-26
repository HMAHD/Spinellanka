<?php

declare(strict_types=1);

namespace Tests\Feature\Payment;

use App\Domain\Audit\Services\AuditService;
use App\Domain\Booking\Enums\BookingStatus;
use App\Domain\Booking\Enums\ProposalStatus;
use App\Domain\Booking\Services\BookingLifecycleService;
use App\Domain\Payment\Contracts\HostedPaymentGatewayInterface;
use App\Domain\Payment\DTOs\PaymentVerificationResult;
use App\Domain\Payment\Enums\FinancialDocumentType;
use App\Domain\Payment\Enums\PaymentMethod;
use App\Domain\Payment\Enums\PaymentStatus;
use App\Domain\Payment\Enums\PaymentType;
use App\Domain\Payment\Services\FinancialDocumentService;
use App\Domain\Payment\Services\PaymentReconciliationService;
use App\Models\Booking;
use App\Models\PaymentTransaction;
use App\Models\Proposal;
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

test('reconciliation recovers missed settlement advances booking to confirmed and generates receipt', function (): void {
    $booking = Booking::factory()->create(['status' => BookingStatus::AwaitingPayment]);
    $proposal = Proposal::factory()->create([
        'booking_id' => $booking->id,
        'status' => ProposalStatus::Sent,
        'expires_at' => Carbon::now()->addDay(),
        'total_amount_minor_units' => 5000,
        'currency' => 'USD',
    ]);

    $transaction = PaymentTransaction::create([
        'booking_id' => $booking->id,
        'proposal_id' => $proposal->id,
        'payment_method' => PaymentMethod::OnlineCard,
        'payment_type' => PaymentType::Full,
        'amount_minor_units' => 5000,
        'currency' => 'USD',
        'status' => PaymentStatus::Pending,
        'idempotency_key' => 'recon-recover-01',
        'created_at' => Carbon::now()->subHours(3),
    ]);

    // Mock the gateway to return successful query result
    $mockGateway = \Mockery::mock(HostedPaymentGatewayInterface::class);
    $mockGateway->shouldReceive('queryTransaction')
        ->with($transaction->reference)
        ->andReturn(new PaymentVerificationResult(
            isValid: true,
            provider: 'fake',
            providerEventId: 'RECON-EVT-01',
            providerTransactionId: 'RECON-TXN-01',
            orderReference: $transaction->reference,
            amountMinorUnits: 5000,
            currency: 'USD',
            statusCode: '2',
            rawPayload: []
        ));

    $reconciliationService = new PaymentReconciliationService(
        gateway: $mockGateway,
        auditService: app(AuditService::class),
        lifecycleService: app(BookingLifecycleService::class),
        documentService: app(FinancialDocumentService::class)
    );

    $count = $reconciliationService->sweepStalePendingTransactions(timeoutHours: 2);
    expect($count)->toBe(1);

    $transaction->refresh();
    expect($transaction->status)->toBe(PaymentStatus::Completed)
        ->and($transaction->gateway_transaction_id)->toBe('RECON-TXN-01');

    $booking->refresh();
    expect($booking->status)->toBe(BookingStatus::Confirmed);

    $this->assertDatabaseHas('financial_documents', [
        'booking_id' => $booking->id,
        'document_type' => FinancialDocumentType::Receipt->value,
    ]);
});

test('console command payments:reconcile triggers sweep', function (): void {
    $this->artisan('payments:reconcile --timeout-hours=2')
        ->expectsOutputToContain('Sweeping stale pending payment transactions')
        ->assertSuccessful();
});
