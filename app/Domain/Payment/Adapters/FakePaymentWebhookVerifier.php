<?php

declare(strict_types=1);

namespace App\Domain\Payment\Adapters;

use App\Domain\Payment\Contracts\PaymentWebhookVerifierInterface;
use App\Domain\Payment\DTOs\PaymentVerificationResult;
use Illuminate\Http\Request;

class FakePaymentWebhookVerifier implements PaymentWebhookVerifierInterface
{
    public function verify(Request $request): PaymentVerificationResult
    {
        $orderId = (string) $request->input('order_id', '');
        $paymentId = (string) $request->input('payment_id', 'fake_pmt_'.uniqid());
        $amount = (string) $request->input('amount', '0.00');
        $currency = (string) $request->input('currency', 'USD');
        $statusCode = (string) $request->input('status_code', '2');
        $signature = (string) $request->input('signature', '');

        // If signature is 'invalid_sig', mark invalid
        $isValid = $signature !== 'invalid_sig';

        $amountMinorUnits = (int) round(((float) $amount) * 100);

        return new PaymentVerificationResult(
            isValid: $isValid,
            provider: 'fake',
            providerEventId: $paymentId,
            providerTransactionId: $paymentId,
            orderReference: $orderId,
            amountMinorUnits: $amountMinorUnits,
            currency: strtoupper($currency),
            statusCode: $statusCode,
            rawPayload: $request->all(),
            errorMessage: $isValid ? null : 'Fake invalid signature error.'
        );
    }

    public function identifier(): string
    {
        return 'fake';
    }
}
