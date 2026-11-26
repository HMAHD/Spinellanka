<?php

declare(strict_types=1);

namespace App\Domain\Payment\Contracts;

use App\Domain\Payment\DTOs\PaymentVerificationResult;
use Illuminate\Http\Request;

interface PaymentWebhookVerifierInterface
{
    /**
     * Verify incoming webhook request signature, merchant ID, and payload authenticity.
     */
    public function verify(Request $request): PaymentVerificationResult;

    /**
     * Provider identifier matching this verifier (e.g. 'payhere', 'fake').
     */
    public function identifier(): string;
}
