<?php

declare(strict_types=1);

namespace App\Domain\Payment\Adapters;

use App\Domain\Payment\Contracts\HostedPaymentGatewayInterface;
use App\Domain\Payment\DTOs\CheckoutSession;
use App\Domain\Payment\DTOs\PaymentVerificationResult;
use App\Domain\Payment\Exceptions\PaymentGatewayMisconfiguredException;
use App\Models\Booking;
use App\Models\PaymentTransaction;
use App\Models\Proposal;

class PayHereHostedPaymentGateway implements HostedPaymentGatewayInterface
{
    public function createCheckoutSession(
        Booking $booking,
        ?Proposal $proposal,
        PaymentTransaction $transaction,
        string $returnUrl,
        string $cancelUrl,
        string $notifyUrl
    ): CheckoutSession {
        if (! $this->isConfigured()) {
            throw new PaymentGatewayMisconfiguredException('PayHere gateway credentials (merchant_id / merchant_secret) are not configured.');
        }

        $merchantId = (string) config('services.payhere.merchant_id');
        $merchantSecret = (string) config('services.payhere.merchant_secret');
        $isSandbox = (bool) config('services.payhere.sandbox', true);

        $checkoutUrl = $isSandbox
            ? 'https://sandbox.payhere.lk/pay/checkout'
            : 'https://www.payhere.lk/pay/checkout';

        $formattedAmount = number_format($transaction->amount_minor_units / 100, 2, '.', '');
        $currency = strtoupper($transaction->currency);
        $orderId = $transaction->reference;

        $hashedSecret = strtoupper(md5($merchantSecret));
        $hash = strtoupper(md5($merchantId.$orderId.$formattedAmount.$currency.$hashedSecret));

        // Resolve contact details from booker or lead traveler
        $contact = $booking->booker ?? $booking->leadTraveler;
        $firstName = $contact?->first_name ?: 'Valued';
        $lastName = $contact?->last_name ?: 'Guest';
        $email = $contact?->email ?: 'guest@spinellanka.test';
        $phone = $contact?->phone ?: '+94770000000';

        $description = sprintf('Spinellanka Booking %s (%s)', $booking->reference, $transaction->payment_type->label());

        $formFields = [
            'merchant_id' => $merchantId,
            'return_url' => $returnUrl,
            'cancel_url' => $cancelUrl,
            'notify_url' => $notifyUrl,
            'order_id' => $orderId,
            'items' => $description,
            'currency' => $currency,
            'amount' => $formattedAmount,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'phone' => $phone,
            'address' => 'Colombo, Sri Lanka',
            'city' => 'Colombo',
            'country' => 'Sri Lanka',
            'hash' => $hash,
            'custom_1' => (string) $booking->id,
            'custom_2' => (string) ($proposal ? $proposal->id : ''),
        ];

        return new CheckoutSession(
            gatewayProvider: 'payhere',
            transactionReference: $orderId,
            checkoutUrl: $checkoutUrl,
            httpMethod: 'POST',
            formFields: $formFields
        );
    }

    public function queryTransaction(string $orderReference): ?PaymentVerificationResult
    {
        // PayHere provides an Order Retrieval API requiring OAuth2 bearer token
        // If not integrated or in local dev, returns null to fall back to manual reconciliation
        return null;
    }

    public function isConfigured(): bool
    {
        return ! empty(config('services.payhere.merchant_id')) && ! empty(config('services.payhere.merchant_secret'));
    }

    public function identifier(): string
    {
        return 'payhere';
    }
}
