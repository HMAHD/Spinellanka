<?php

declare(strict_types=1);

namespace Tests\Feature\Payment;

use App\Domain\Availability\Services\AvailabilityService;
use App\Domain\Booking\Enums\BookingStatus;
use App\Domain\Booking\Enums\ProposalPaymentChoice;
use App\Domain\Booking\Enums\ProposalStatus;
use App\Domain\Booking\ServiceType;
use App\Domain\Catalog\Enums\VehicleStatus;
use App\Domain\Payment\Adapters\PayHereHostedPaymentGateway;
use App\Domain\Payment\DTOs\CheckoutSession;
use App\Domain\Payment\Enums\PaymentMethod;
use App\Domain\Payment\Enums\PaymentStatus;
use App\Domain\Payment\Enums\PaymentType;
use App\Domain\Payment\Services\PaymentService;
use App\Models\Booking;
use App\Models\GuestAccessToken;
use App\Models\PaymentTransaction;
use App\Models\Proposal;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed();
    config()->set('services.payment.gateway', 'payhere');
    config()->set('services.payhere.merchant_id', '1211144');
    config()->set('services.payhere.merchant_secret', 'testsecretkey123');
    config()->set('services.payhere.sandbox', true);
    $this->paymentService = app(PaymentService::class);
    $this->availabilityService = app(AvailabilityService::class);
});

test('guest can initiate checkout session and receives auto-submitting POST form', function (): void {
    $vehicle = Vehicle::factory()->create(['status' => VehicleStatus::Active]);
    $booking = Booking::factory()->create([
        'status' => BookingStatus::AwaitingPayment,
        'vehicle_id' => $vehicle->id,
        'service_type' => ServiceType::AirportPickup,
        'total_amount_minor_units' => 10000,
        'currency' => 'USD',
    ]);

    $proposal = Proposal::factory()->create([
        'booking_id' => $booking->id,
        'status' => ProposalStatus::Accepted,
        'vehicle_id' => $vehicle->id,
        'total_amount_minor_units' => 10000,
        'currency' => 'USD',
        'deposit_required' => true,
        'deposit_amount_minor_units' => 2500,
        'payment_choice' => ProposalPaymentChoice::FullOrDeposit,
    ]);

    $booking->update(['current_proposal_id' => $proposal->id]);

    $plainToken = 'test-token-32-chars-long-example-1234';
    GuestAccessToken::create([
        'booking_id' => $booking->id,
        'token_hash' => GuestAccessToken::hashToken($plainToken),
        'purpose' => 'guest_access',
        'expires_at' => Carbon::now()->addDays(7),
    ]);

    $response = $this->post(route('booking.payment.checkout', [
        'reference' => $booking->reference,
        'token' => $plainToken,
    ]));

    $response->assertOk();
    $response->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    $response->assertHeader('Referrer-Policy', 'no-referrer');
    $response->assertSee('Transferring to Secure Payment Gateway');
    $response->assertSee('USD 25.00');

    // Assert pending transaction recorded in database
    $this->assertDatabaseHas('payment_transactions', [
        'booking_id' => $booking->id,
        'proposal_id' => $proposal->id,
        'amount_minor_units' => 2500,
        'currency' => 'USD',
        'status' => PaymentStatus::Pending->value,
    ]);
});

test('payhere hosted gateway generates compliant MD5 checksum hash and parameters', function (): void {
    config()->set('services.payhere.merchant_id', '1211144');
    config()->set('services.payhere.merchant_secret', 'testsecretkey123');
    config()->set('services.payhere.mode', 'sandbox');

    $gateway = app(PayHereHostedPaymentGateway::class);

    $vehicle = Vehicle::factory()->create(['status' => VehicleStatus::Active]);
    $booking = Booking::factory()->create([
        'status' => BookingStatus::AwaitingPayment,
        'vehicle_id' => $vehicle->id,
        'reference' => 'BK-PAYHERE-01',
    ]);

    $proposal = Proposal::factory()->create([
        'booking_id' => $booking->id,
        'status' => ProposalStatus::Accepted,
        'total_amount_minor_units' => 5000,
        'currency' => 'USD',
        'deposit_required' => false,
    ]);

    $transaction = PaymentTransaction::create([
        'booking_id' => $booking->id,
        'proposal_id' => $proposal->id,
        'payment_method' => PaymentMethod::OnlineCard,
        'payment_type' => PaymentType::Full,
        'amount_minor_units' => 5000,
        'currency' => 'USD',
        'status' => PaymentStatus::Pending,
        'idempotency_key' => 'idemp-test-01',
    ]);

    $session = $gateway->createCheckoutSession(
        booking: $booking,
        proposal: $proposal,
        transaction: $transaction,
        returnUrl: 'https://spinellanka.test/return',
        cancelUrl: 'https://spinellanka.test/cancel',
        notifyUrl: 'https://spinellanka.test/notify'
    );

    expect($session)->toBeInstanceOf(CheckoutSession::class)
        ->and($session->checkoutUrl)->toBe('https://sandbox.payhere.lk/pay/checkout')
        ->and($session->formFields)->toHaveKey('merchant_id', '1211144')
        ->and($session->formFields)->toHaveKey('order_id', $transaction->reference)
        ->and($session->formFields)->toHaveKey('currency', 'USD')
        ->and($session->formFields)->toHaveKey('amount', '50.00')
        ->and($session->formFields)->toHaveKey('hash');

    // Verify PayHere hash: strtoupper(md5($merchantId . $orderId . number_format($amount, 2, '.', '') . $currency . strtoupper(md5($merchantSecret))))
    $expectedInnerSecret = strtoupper(md5('testsecretkey123'));
    $expectedHash = strtoupper(md5('1211144'.$transaction->reference.'50.00'.'USD'.$expectedInnerSecret));
    expect($session->formFields['hash'])->toBe($expectedHash);
});

test('browser return endpoint is decoupled and never settles transaction or confirms booking', function (): void {
    $vehicle = Vehicle::factory()->create(['status' => VehicleStatus::Active]);
    $booking = Booking::factory()->create([
        'status' => BookingStatus::AwaitingPayment,
        'vehicle_id' => $vehicle->id,
        'total_amount_minor_units' => 8000,
    ]);

    $plainToken = 'test-token-return-endpoint-123456';
    GuestAccessToken::create([
        'booking_id' => $booking->id,
        'token_hash' => GuestAccessToken::hashToken($plainToken),
        'purpose' => 'guest_access',
        'expires_at' => Carbon::now()->addDays(7),
    ]);

    // Create a pending transaction
    PaymentTransaction::create([
        'booking_id' => $booking->id,
        'payment_method' => PaymentMethod::OnlineCard,
        'payment_type' => PaymentType::Full,
        'amount_minor_units' => 8000,
        'currency' => 'USD',
        'status' => PaymentStatus::Pending,
        'idempotency_key' => 'idemp-pending-01',
    ]);

    // Hit return endpoint with arbitrary query parameters (mimicking user browser return)
    $response = $this->get(route('booking.payment.return', [
        'reference' => $booking->reference,
        'token' => $plainToken,
        'order_id' => 'idemp-pending-01',
        'status_code' => '2', // Fake success parameter in browser!
    ]));

    $response->assertRedirect(route('booking.status', [
        'reference' => $booking->reference,
        'token' => $plainToken,
    ]));

    $response->assertSessionHas('payment_pending');

    // The booking MUST STILL BE AwaitingPayment — NEVER confirmed from browser return!
    $booking->refresh();
    expect($booking->status)->toBe(BookingStatus::AwaitingPayment);

    // The transaction MUST STILL BE Pending — NEVER settled from browser return!
    $tx = PaymentTransaction::where('idempotency_key', 'idemp-pending-01')->first();
    expect($tx->status)->toBe(PaymentStatus::Pending);
});

test('browser cancel endpoint redirects to status page with informative notice', function (): void {
    $booking = Booking::factory()->create([
        'status' => BookingStatus::AwaitingPayment,
    ]);

    $plainToken = 'test-token-cancel-endpoint-12345';
    GuestAccessToken::create([
        'booking_id' => $booking->id,
        'token_hash' => GuestAccessToken::hashToken($plainToken),
        'purpose' => 'guest_access',
        'expires_at' => Carbon::now()->addDays(7),
    ]);

    $response = $this->get(route('booking.payment.cancel', [
        'reference' => $booking->reference,
        'token' => $plainToken,
    ]));

    $response->assertRedirect(route('booking.status', [
        'reference' => $booking->reference,
        'token' => $plainToken,
    ]));

    $response->assertSessionHas('error');
});
