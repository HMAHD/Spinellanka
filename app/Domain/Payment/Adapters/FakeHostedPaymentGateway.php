<?php

declare(strict_types=1);

namespace App\Domain\Payment\Adapters;

use App\Domain\Payment\Contracts\HostedPaymentGatewayInterface;
use App\Domain\Payment\DTOs\CheckoutSession;
use App\Domain\Payment\DTOs\PaymentVerificationResult;
use App\Models\Booking;
use App\Models\PaymentTransaction;
use App\Models\Proposal;

class FakeHostedPaymentGateway implements HostedPaymentGatewayInterface
{
    /** @var array<string, CheckoutSession> */
    public array $createdSessions = [];

    /** @var array<string, PaymentVerificationResult> */
    public array $mockQueries = [];

    public bool $isConfigured = true;

    public function createCheckoutSession(
        Booking $booking,
        ?Proposal $proposal,
        PaymentTransaction $transaction,
        string $returnUrl,
        string $cancelUrl,
        string $notifyUrl
    ): CheckoutSession {
        $orderId = $transaction->reference;
        $formattedAmount = number_format($transaction->amount_minor_units / 100, 2, '.', '');

        $session = new CheckoutSession(
            gatewayProvider: 'fake',
            transactionReference: $orderId,
            checkoutUrl: $returnUrl.(str_contains($returnUrl, '?') ? '&' : '?').'fake_paid=1&order_id='.urlencode($orderId),
            httpMethod: 'GET',
            formFields: [
                'order_id' => $orderId,
                'amount' => $formattedAmount,
                'currency' => $transaction->currency,
            ]
        );

        $this->createdSessions[$orderId] = $session;

        return $session;
    }

    public function queryTransaction(string $orderReference): ?PaymentVerificationResult
    {
        return $this->mockQueries[$orderReference] ?? null;
    }

    public function isConfigured(): bool
    {
        return $this->isConfigured;
    }

    public function identifier(): string
    {
        return 'fake';
    }
}
