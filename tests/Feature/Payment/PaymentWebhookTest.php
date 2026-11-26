<?php

declare(strict_types=1);

namespace Tests\Feature\Payment;

use App\Domain\Availability\Enums\ScheduleBlockKind;
use App\Domain\Availability\Enums\ScheduleBlockStatus;
use App\Domain\Availability\Services\AvailabilityService;
use App\Domain\Booking\Enums\BookingStatus;
use App\Domain\Booking\Enums\ProposalPaymentChoice;
use App\Domain\Booking\Enums\ProposalStatus;
use App\Domain\Booking\ServiceType;
use App\Domain\Catalog\Enums\VehicleStatus;
use App\Domain\Payment\Enums\PaymentMethod;
use App\Domain\Payment\Enums\PaymentStatus;
use App\Domain\Payment\Enums\PaymentType;
use App\Domain\Payment\Services\PaymentWebhookService;
use App\Models\Booking;
use App\Models\FinancialDocument;
use App\Models\PaymentTransaction;
use App\Models\Proposal;
use App\Models\Vehicle;
use App\Models\VehicleScheduleBlock;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed();
    $this->webhookService = app(PaymentWebhookService::class);
    $this->availabilityService = app(AvailabilityService::class);
});

test('valid webhook settles transaction, records receipt, confirms booking, and generates receipt document', function (): void {
    config()->set('services.payhere.merchant_id', '1211144');
    config()->set('services.payhere.merchant_secret', 'secretpass123');

    $vehicle = Vehicle::factory()->create(['status' => VehicleStatus::Active]);
    $booking = Booking::factory()->create([
        'status' => BookingStatus::AwaitingPayment,
        'vehicle_id' => $vehicle->id,
        'service_type' => ServiceType::AirportPickup,
        'total_amount_minor_units' => 10000,
        'currency' => 'USD',
    ]);

    // Active proposal hold schedule block
    $block = VehicleScheduleBlock::factory()->create([
        'vehicle_id' => $vehicle->id,
        'source_type' => Booking::class,
        'source_id' => $booking->id,
        'starts_at' => Carbon::now()->addDays(2),
        'ends_at' => Carbon::now()->addDays(3),
        'kind' => ScheduleBlockKind::ProposalHold,
        'status' => ScheduleBlockStatus::Active,
    ]);

    $proposal = Proposal::factory()->create([
        'booking_id' => $booking->id,
        'status' => ProposalStatus::Accepted,
        'vehicle_id' => $vehicle->id,
        'schedule_block_id' => $block->id,
        'total_amount_minor_units' => 10000,
        'currency' => 'USD',
        'deposit_required' => true,
        'deposit_amount_minor_units' => 2500,
        'payment_choice' => ProposalPaymentChoice::FullOrDeposit,
    ]);

    $booking->update([
        'current_proposal_id' => $proposal->id,
        'schedule_block_id' => $block->id,
    ]);

    $idempotencyKey = 'payhere-order-001';
    $transaction = PaymentTransaction::create([
        'booking_id' => $booking->id,
        'proposal_id' => $proposal->id,
        'payment_method' => PaymentMethod::OnlineCard,
        'payment_type' => PaymentType::Deposit,
        'amount_minor_units' => 2500,
        'currency' => 'USD',
        'status' => PaymentStatus::Pending,
        'idempotency_key' => $idempotencyKey,
    ]);

    $merchantId = '1211144';
    $secret = 'secretpass123';
    $amount = '25.00';
    $currency = 'USD';
    $statusCode = '2'; // success
    $md5Sig = strtoupper(md5($merchantId.$idempotencyKey.$amount.$currency.$statusCode.strtoupper(md5($secret))));

    $payload = [
        'merchant_id' => $merchantId,
        'order_id' => $idempotencyKey,
        'payment_id' => '320025112233',
        'payhere_amount' => $amount,
        'payhere_currency' => $currency,
        'status_code' => $statusCode,
        'md5sig' => $md5Sig,
        'method' => 'VISA',
        'status_message' => 'Successfully completed',
    ];

    $response = $this->postJson(route('webhooks.payment', ['provider' => 'payhere']), $payload);

    $response->assertOk();
    $response->assertJson([
        'status' => 'success',
        'transaction_id' => $transaction->id,
    ]);

    // Assert transaction completed
    $transaction->refresh();
    expect($transaction->status)->toBe(PaymentStatus::Completed)
        ->and($transaction->gateway_transaction_id)->toBe('320025112233')
        ->and($transaction->settled_at)->not->toBeNull();

    // Assert booking confirmed
    $booking->refresh();
    expect($booking->status)->toBe(BookingStatus::Confirmed);

    // Assert schedule block upgraded to Confirmed
    $this->assertDatabaseHas('vehicle_schedule_blocks', [
        'source_id' => $booking->id,
        'source_type' => Booking::class,
        'kind' => ScheduleBlockKind::ConfirmedBooking->value,
        'status' => ScheduleBlockStatus::Active->value,
    ]);

    // Assert webhook receipt logged
    $this->assertDatabaseHas('webhook_receipts', [
        'provider' => 'payhere',
        'event_id' => '320025112233',
        'signature_valid' => true,
    ]);

    // Assert payment event logged
    $this->assertDatabaseHas('payment_events', [
        'payment_transaction_id' => $transaction->id,
        'provider' => 'payhere',
        'provider_event_id' => '320025112233',
        'status' => 'processed',
    ]);

    // Assert financial document (receipt) issued
    $this->assertDatabaseHas('financial_documents', [
        'booking_id' => $booking->id,
        'payment_transaction_id' => $transaction->id,
        'document_type' => 'receipt',
        'amount_minor_units' => 2500,
        'currency' => 'USD',
    ]);
});

test('duplicate webhook delivery is idempotent and does not recreate documents or reconfirm booking', function (): void {
    config()->set('services.payhere.merchant_id', '1211144');
    config()->set('services.payhere.merchant_secret', 'secretpass123');

    $vehicle = Vehicle::factory()->create(['status' => VehicleStatus::Active]);
    $booking = Booking::factory()->create([
        'status' => BookingStatus::AwaitingPayment,
        'vehicle_id' => $vehicle->id,
    ]);

    $proposal = Proposal::factory()->create([
        'booking_id' => $booking->id,
        'status' => ProposalStatus::Accepted,
        'total_amount_minor_units' => 6000,
        'currency' => 'USD',
        'deposit_required' => false,
    ]);

    $booking->update(['current_proposal_id' => $proposal->id]);

    $idempotencyKey = 'idemp-duplicate-test';
    $transaction = PaymentTransaction::create([
        'booking_id' => $booking->id,
        'proposal_id' => $proposal->id,
        'payment_method' => PaymentMethod::OnlineCard,
        'payment_type' => PaymentType::Full,
        'amount_minor_units' => 6000,
        'currency' => 'USD',
        'status' => PaymentStatus::Pending,
        'idempotency_key' => $idempotencyKey,
    ]);

    $md5Sig = strtoupper(md5('1211144'.$idempotencyKey.'60.00'.'USD'.'2'.strtoupper(md5('secretpass123'))));

    $payload = [
        'merchant_id' => '1211144',
        'order_id' => $idempotencyKey,
        'payment_id' => '999888777',
        'payhere_amount' => '60.00',
        'payhere_currency' => 'USD',
        'status_code' => '2',
        'md5sig' => $md5Sig,
    ];

    // First delivery
    $firstResponse = $this->postJson(route('webhooks.payment', ['provider' => 'payhere']), $payload);
    $firstResponse->assertOk();

    $docCountFirst = FinancialDocument::count();
    expect($docCountFirst)->toBe(1);

    // Second delivery with identical event_id
    $secondResponse = $this->postJson(route('webhooks.payment', ['provider' => 'payhere']), $payload);
    $secondResponse->assertOk();
    $secondResponse->assertJson(['status' => 'already_processed']);

    // Ensure document count is STILL 1
    expect(FinancialDocument::count())->toBe(1);
});

test('invalid webhook signature returns HTTP 400 and leaves transaction pending', function (): void {
    config()->set('services.payhere.merchant_id', '1211144');
    config()->set('services.payhere.merchant_secret', 'secretpass123');

    $booking = Booking::factory()->create(['status' => BookingStatus::AwaitingPayment]);
    $transaction = PaymentTransaction::create([
        'booking_id' => $booking->id,
        'payment_method' => PaymentMethod::OnlineCard,
        'payment_type' => PaymentType::Full,
        'amount_minor_units' => 5000,
        'currency' => 'USD',
        'status' => PaymentStatus::Pending,
        'idempotency_key' => 'idemp-bad-sig',
    ]);

    $payload = [
        'merchant_id' => '1211144',
        'order_id' => 'idemp-bad-sig',
        'payment_id' => '111222333',
        'payhere_amount' => '50.00',
        'payhere_currency' => 'USD',
        'status_code' => '2',
        'md5sig' => 'TAMPERED_OR_FORGED_SIGNATURE',
    ];

    $response = $this->postJson(route('webhooks.payment', ['provider' => 'payhere']), $payload);
    $response->assertStatus(400);

    $transaction->refresh();
    expect($transaction->status)->toBe(PaymentStatus::Pending);

    $booking->refresh();
    expect($booking->status)->toBe(BookingStatus::AwaitingPayment);
});

test('negative status code marks transaction failed and never confirms booking', function (): void {
    config()->set('services.payhere.merchant_id', '1211144');
    config()->set('services.payhere.merchant_secret', 'secretpass123');

    $booking = Booking::factory()->create(['status' => BookingStatus::AwaitingPayment]);
    $transaction = PaymentTransaction::create([
        'booking_id' => $booking->id,
        'payment_method' => PaymentMethod::OnlineCard,
        'payment_type' => PaymentType::Full,
        'amount_minor_units' => 5000,
        'currency' => 'USD',
        'status' => PaymentStatus::Pending,
        'idempotency_key' => 'idemp-failed-tx',
    ]);

    // Status code -2 = Failed
    $statusCode = '-2';
    $md5Sig = strtoupper(md5('1211144'.'idemp-failed-tx'.'50.00'.'USD'.$statusCode.strtoupper(md5('secretpass123'))));

    $payload = [
        'merchant_id' => '1211144',
        'order_id' => 'idemp-failed-tx',
        'payment_id' => 'fail_999888',
        'payhere_amount' => '50.00',
        'payhere_currency' => 'USD',
        'status_code' => $statusCode,
        'md5sig' => $md5Sig,
        'status_message' => 'Insufficient funds',
    ];

    $response = $this->postJson(route('webhooks.payment', ['provider' => 'payhere']), $payload);
    $response->assertOk();
    $response->assertJson(['status' => 'failed']);

    $transaction->refresh();
    expect($transaction->status)->toBe(PaymentStatus::Failed);

    $booking->refresh();
    expect($booking->status)->toBe(BookingStatus::AwaitingPayment);
});

test('expired proposal webhook settlement completes transaction but does not confirm booking into expired hold', function (): void {
    config()->set('services.payhere.merchant_id', '1211144');
    config()->set('services.payhere.merchant_secret', 'secretpass123');

    $booking = Booking::factory()->create(['status' => BookingStatus::Expired]);
    $proposal = Proposal::factory()->create([
        'booking_id' => $booking->id,
        'status' => ProposalStatus::Expired,
        'total_amount_minor_units' => 5000,
        'currency' => 'USD',
        'expires_at' => Carbon::now()->subHour(),
    ]);

    $idempotencyKey = 'idemp-expired-proposal';
    $transaction = PaymentTransaction::create([
        'booking_id' => $booking->id,
        'proposal_id' => $proposal->id,
        'payment_method' => PaymentMethod::OnlineCard,
        'payment_type' => PaymentType::Full,
        'amount_minor_units' => 5000,
        'currency' => 'USD',
        'status' => PaymentStatus::Pending,
        'idempotency_key' => $idempotencyKey,
    ]);

    $md5Sig = strtoupper(md5('1211144'.$idempotencyKey.'50.00'.'USD'.'2'.strtoupper(md5('secretpass123'))));

    $payload = [
        'merchant_id' => '1211144',
        'order_id' => $idempotencyKey,
        'payment_id' => 'captured_late_123',
        'payhere_amount' => '50.00',
        'payhere_currency' => 'USD',
        'status_code' => '2',
        'md5sig' => $md5Sig,
    ];

    $response = $this->postJson(route('webhooks.payment', ['provider' => 'payhere']), $payload);
    $response->assertOk();
    $response->assertJson(['status' => 'settled_unassigned']);

    // Money is captured, so transaction is Completed
    $transaction->refresh();
    expect($transaction->status)->toBe(PaymentStatus::Completed);

    // But booking MUST NOT be confirmed into an expired hold!
    $booking->refresh();
    expect($booking->status)->toBe(BookingStatus::Expired);

    // Audit event for staff alert recorded
    $this->assertDatabaseHas('audit_events', [
        'action' => 'payment.settled_on_expired_proposal',
    ]);
});
