<?php

declare(strict_types=1);

namespace App\Domain\Payment\Services;

use App\Domain\Audit\Services\AuditService;
use App\Domain\Booking\Enums\BookingStatus;
use App\Domain\Booking\Enums\ConfirmationChannel;
use App\Domain\Booking\Services\BookingLifecycleService;
use App\Domain\Payment\Contracts\HostedPaymentGatewayInterface;
use App\Domain\Payment\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\PaymentTransaction;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PaymentReconciliationService
{
    public function __construct(
        protected HostedPaymentGatewayInterface $gateway,
        protected AuditService $auditService,
        protected ?BookingLifecycleService $lifecycleService = null,
        protected ?FinancialDocumentService $documentService = null,
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
            ->with(['booking', 'proposal'])
            ->get();

        $count = 0;
        foreach ($staleTransactions as $transaction) {
            $queryResult = $this->gateway->queryTransaction($transaction->reference);

            if ($queryResult === null) {
                // If gateway query API returns no verification result (e.g. gateway query unsupported),
                // do NOT cancel young pending transactions (< 2 hours) unless the proposal hold has expired.
                $isHardTimeout = $transaction->created_at->diffInMinutes(Carbon::now('Asia/Colombo')) >= 120;
                $isProposalExpired = $transaction->proposal?->isExpired()
                    || ($transaction->booking && $transaction->booking->status->isTerminal());

                if ($isHardTimeout || $isProposalExpired) {
                    $transaction->update([
                        'status' => PaymentStatus::Cancelled,
                        'notes' => 'Checkout session timed out without gateway confirmation.',
                    ]);
                    $count++;
                }

                continue;
            }

            if ($queryResult->isSuccessful()) {
                // Webhook was missed! Recover settlement via reconciliation
                $now = Carbon::now('Asia/Colombo');
                $booking = $transaction->booking;
                $proposal = $transaction->proposal;

                DB::transaction(function () use ($transaction, $booking, $proposal, $queryResult, $now) {
                    $vehicleId = ($proposal ? $proposal->vehicle_id : null) ?? $booking?->vehicle_id;
                    if ($vehicleId) {
                        Vehicle::where('id', $vehicleId)->lockForUpdate()->first();
                    }
                    if ($booking) {
                        Booking::where('id', $booking->id)->lockForUpdate()->first();
                    }
                    /** @var PaymentTransaction $lockedTxn */
                    $lockedTxn = PaymentTransaction::where('id', $transaction->id)->lockForUpdate()->firstOrFail();

                    $lockedTxn->update([
                        'status' => PaymentStatus::Completed,
                        'gateway_transaction_id' => $queryResult->providerTransactionId,
                        'settled_at' => $now,
                        'notes' => 'Settled via automated reconciliation query.',
                    ]);

                    $this->auditService->record(
                        action: 'payment.reconciled_settled',
                        actor: null,
                        summary: "Reconciled missed webhook settlement for transaction {$lockedTxn->reference}",
                        metadata: [
                            'transaction_id' => $lockedTxn->id,
                            'booking_id' => $lockedTxn->booking_id,
                            'gateway_transaction_id' => $queryResult->providerTransactionId,
                        ],
                        auditable: $lockedTxn,
                        actorType: 'system'
                    );

                    // If booking was awaiting payment, advance to Confirmed and issue receipt
                    if ($booking && $booking->status === BookingStatus::AwaitingPayment) {
                        if ($this->lifecycleService) {
                            $this->lifecycleService->confirm(
                                booking: $booking,
                                staff: null,
                                channel: ConfirmationChannel::OnlinePayment,
                                notes: "Settled via automated reconciliation (Txn: {$queryResult->providerTransactionId})",
                                proposal: $proposal
                            );
                        }
                        if ($this->documentService) {
                            $this->documentService->generateReceipt($lockedTxn);
                        }
                    }
                });
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
