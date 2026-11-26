<?php

declare(strict_types=1);

namespace App\Domain\Payment\Adapters;

use App\Domain\Payment\Contracts\PaymentWebhookVerifierInterface;
use App\Domain\Payment\DTOs\PaymentVerificationResult;
use Illuminate\Http\Request;

class PayHereWebhookVerifier implements PaymentWebhookVerifierInterface
{
    public function verify(Request $request): PaymentVerificationResult
    {
        $merchantSecret = (string) config('services.payhere.merchant_secret');
        $configuredMerchantId = (string) config('services.payhere.merchant_id');

        $merchantId = (string) $request->input('merchant_id', '');
        $orderId = (string) $request->input('order_id', '');
        $paymentId = (string) $request->input('payment_id', '');
        $payhereAmount = (string) $request->input('payhere_amount', '');
        $payhereCurrency = (string) $request->input('payhere_currency', '');
        $statusCode = (string) $request->input('status_code', '');
        $md5sig = (string) $request->input('md5sig', '');

        // 1. Missing required payload fields check
        if ($merchantId === '' || $orderId === '' || $payhereAmount === '' || $payhereCurrency === '' || $statusCode === '' || $md5sig === '') {
            return new PaymentVerificationResult(
                isValid: false,
                provider: 'payhere',
                providerEventId: $paymentId ?: null,
                providerTransactionId: $paymentId ?: null,
                orderReference: $orderId ?: null,
                amountMinorUnits: 0,
                currency: $payhereCurrency ?: 'USD',
                statusCode: $statusCode,
                rawPayload: $request->all(),
                errorMessage: 'Missing required PayHere webhook parameters.'
            );
        }

        // 2. Fail closed if gateway credentials are not configured on the server
        if (empty($configuredMerchantId) || empty($merchantSecret)) {
            return new PaymentVerificationResult(
                isValid: false,
                provider: 'payhere',
                providerEventId: $paymentId ?: null,
                providerTransactionId: $paymentId ?: null,
                orderReference: $orderId ?: null,
                amountMinorUnits: 0,
                currency: $payhereCurrency ?: 'USD',
                statusCode: $statusCode,
                rawPayload: $request->all(),
                errorMessage: 'PayHere gateway credentials are not configured on this server.'
            );
        }

        // Merchant ID verification check (prevents cross-account injection)
        if (! hash_equals($configuredMerchantId, $merchantId)) {
            return new PaymentVerificationResult(
                isValid: false,
                provider: 'payhere',
                providerEventId: $paymentId,
                providerTransactionId: $paymentId,
                orderReference: $orderId,
                amountMinorUnits: 0,
                currency: $payhereCurrency,
                statusCode: $statusCode,
                rawPayload: $request->all(),
                errorMessage: 'Merchant ID does not match configured Spinellanka merchant account.'
            );
        }

        // 3. Cryptographic signature check (timing-safe hash_equals)
        $formattedAmount = number_format((float) $payhereAmount, 2, '.', '');
        $hashedSecret = strtoupper(md5($merchantSecret));
        $expectedSignature = strtoupper(md5(
            $merchantId.
            $orderId.
            $formattedAmount.
            strtoupper($payhereCurrency).
            $statusCode.
            $hashedSecret
        ));

        $isValid = hash_equals($expectedSignature, strtoupper($md5sig));

        // Parse amount into integer minor units using round to avoid float precision truncation
        $amountMinorUnits = (int) round(((float) $payhereAmount) * 100);

        return new PaymentVerificationResult(
            isValid: $isValid,
            provider: 'payhere',
            providerEventId: $paymentId,
            providerTransactionId: $paymentId,
            orderReference: $orderId,
            amountMinorUnits: $amountMinorUnits,
            currency: strtoupper($payhereCurrency),
            statusCode: $statusCode,
            rawPayload: $request->all(),
            errorMessage: $isValid ? null : 'Invalid PayHere cryptographic signature.'
        );
    }

    public function identifier(): string
    {
        return 'payhere';
    }
}
