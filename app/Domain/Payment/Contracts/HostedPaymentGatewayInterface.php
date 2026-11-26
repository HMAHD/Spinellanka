<?php

declare(strict_types=1);

namespace App\Domain\Payment\Contracts;

use App\Domain\Payment\DTOs\CheckoutSession;
use App\Domain\Payment\DTOs\PaymentVerificationResult;
use App\Models\Booking;
use App\Models\PaymentTransaction;
use App\Models\Proposal;

interface HostedPaymentGatewayInterface
{
    /**
     * Create a hosted payment checkout session.
     */
    public function createCheckoutSession(
        Booking $booking,
        ?Proposal $proposal,
        PaymentTransaction $transaction,
        string $returnUrl,
        string $cancelUrl,
        string $notifyUrl
    ): CheckoutSession;

    /**
     * Query transaction status from gateway API (used for reconciliation).
     */
    public function queryTransaction(string $orderReference): ?PaymentVerificationResult;

    /**
     * Check whether this gateway adapter is configured and active.
     */
    public function isConfigured(): bool;

    /**
     * Provider identifier string (e.g. 'payhere', 'fake').
     */
    public function identifier(): string;
}
