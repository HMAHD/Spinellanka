<?php

declare(strict_types=1);

namespace App\Domain\Payment\Services;

use App\Domain\Audit\Services\AuditService;
use App\Domain\Booking\Enums\ConfirmationChannel;
use App\Domain\Booking\Services\BookingLifecycleService;
use App\Domain\Messaging\Services\OutboxService;
use App\Domain\Payment\Adapters\FakePaymentWebhookVerifier;
use App\Domain\Payment\Adapters\PayHereWebhookVerifier;
use App\Domain\Payment\Contracts\PaymentWebhookVerifierInterface;
use App\Domain\Payment\DTOs\PaymentWebhookResult;
use App\Domain\Payment\Enums\PaymentStatus;
use App\Domain\Payment\Exceptions\InvalidWebhookSignatureException;
use App\Models\Booking;
use App\Models\PaymentEvent;
use App\Models\PaymentTransaction;
use App\Models\Proposal;
use App\Models\Vehicle;
use App\Models\WebhookReceipt;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PaymentWebhookService
{
    public function __construct(
        protected PaymentWebhookVerifierInterface $verifier,
        protected BookingLifecycleService $lifecycleService,
        protected FinancialDocumentService $documentService,
        protected AuditService $auditService,
        protected OutboxService $outboxService,
    ) {}

    /**
     * Ingest, cryptographically verify, and settle a webhook callback.
     */
    public function handleWebhook(string $provider, Request $request): PaymentWebhookResult
    {
        $verifier = match ($provider) {
            'payhere' => app(PayHereWebhookVerifier::class),
            'fake' => app()->environment(['local', 'testing'])
                ? app(FakePaymentWebhookVerifier::class)
                : throw new InvalidWebhookSignatureException('Fake payment provider is disabled in production.'),
            default => $this->verifier,
        };

        $verificationResult = $verifier->verify($request);

        // 1. Record raw webhook receipt in append-only delivery log
        WebhookReceipt::create([
            'provider' => $provider,
            'event_id' => $verificationResult->providerEventId ?: ('anon_'.uniqid()),
            'signature_valid' => $verificationResult->isValid,
            'source_ip' => $request->ip(),
            'payload_summary' => [
                'order_id' => $verificationResult->orderReference,
                'status_code' => $verificationResult->statusCode,
                'amount_minor_units' => $verificationResult->amountMinorUnits,
                'currency' => $verificationResult->currency,
            ],
            'received_at' => Carbon::now('Asia/Colombo'),
        ]);

        // 2. Reject invalid cryptographic signatures immediately
        if (! $verificationResult->isValid) {
            $this->auditService->record(
                action: 'payment.webhook_rejected',
                actor: null,
                summary: "Rejected invalid {$provider} webhook signature for order {$verificationResult->orderReference}",
                metadata: [
                    'provider' => $provider,
                    'error' => $verificationResult->errorMessage,
                    'ip' => $request->ip(),
                ],
                auditable: null,
                actorType: 'system'
            );

            throw new InvalidWebhookSignatureException($verificationResult->errorMessage ?? 'Invalid webhook signature.');
        }

        // 3. Idempotency Gate on unique provider event identifier
        if ($verificationResult->providerEventId) {
            $alreadyProcessed = PaymentEvent::where('provider', $provider)
                ->where('provider_event_id', $verificationResult->providerEventId)
                ->where('status', 'processed')
                ->exists();

            if ($alreadyProcessed) {
                return PaymentWebhookResult::alreadyProcessed();
            }
        }

        // 4. Resolve payment transaction reference
        if (! $verificationResult->orderReference) {
            throw new InvalidArgumentException('Webhook payload missing order reference.');
        }

        /** @var PaymentTransaction|null $unlockedTxn */
        $unlockedTxn = PaymentTransaction::where('reference', $verificationResult->orderReference)
            ->orWhere('idempotency_key', $verificationResult->orderReference)
            ->first();
        if (! $unlockedTxn) {
            throw new InvalidArgumentException("Payment transaction {$verificationResult->orderReference} not found.");
        }

        $bookingId = $unlockedTxn->booking_id;
        $proposalId = $unlockedTxn->proposal_id;

        // Resolve vehicle ID for canonical lock ordering
        /** @var Booking $bookingSnapshot */
        $bookingSnapshot = Booking::where('id', $bookingId)->firstOrFail();
        /** @var Proposal|null $proposalSnapshot */
        $proposalSnapshot = $proposalId ? Proposal::where('id', $proposalId)->first() : null;
        $vehicleId = $proposalSnapshot ? $proposalSnapshot->vehicle_id : $bookingSnapshot->vehicle_id;

        return DB::transaction(function () use (
            $unlockedTxn,
            $bookingId,
            $proposalId,
            $vehicleId,
            $verificationResult,
            $provider
        ) {
            // Strict canonical lock sequence: Vehicle -> Booking -> Proposal -> PaymentTransaction
            if ($vehicleId) {
                Vehicle::where('id', $vehicleId)->lockForUpdate()->firstOrFail();
            }
            /** @var Booking $booking */
            $booking = Booking::where('id', $bookingId)->lockForUpdate()->firstOrFail();

            /** @var Proposal|null $proposal */
            $proposal = null;
            if ($proposalId) {
                $proposal = Proposal::where('id', $proposalId)->lockForUpdate()->first();
            }

            /** @var PaymentTransaction $transaction */
            $transaction = PaymentTransaction::where('id', $unlockedTxn->id)->lockForUpdate()->firstOrFail();
            $now = Carbon::now('Asia/Colombo');

            // Idempotency: If transaction is already completed, exit safely
            if ($transaction->status === PaymentStatus::Completed) {
                return PaymentWebhookResult::alreadyProcessed();
            }

            // 5. Gateway status code check: only status_code === '2' denotes success in PayHere
            if (! $verificationResult->isSuccessful()) {
                $transaction->update([
                    'status' => PaymentStatus::Failed,
                    'gateway_transaction_id' => $verificationResult->providerTransactionId,
                    'notes' => sprintf('Payment failed/cancelled via %s (Status: %s)', $provider, $verificationResult->statusCode),
                ]);

                PaymentEvent::create([
                    'payment_transaction_id' => $transaction->id,
                    'provider' => $provider,
                    'provider_event_id' => $verificationResult->providerEventId ?: ('fail_'.uniqid()),
                    'event_type' => 'payment.failed',
                    'payload' => $verificationResult->rawPayload,
                    'status' => 'processed',
                    'error_message' => "Gateway status code {$verificationResult->statusCode}",
                    'received_at' => Carbon::now('Asia/Colombo'),
                    'processed_at' => Carbon::now('Asia/Colombo'),
                ]);

                $this->auditService->record(
                    action: 'payment.failed',
                    actor: null,
                    summary: "Payment failed via {$provider} for booking {$booking->reference} (Status: {$verificationResult->statusCode})",
                    metadata: [
                        'transaction_id' => $transaction->id,
                        'booking_id' => $booking->id,
                        'status_code' => $verificationResult->statusCode,
                    ],
                    auditable: $transaction,
                    actorType: 'system'
                );

                return PaymentWebhookResult::failed("Gateway reported status code {$verificationResult->statusCode}");
            }

            // 6. Strict amount and currency parity checks
            if ($verificationResult->amountMinorUnits !== $transaction->amount_minor_units) {
                $transaction->update([
                    'status' => PaymentStatus::Failed,
                    'notes' => "Amount mismatch: received {$verificationResult->amountMinorUnits}, expected {$transaction->amount_minor_units}",
                ]);

                $this->auditService->record(
                    action: 'payment.mismatch',
                    actor: null,
                    summary: "Payment amount mismatch via {$provider} for booking {$booking->reference}",
                    metadata: [
                        'transaction_id' => $transaction->id,
                        'booking_id' => $booking->id,
                        'received_amount' => $verificationResult->amountMinorUnits,
                        'expected_amount' => $transaction->amount_minor_units,
                    ],
                    auditable: $transaction,
                    actorType: 'system'
                );

                PaymentEvent::create([
                    'payment_transaction_id' => $transaction->id,
                    'provider' => $provider,
                    'provider_event_id' => $verificationResult->providerEventId ?: ('mismatch_'.uniqid()),
                    'event_type' => 'webhook.amount_mismatch',
                    'payload' => $verificationResult->rawPayload,
                    'status' => 'failed',
                    'received_at' => $now,
                    'processed_at' => $now,
                ]);

                return PaymentWebhookResult::failed("Amount mismatch: received {$verificationResult->amountMinorUnits}, expected {$transaction->amount_minor_units}");
            }

            if ($verificationResult->currency !== $transaction->currency) {
                $transaction->update([
                    'status' => PaymentStatus::Failed,
                    'notes' => "Currency mismatch: received {$verificationResult->currency}, expected {$transaction->currency}",
                ]);

                $this->auditService->record(
                    action: 'payment.mismatch',
                    actor: null,
                    summary: "Payment currency mismatch via {$provider} for booking {$booking->reference}",
                    metadata: [
                        'transaction_id' => $transaction->id,
                        'booking_id' => $booking->id,
                        'received_currency' => $verificationResult->currency,
                        'expected_currency' => $transaction->currency,
                    ],
                    auditable: $transaction,
                    actorType: 'system'
                );

                PaymentEvent::create([
                    'payment_transaction_id' => $transaction->id,
                    'provider' => $provider,
                    'provider_event_id' => $verificationResult->providerEventId ?: ('curr_mismatch_'.uniqid()),
                    'event_type' => 'webhook.currency_mismatch',
                    'payload' => $verificationResult->rawPayload,
                    'status' => 'failed',
                    'received_at' => $now,
                    'processed_at' => $now,
                ]);

                return PaymentWebhookResult::failed("Currency mismatch: received {$verificationResult->currency}, expected {$transaction->currency}");
            }

            // 7. Overpayment / Discrepancy protection: check total settled vs proposal total
            $currentSettled = $booking->totalSettledAmount();
            $proposalTotal = $proposal ? $proposal->total_amount_minor_units : $booking->total_amount_minor_units;

            if ($proposalTotal !== null && ($currentSettled + $verificationResult->amountMinorUnits) > $proposalTotal) {
                $transaction->update([
                    'status' => PaymentStatus::Discrepancy,
                    'gateway_transaction_id' => $verificationResult->providerTransactionId,
                    'notes' => 'Overpayment detected: total settled exceeds proposal total amount.',
                ]);

                $this->auditService->record(
                    action: 'payment.discrepancy_detected',
                    actor: null,
                    summary: "Overpayment discrepancy for booking {$booking->reference} (settled: {$currentSettled}, incoming: {$verificationResult->amountMinorUnits}, total: {$proposalTotal})",
                    metadata: [
                        'booking_id' => $booking->id,
                        'transaction_id' => $transaction->id,
                    ],
                    auditable: $transaction,
                    actorType: 'system'
                );

                return PaymentWebhookResult::discrepancy('Settled amount exceeds proposal total amount.');
            }

            $now = Carbon::now('Asia/Colombo');

            // 8. Handle expired proposal or terminal booking: capture payment, flag for manual review, do NOT confirm
            if ($proposal && ($proposal->isExpired() || $booking->status->isTerminal())) {
                $transaction->update([
                    'status' => PaymentStatus::Completed,
                    'gateway_transaction_id' => $verificationResult->providerTransactionId,
                    'settled_at' => $now,
                    'notes' => 'Payment settled after proposal expiry/cancellation. Retained for manual operational resolution.',
                ]);

                PaymentEvent::create([
                    'payment_transaction_id' => $transaction->id,
                    'provider' => $provider,
                    'provider_event_id' => $verificationResult->providerEventId ?: ('settled_exp_'.uniqid()),
                    'event_type' => 'payment.settled_expired',
                    'payload' => $verificationResult->rawPayload,
                    'status' => 'processed',
                    'received_at' => $now,
                    'processed_at' => $now,
                ]);

                $this->auditService->record(
                    action: 'payment.settled_on_expired_proposal',
                    actor: null,
                    summary: "Payment of {$transaction->currency} ".number_format($transaction->amount_minor_units / 100, 2)." captured on expired/terminal booking {$booking->reference}",
                    metadata: [
                        'booking_id' => $booking->id,
                        'transaction_id' => $transaction->id,
                        'proposal_id' => $proposal->id,
                    ],
                    auditable: $transaction,
                    actorType: 'system'
                );

                return PaymentWebhookResult::settledWithoutConfirmation();
            }

            // 9. Standard settlement: update transaction to Completed
            $transaction->update([
                'status' => PaymentStatus::Completed,
                'gateway_transaction_id' => $verificationResult->providerTransactionId,
                'settled_at' => $now,
            ]);

            PaymentEvent::create([
                'payment_transaction_id' => $transaction->id,
                'provider' => $provider,
                'provider_event_id' => $verificationResult->providerEventId ?: ('settled_'.uniqid()),
                'event_type' => 'payment.settled',
                'payload' => $verificationResult->rawPayload,
                'status' => 'processed',
                'received_at' => $now,
                'processed_at' => $now,
            ]);

            // Advance booking via BookingLifecycleService
            $confirmationNotes = sprintf(
                'Automated online payment settlement via %s (Provider Txn: %s)',
                $provider,
                $verificationResult->providerTransactionId
            );

            $this->lifecycleService->confirm(
                booking: $booking,
                staff: null,
                channel: ConfirmationChannel::OnlinePayment,
                notes: $confirmationNotes,
                proposal: $proposal
            );

            // Generate official receipt
            $this->documentService->generateReceipt($transaction);

            $this->auditService->record(
                action: 'payment.settled',
                actor: null,
                summary: "Payment of {$transaction->currency} ".number_format($transaction->amount_minor_units / 100, 2)." settled via {$provider} for booking {$booking->reference}",
                metadata: [
                    'booking_id' => $booking->id,
                    'transaction_id' => $transaction->id,
                    'reference' => $transaction->reference,
                    'gateway_transaction_id' => $verificationResult->providerTransactionId,
                    'amount_minor_units' => $transaction->amount_minor_units,
                    'currency' => $transaction->currency,
                    'provider' => $provider,
                ],
                auditable: $transaction,
                actorType: 'system'
            );

            $this->outboxService->record(
                eventType: 'booking.payment_settled',
                aggregate: $booking,
                payload: [
                    'booking_id' => $booking->id,
                    'transaction_id' => $transaction->id,
                    'reference' => $booking->reference,
                    'amount_minor_units' => $transaction->amount_minor_units,
                    'currency' => $transaction->currency,
                    'payment_method' => $transaction->payment_method->value,
                    'provider' => $provider,
                ],
                idempotencyKey: sprintf('evt_booking_%d_txn_%d_settled_v1', $booking->id, $transaction->id)
            );

            return PaymentWebhookResult::success($transaction->id);
        });
    }
}
