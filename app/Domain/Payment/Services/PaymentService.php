<?php

declare(strict_types=1);

namespace App\Domain\Payment\Services;

use App\Domain\Audit\Services\AuditService;
use App\Domain\Booking\Enums\PaymentOption;
use App\Domain\Booking\Enums\ProposalStatus;
use App\Domain\Payment\Contracts\HostedPaymentGatewayInterface;
use App\Domain\Payment\DTOs\CheckoutSession;
use App\Domain\Payment\Enums\PaymentMethod;
use App\Domain\Payment\Enums\PaymentStatus;
use App\Domain\Payment\Enums\PaymentType;
use App\Domain\Payment\Exceptions\ProposalNotActionablePaymentException;
use App\Models\Booking;
use App\Models\PaymentTransaction;
use App\Models\Proposal;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    public function __construct(
        protected HostedPaymentGatewayInterface $gateway,
        protected AuditService $auditService,
    ) {}

    /**
     * Initiate a hosted payment session for a proposal.
     */
    public function initiateCheckout(
        Booking $booking,
        Proposal $proposal,
        PaymentOption $paymentOption,
        ?string $returnUrl = null,
        ?string $cancelUrl = null,
        ?string $notifyUrl = null,
    ): CheckoutSession {
        if ($proposal->isExpired() || ! in_array($proposal->status, [ProposalStatus::Sent, ProposalStatus::Accepted], true)) {
            throw new ProposalNotActionablePaymentException("Proposal {$proposal->reference} is not actionable for checkout.");
        }

        return DB::transaction(function () use ($booking, $proposal, $paymentOption, $returnUrl, $cancelUrl, $notifyUrl) {
            // Cancel any previous pending transactions for this proposal to prevent duplicate sessions
            PaymentTransaction::where('booking_id', $booking->id)
                ->where('proposal_id', $proposal->id)
                ->where('status', PaymentStatus::Pending->value)
                ->update(['status' => PaymentStatus::Cancelled->value]);

            $isDeposit = $paymentOption === PaymentOption::Deposit && $proposal->deposit_required;
            $amountMinorUnits = $isDeposit
                ? $proposal->deposit_amount_minor_units
                : $proposal->total_amount_minor_units;

            $paymentType = $isDeposit ? PaymentType::Deposit : PaymentType::Full;
            $reference = PaymentTransaction::generateReference($booking);
            $idempotencyKey = sprintf('txn_init_%s_v%d_%s', $booking->reference, $proposal->version, $reference);

            /** @var PaymentTransaction $transaction */
            $transaction = PaymentTransaction::create([
                'booking_id' => $booking->id,
                'proposal_id' => $proposal->id,
                'reference' => $reference,
                'gateway_provider' => $this->gateway->identifier(),
                'gateway_transaction_id' => null,
                'payment_method' => PaymentMethod::OnlineCard,
                'payment_type' => $paymentType,
                'status' => PaymentStatus::Pending,
                'amount_minor_units' => $amountMinorUnits,
                'currency' => $proposal->currency,
                'idempotency_key' => $idempotencyKey,
                'recorded_by_user_id' => null,
                'notes' => sprintf('Online checkout initiated via %s', $this->gateway->identifier()),
                'metadata' => [
                    'payment_option' => $paymentOption->value,
                    'proposal_version' => $proposal->version,
                ],
                'settled_at' => null,
            ]);

            $defaultReturnUrl = route('booking.payment.return', ['reference' => $booking->reference, 'token' => 'guest_flow']);
            $defaultCancelUrl = route('booking.payment.cancel', ['reference' => $booking->reference, 'token' => 'guest_flow']);
            $defaultNotifyUrl = route('webhooks.payment', ['provider' => $this->gateway->identifier()]);

            $session = $this->gateway->createCheckoutSession(
                booking: $booking,
                proposal: $proposal,
                transaction: $transaction,
                returnUrl: $returnUrl ?: $defaultReturnUrl,
                cancelUrl: $cancelUrl ?: $defaultCancelUrl,
                notifyUrl: $notifyUrl ?: $defaultNotifyUrl
            );

            $this->auditService->record(
                action: 'payment.checkout_initiated',
                actor: null,
                summary: "Guest initiated online checkout for booking {$booking->reference} ({$transaction->reference})",
                metadata: [
                    'booking_id' => $booking->id,
                    'proposal_id' => $proposal->id,
                    'transaction_id' => $transaction->id,
                    'reference' => $transaction->reference,
                    'amount_minor_units' => $amountMinorUnits,
                    'currency' => $proposal->currency,
                    'provider' => $this->gateway->identifier(),
                ],
                auditable: $transaction,
                actorType: 'guest'
            );

            return $session;
        });
    }

    public function findByReference(string $reference): ?PaymentTransaction
    {
        return PaymentTransaction::where('reference', $reference)->first();
    }
}
