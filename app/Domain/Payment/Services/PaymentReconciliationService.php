<?php

declare(strict_types=1);

namespace App\Domain\Payment\Services;

use App\Domain\Audit\Services\AuditService;
use App\Domain\Payment\Contracts\HostedPaymentGatewayInterface;
use App\Domain\Payment\Enums\PaymentStatus;
use App\Models\PaymentTransaction;
use Carbon\Carbon;

class PaymentReconciliationService
{
    public function __construct(
        protected HostedPaymentGatewayInterface $gateway,
        protected AuditService $auditService,
    ) {}

    /**
     * Sweep stale pending transactions older than specified hours.
     */
    public function sweepStalePendingTransactions(int $timeoutHours = 2): int
    {
        return $this->reconcileStalePendingTransactions($timeoutHours * 60);
    }

    /**
     * Scan and reconcile stale pending transactions older than $staleMinutes.
     */
    public function reconcileStalePendingTransactions(int $staleMinutes = 30): int
    {
        $cutoff = Carbon::now('Asia/Colombo')->subMinutes($staleMinutes);

        $staleTransactions = PaymentTransaction::where('status', PaymentStatus::Pending->value)
            ->where('created_at', '<=', $cutoff)
            ->get();

        $count = 0;
        foreach ($staleTransactions as $transaction) {
            $queryResult = $this->gateway->queryTransaction($transaction->reference);

            if ($queryResult === null) {
                // If gateway query API is unavailable or returns no record, mark expired/cancelled
                $transaction->update([
                    'status' => PaymentStatus::Cancelled,
                    'notes' => 'Checkout session timed out without gateway confirmation.',
                ]);
                $count++;

                continue;
            }

            if ($queryResult->isSuccessful()) {
                // Webhook was missed! Mark completed and alert operations
                $transaction->update([
                    'status' => PaymentStatus::Completed,
                    'gateway_transaction_id' => $queryResult->providerTransactionId,
                    'settled_at' => Carbon::now('Asia/Colombo'),
                    'notes' => 'Settled via automated reconciliation query.',
                ]);

                $this->auditService->record(
                    action: 'payment.reconciled_settled',
                    actor: null,
                    summary: "Reconciled missed webhook settlement for transaction {$transaction->reference}",
                    metadata: [
                        'transaction_id' => $transaction->id,
                        'booking_id' => $transaction->booking_id,
                        'gateway_transaction_id' => $queryResult->providerTransactionId,
                    ],
                    auditable: $transaction,
                    actorType: 'system'
                );
            } else {
                $transaction->update([
                    'status' => PaymentStatus::Failed,
                    'notes' => "Gateway confirmed failure during reconciliation: {$queryResult->statusCode}",
                ]);
            }

            $count++;
        }

        return $count;
    }
}
