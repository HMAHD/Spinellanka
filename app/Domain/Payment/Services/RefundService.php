<?php

declare(strict_types=1);

namespace App\Domain\Payment\Services;

use App\Domain\Audit\Services\AuditService;
use App\Domain\Identity\Permission;
use App\Domain\Messaging\Services\OutboxService;
use App\Domain\Payment\DTOs\RefundData;
use App\Domain\Payment\Enums\PaymentStatus;
use App\Domain\Payment\Enums\PaymentType;
use App\Domain\Payment\Enums\RefundStatus;
use App\Domain\Payment\Exceptions\RefundExceedsSettledAmountException;
use App\Models\Booking;
use App\Models\PaymentTransaction;
use App\Models\Refund;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;

class RefundService
{
    public function __construct(
        protected AuditService $auditService,
        protected OutboxService $outboxService,
    ) {}

    /**
     * Issue an approved refund against a settled transaction or booking.
     */
    public function issueRefund(
        PaymentTransaction|Booking $target,
        User $staff,
        RefundData $data
    ): Refund {
        if (! $staff->can(Permission::PaymentRefund->value) && ! $staff->hasPermissionTo(Permission::PaymentRefund->value)) {
            throw new AuthorizationException('Actor does not have payment.refund permission.');
        }

        if ($data->staffPasswordConfirmation !== null && ! Hash::check($data->staffPasswordConfirmation, $staff->password)) {
            throw new AuthorizationException('Staff password re-authentication failed.');
        }

        if ($data->amountMinorUnits <= 0) {
            throw new InvalidArgumentException('Refund amount must be greater than zero.');
        }

        $transaction = $target instanceof PaymentTransaction
            ? $target
            : $target->paymentTransactions()
                ->whereIn('status', [PaymentStatus::Completed->value, PaymentStatus::PartiallyRefunded->value])
                ->where('amount_minor_units', '>', 0)
                ->latest('id')
                ->firstOrFail();

        return DB::transaction(function () use ($transaction, $staff, $data) {
            /** @var PaymentTransaction $lockedTxn */
            $lockedTxn = PaymentTransaction::where('id', $transaction->id)->lockForUpdate()->firstOrFail();

            if (! $lockedTxn->isSettled()) {
                throw new InvalidArgumentException("Cannot refund transaction {$lockedTxn->reference} in status {$lockedTxn->status->value}.");
            }

            $remainingRefundable = $lockedTxn->remainingRefundableAmount();
            if ($data->amountMinorUnits > $remainingRefundable) {
                throw new RefundExceedsSettledAmountException(
                    sprintf(
                        'Requested refund of %s %.2f exceeds remaining refundable balance of %s %.2f.',
                        $lockedTxn->currency,
                        $data->amountMinorUnits / 100,
                        $lockedTxn->currency,
                        $remainingRefundable / 100
                    )
                );
            }

            $reference = Refund::generateReference($lockedTxn);
            $now = Carbon::now('Asia/Colombo');

            /** @var Refund $refund */
            $refund = Refund::create([
                'payment_transaction_id' => $lockedTxn->id,
                'booking_id' => $lockedTxn->booking_id,
                'reference' => $reference,
                'amount_minor_units' => $data->amountMinorUnits,
                'currency' => $lockedTxn->currency,
                'reason' => trim($data->reason),
                'status' => RefundStatus::Completed,
                'gateway_refund_id' => null,
                'approved_by_user_id' => $staff->id,
                'processed_at' => $now,
            ]);

            // Update parent transaction status
            $newRemaining = $remainingRefundable - $data->amountMinorUnits;
            $newStatus = $newRemaining === 0 ? PaymentStatus::Refunded : PaymentStatus::PartiallyRefunded;
            $lockedTxn->update(['status' => $newStatus]);

            // Create compensating negative PaymentTransaction record in append-only ledger
            PaymentTransaction::create([
                'booking_id' => $lockedTxn->booking_id,
                'proposal_id' => $lockedTxn->proposal_id,
                'reference' => PaymentTransaction::generateReference($lockedTxn->booking),
                'gateway_provider' => $lockedTxn->gateway_provider,
                'gateway_transaction_id' => $refund->reference,
                'payment_method' => $lockedTxn->payment_method,
                'payment_type' => PaymentType::Refund,
                'status' => PaymentStatus::Completed,
                'amount_minor_units' => -$data->amountMinorUnits,
                'currency' => $lockedTxn->currency,
                'idempotency_key' => sprintf('refund_comp_%d_%s', $refund->id, $reference),
                'recorded_by_user_id' => $staff->id,
                'notes' => sprintf('Compensating refund entry for %s: %s', $lockedTxn->reference, $data->reason),
                'metadata' => [
                    'source_transaction_id' => $lockedTxn->id,
                    'refund_id' => $refund->id,
                ],
                'settled_at' => $now,
            ]);

            $this->auditService->record(
                action: 'payment.refund_issued',
                actor: $staff,
                summary: "Issued refund of {$lockedTxn->currency} ".number_format($data->amountMinorUnits / 100, 2)." on transaction {$lockedTxn->reference} (Reason: {$data->reason})",
                metadata: [
                    'booking_id' => $lockedTxn->booking_id,
                    'transaction_id' => $lockedTxn->id,
                    'refund_id' => $refund->id,
                    'reference' => $refund->reference,
                    'amount_minor_units' => $data->amountMinorUnits,
                    'remaining_refundable_minor_units' => $newRemaining,
                ],
                auditable: $refund,
                actorType: 'staff'
            );

            $this->outboxService->record(
                eventType: 'booking.payment_refunded',
                aggregate: $lockedTxn->booking,
                payload: [
                    'booking_id' => $lockedTxn->booking_id,
                    'transaction_id' => $lockedTxn->id,
                    'refund_id' => $refund->id,
                    'reference' => $refund->reference,
                    'amount_minor_units' => $data->amountMinorUnits,
                    'currency' => $lockedTxn->currency,
                ],
                idempotencyKey: sprintf('evt_booking_%d_refund_%d_v1', $lockedTxn->booking_id, $refund->id)
            );

            return $refund;
        });
    }
}
