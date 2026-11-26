<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Payment\Exceptions\InvalidWebhookSignatureException;
use App\Domain\Payment\Services\PaymentWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentWebhookController extends Controller
{
    public function __construct(
        protected PaymentWebhookService $webhookService,
    ) {}

    /**
     * Handle incoming asynchronous payment gateway webhook.
     */
    public function handle(Request $request, string $provider): JsonResponse
    {
        try {
            $result = $this->webhookService->handleWebhook(
                provider: $provider,
                request: $request
            );

            return response()->json([
                'status' => $result->status,
                'transaction_id' => $result->transactionId,
                'message' => $result->message,
            ], 200);
        } catch (InvalidWebhookSignatureException $e) {
            Log::warning('Payment webhook signature rejected', [
                'provider' => $provider,
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'error' => 'Invalid webhook signature',
            ], 400);
        } catch (\Throwable $e) {
            Log::error('Payment webhook processing error', [
                'provider' => $provider,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => $e->getMessage(),
            ], 422);
        }
    }
}
