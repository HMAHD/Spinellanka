<?php

declare(strict_types=1);

namespace App\Domain\Payment\Services;

use App\Domain\Audit\Services\AuditService;
use App\Domain\Booking\Enums\BookingStatus;
use App\Domain\Booking\Enums\ConfirmationChannel;
use App\Domain\Booking\Services\BookingLifecycleService;
use App\Domain\Identity\Permission;
use App\Domain\Messaging\Services\OutboxService;
use App\Domain\Payment\DTOs\OfflinePaymentData;
use App\Domain\Payment\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class OfflinePaymentService
{
    public function __construct(
        protected BookingLifecycleService $lifecycleService,
        protected FinancialDocumentService $documentService,
        protected AuditService $auditService,
        protected OutboxService $outboxService,
    ) {}

    /**
     * Record an offline payment (cash, bank wire, POS terminal).
     */
    public function recordOfflinePayment(
        Booking $booking,
        User $staff,
        OfflinePaymentData $data
    ): PaymentTransaction {
        if (! $staff->can(Permission::PaymentRecordOffline->value) && ! $staff->hasPermissionTo(Permission::PaymentRecordOffline->value)) {
            throw new AuthorizationException('Actor does not have payment.record_offline permission.');
        }

        if ($booking->status->isTerminal()) {
            throw new InvalidArgumentException("Cannot record payment for booking {$booking->reference} in terminal status {$booking->status->value}.");
        }

        if ($data->amountMinorUnits <= 0) {
            throw new InvalidArgumentException('Offline payment amount must be greater than zero.');
        }

        $proposal = $data->proposal ?? $booking->currentProposal;
        $vehicleId = $proposal ? $proposal->vehicle_id : $booking->vehicle_id;

        return DB::transaction(function () use ($booking, $staff, $data, $proposal, $vehicleId) {
            // Strict canonical lock sequence: Vehicle -> Booking -> Proposal -> PaymentTransaction
            if ($vehicleId) {
                Vehicle::where('id', $vehicleId)->lockForUpdate()->first();
            }
            $lockedBooking = Booking::where('id', $booking->id)->lockForUpdate()->firstOrFail();
            $reference = PaymentTransaction::generateReference($lockedBooking);
            $now = Carbon::now('Asia/Colombo');

            /** @var PaymentTransaction $transaction */
            $transaction = PaymentTransaction::create([
                'booking_id' => $lockedBooking->id,
                'proposal_id' => $proposal?->id,
                'reference' => $reference,
                'gateway_provider' => 'offline',
                'gateway_transaction_id' => $data->externalReference,
                'payment_method' => $data->paymentMethod,
                'payment_type' => $data->paymentType,
                'status' => PaymentStatus::Completed,
                'amount_minor_units' => $data->amountMinorUnits,
                'currency' => $data->currency,
                'idempotency_key' => sprintf('offline_txn_%s_%s', $lockedBooking->reference, $reference),
                'recorded_by_user_id' => $staff->id,
                'notes' => $data->notes,
                'metadata' => [
                    'recorded_by_name' => $staff->name,
                    'external_reference' => $data->externalReference,
                ],
                'settled_at' => $now,
            ]);

            // If booking was awaiting payment, advance to Confirmed
            if ($lockedBooking->status === BookingStatus::AwaitingPayment) {
                $this->lifecycleService->confirm(
                    booking: $lockedBooking,
                    staff: $staff,
                    channel: ConfirmationChannel::Phone,
                    notes: sprintf('Confirmed upon offline payment recorded by %s: %s', $staff->name, $data->notes),
                    proposal: $proposal
                );
            }

            // Generate receipt
            $this->documentService->generateReceipt($transaction);

            $this->auditService->record(
                action: 'payment.offline_recorded',
                actor: $staff,
                summary: "Recorded offline {$data->paymentMethod->label()} of {$transaction->currency} ".number_format($transaction->amount_minor_units / 100, 2)." for booking {$lockedBooking->reference}",
                metadata: [
                    'booking_id' => $lockedBooking->id,
                    'transaction_id' => $transaction->id,
                    'reference' => $transaction->reference,
                    'method' => $data->paymentMethod->value,
                    'amount_minor_units' => $data->amountMinorUnits,
                    'currency' => $data->currency,
                    'staff_id' => $staff->id,
                ],
                auditable: $transaction,
                actorType: 'staff'
            );

            $this->outboxService->record(
                eventType: 'booking.payment_offline_recorded',
                aggregate: $lockedBooking,
                payload: [
                    'booking_id' => $lockedBooking->id,
                    'transaction_id' => $transaction->id,
                    'reference' => $lockedBooking->reference,
                    'amount_minor_units' => $transaction->amount_minor_units,
                    'currency' => $transaction->currency,
                    'payment_method' => $data->paymentMethod->value,
                ],
                idempotencyKey: sprintf('evt_booking_%d_offline_txn_%d_v1', $lockedBooking->id, $transaction->id)
            );

            return $transaction;
        });
    }
}
