# Phase 13 Architecture Specification: Hosted Payments, Ledger & Financial Documents

## 1. Executive Summary & Policy Compliance

Phase 13 delivers provider-neutral hosted payment checkout, cryptographically signed webhook ingestion, an append-oriented transaction ledger, compensating refunds, versioned financial document generation, and operational reconciliation for Spinellanka.

### Key Policy & Invariant Boundaries:
1. **Zero Raw Card Data**: Spinellanka never collects, receives, logs, or stores PAN, CVV, or card authentication data. Payment collection is 100% delegated to provider-hosted checkout.
2. **Provider Neutrality**: Domain logic depends strictly on `HostedPaymentGatewayInterface` and `PaymentWebhookVerifierInterface`. The default runtime gateway is PayHere (CBSL-approved Sri Lankan aggregator), while deterministic `FakeHostedPaymentGateway` enables robust, reproducible offline and CI testing.
3. **Pillar A & B Integration**:
   - Sent proposals remain strictly immutable.
   - Confirmed bookings undergoing amendment maintain their active agreement (`pending_revision = true`). Online payment settlement atomically confirms the revision and clears `pending_revision`.
4. **Browser Return Decoupling**: Browser redirects from hosted payment gateways are treated strictly as display hints. Settlement is executed **only** via cryptographically verified server-to-server webhooks.
5. **Fail-Closed Provider Gate**: If gateway credentials or webhook secrets are unconfigured, provider code fails closed safely without breaking offline payment, guest control, or administrative operations.

---

## 2. Component Architecture & Data Flow

```
                           ┌────────────────────────────────────────┐
                           │      Guest Status / Pay Now UI         │
                           └──────────────────┬─────────────────────┘
                                              │ Creates Checkout Session
                                              ▼
                           ┌────────────────────────────────────────┐
                           │            PaymentService              │
                           └──────┬──────────────────────────┬──────┘
                                  │                          │
                 ┌────────────────▼──────────┐    ┌──────────▼───────────────┐
                 │ HostedPaymentGatewayInterf │    │   PaymentTransaction     │
                 │ (PayHere / Fake Adapter)  │    │      (Status: Pending)   │
                 └────────┬──────────────────┘    └──────────────────────────┘
                          │
                          ▼
            ┌─────────────────────────────┐
            │   Hosted Checkout Session   │
            │    (PayHere / Provider)     │
            └─────────────┬───────────────┘
                          │
         ┌────────────────┴────────────────┐
         │                                 │
         ▼ (Server-to-Server Callback)     ▼ (Browser Redirect)
 ┌──────────────────────────────┐   ┌───────────────────────────────┐
 │   POST /webhooks/payment/    │   │  GET /booking/{ref}/{token}/  │
 │          {provider}          │   │         payment/return        │
 └──────────────┬───────────────┘   └──────────────┬────────────────┘
                │ Cryptographic Verification       │ Display Pending /
                ▼                                  │ Contextual Result
 ┌──────────────────────────────┐                  │ (NEVER settles!)
 │  PaymentWebhookService       │                  ▼
 │  - Signature & Replay Checks │   ┌───────────────────────────────┐
 │  - Canonical Lock Sequence   │   │  Guest Status Page: Pending   │
 │  - Amount & Currency Checks  │   │  or Settled (via Webhook)     │
 │  - Idempotency Gate          │   └───────────────────────────────┘
 └──────────────┬───────────────┘
                │
                ├─────────────────────────────────────────────────┐
                ▼ (Advances Booking)                              ▼ (Generates Document)
 ┌──────────────────────────────┐                  ┌───────────────────────────────┐
 │  BookingLifecycleService     │                  │   FinancialDocumentService    │
 │  - confirm(booking, prop)    │                  │   - generateReceipt()         │
 │  - Channel: OnlinePayment    │                  │   - atomic sequence number    │
 └──────────────┬───────────────┘                  └──────────────┬────────────────┘
                │ Emits Outbox & Audit                            │
                ▼                                                 ▼
 ┌──────────────────────────────┐                  ┌───────────────────────────────┐
 │   Outbox & Audit Services    │                  │  Private Token-Gated Stream   │
 └──────────────────────────────┘                  └───────────────────────────────┘
```

---

## 3. Database Schema

### 3.1 Migration: `create_payment_tables`

#### Table: `payment_transactions`
- `id` (bigIncrements)
- `booking_id` (foreignId -> bookings, `restrictOnDelete` - ledger cannot be cascade deleted!)
- `proposal_id` (foreignId -> proposals, nullable, nullOnDelete)
- `reference` (string, unique, format: `TXN-{booking_ref}-{random_hex}`)
- `gateway_provider` (string: `payhere`, `fake`, `offline`)
- `gateway_transaction_id` (string, nullable, indexed)
- `payment_method` (string: `online_card`, `bank_transfer`, `cash`, `card_terminal`)
- `payment_type` (string: `full`, `deposit`, `balance`, `rent_extension`, `security_deposit`, `refund`)
- `status` (string: `pending`, `completed`, `failed`, `cancelled`, `refunded`, `partially_refunded`, `discrepancy`)
- `amount_minor_units` (bigInteger, signed to support compensating entries)
- `currency` (string, 3 chars: USD, LKR, EUR, etc.)
- `idempotency_key` (string, unique)
- `recorded_by_user_id` (foreignId -> users, nullable, nullOnDelete)
- `notes` (text, nullable)
- `metadata` (json, nullable)
- `settled_at` (dateTime, nullable, Asia/Colombo)
- `timestamps`

#### Table: `payment_events`
- `id` (bigIncrements)
- `payment_transaction_id` (foreignId -> payment_transactions, nullable, nullOnDelete)
- `provider` (string)
- `provider_event_id` (string, unique)
- `event_type` (string)
- `payload` (json, sanitized/redacted)
- `status` (string: `processed`, `ignored`, `failed`)
- `error_message` (text, nullable)
- `received_at` (dateTime, Asia/Colombo)
- `processed_at` (dateTime, nullable, Asia/Colombo)
- `timestamps`

#### Table: `webhook_receipts`
- `id` (bigIncrements)
- `provider` (string, indexed)
- `event_id` (string, indexed - NOT unique to allow aggregator delivery retries)
- `signature_valid` (boolean)
- `source_ip` (string, nullable)
- `payload_summary` (json, nullable)
- `received_at` (dateTime, Asia/Colombo)
- `timestamps`

#### Table: `refunds`
- `id` (bigIncrements)
- `payment_transaction_id` (foreignId -> payment_transactions, restrictOnDelete)
- `booking_id` (foreignId -> bookings, restrictOnDelete)
- `reference` (string, unique, format: `REF-{txn_ref}-{random_hex}`)
- `amount_minor_units` (bigInteger, positive amount refunded)
- `currency` (string, 3 chars)
- `reason` (text)
- `status` (string: `pending`, `completed`, `failed`)
- `gateway_refund_id` (string, nullable)
- `approved_by_user_id` (foreignId -> users)
- `processed_at` (dateTime, nullable, Asia/Colombo)
- `timestamps`

#### Table: `financial_documents`
- `id` (bigIncrements)
- `booking_id` (foreignId -> bookings, restrictOnDelete)
- `proposal_id` (foreignId -> proposals, nullable)
- `payment_transaction_id` (foreignId -> payment_transactions, nullable)
- `document_type` (string: `estimate`, `proposal`, `receipt`, `final_invoice`, `rental_agreement`)
- `document_number` (string, unique, format: `REC-{YYYY}-{SEQ:05d}`)
- `version` (unsignedInteger, default 1)
- `amount_minor_units` (bigInteger)
- `currency` (string, 3 chars)
- `file_path` (string, nullable)
- `checksum` (string, nullable)
- `metadata` (json, nullable)
- `issued_at` (dateTime, Asia/Colombo)
- `timestamps`

#### Table: `document_sequences`
- `id` (bigIncrements)
- `document_type` (string)
- `year` (unsignedSmallInteger)
- `last_number` (unsignedBigInteger, default 0)
- `timestamps`
- Unique index: `(document_type, year)`

---

## 4. Contracts, DTOs & Exception Hierarchy

### 4.1 Domain Enums
- **`App\Domain\Payment\Enums\PaymentStatus`**: `Pending`, `Completed`, `Failed`, `Cancelled`, `Refunded`, `PartiallyRefunded`, `Discrepancy`.
- **`App\Domain\Payment\Enums\PaymentMethod`**: `OnlineCard`, `BankTransfer`, `Cash`, `CardTerminal`.
- **`App\Domain\Payment\Enums\PaymentType`**: `Full`, `Deposit`, `Balance`, `RentExtension`, `SecurityDeposit`, `Refund`.
- **`App\Domain\Payment\Enums\RefundStatus`**: `Pending`, `Completed`, `Rejected`, `Failed`.
- **`App\Domain\Payment\Enums\FinancialDocumentType`**: `Estimate`, `Proposal`, `Receipt`, `FinalInvoice`, `RentalAgreement`.
- **`App\Domain\Booking\Enums\ConfirmationChannel`**: Add case `OnlinePayment = 'online_payment'`.

### 4.2 Interfaces
```php
namespace App\Domain\Payment\Contracts;

use App\Domain\Payment\DTOs\CheckoutSession;
use App\Domain\Payment\DTOs\PaymentVerificationResult;
use App\Models\Booking;
use App\Models\PaymentTransaction;
use App\Models\Proposal;
use Illuminate\Http\Request;

interface HostedPaymentGatewayInterface
{
    public function createCheckoutSession(
        Booking $booking,
        ?Proposal $proposal,
        PaymentTransaction $transaction,
        string $returnUrl,
        string $cancelUrl,
        string $notifyUrl
    ): CheckoutSession;

    public function queryTransaction(string $orderReference): ?PaymentVerificationResult;

    public function isConfigured(): bool;

    public function identifier(): string;
}

interface PaymentWebhookVerifierInterface
{
    public function verify(Request $request): PaymentVerificationResult;

    public function identifier(): string;
}
```

### 4.3 DTOs
```php
namespace App\Domain\Payment\DTOs;

readonly class CheckoutSession
{
    public function __construct(
        public string $gatewayProvider,
        public string $transactionReference,
        public string $checkoutUrl,
        public string $httpMethod, // 'POST' or 'GET'
        public array $formFields,  // POST fields for PayHere
    ) {}
}

readonly class PaymentVerificationResult
{
    public function __construct(
        public bool $isValid,
        public string $provider,
        public ?string $providerEventId,
        public ?string $providerTransactionId,
        public ?string $orderReference,
        public int $amountMinorUnits,
        public string $currency,
        public string $statusCode,
        public array $rawPayload,
        public ?string $errorMessage = null,
    ) {}
}
```

### 4.4 Domain Exceptions
- `InvalidWebhookSignatureException`: Thrown on cryptographic signature mismatch or missing signature headers/fields.
- `PaymentAmountMismatchException`: Thrown when gateway amount diverges from proposal amount.
- `PaymentCurrencyMismatchException`: Thrown when gateway currency diverges from proposal currency.
- `ProposalNotActionablePaymentException`: Thrown when payment arrives for an expired or superseded proposal.
- `DuplicatePaymentEventException`: Thrown or handled idempotently when a payment event has already settled.
- `RefundExceedsSettledAmountException`: Thrown when requested refund exceeds remaining refundable balance.
- `PaymentGatewayMisconfiguredException`: Thrown when attempting live checkout without configured merchant keys.

---

## 5. PayHere Sri Lanka Cryptographic Integration

### 5.1 Checkout Session Generation Hash (Customer POST Form)
```php
$merchantId = config('services.payhere.merchant_id');
$merchantSecret = config('services.payhere.merchant_secret');
$orderId = $transaction->reference; // e.g. "TXN-BKG-ABCD1234-8492"
$formattedAmount = number_format($transaction->amount_minor_units / 100, 2, '.', '');
$currency = strtoupper($transaction->currency);

$hashedSecret = strtoupper(md5($merchantSecret));
$hash = strtoupper(md5($merchantId . $orderId . $formattedAmount . $currency . $hashedSecret));
```

### 5.2 Webhook Callback Verification Hash (Server-to-Server)
Incoming parameters from PayHere:
- `merchant_id`, `order_id`, `payment_id`, `payhere_amount`, `payhere_currency`, `status_code`, `md5sig`.

```php
$merchantSecret = config('services.payhere.merchant_secret');
$hashedSecret = strtoupper(md5($merchantSecret));
$formattedAmount = number_format((float) $request->input('payhere_amount'), 2, '.', '');

$expectedSignature = strtoupper(md5(
    $request->input('merchant_id') .
    $request->input('order_id') .
    $formattedAmount .
    strtoupper($request->input('payhere_currency')) .
    $request->input('status_code') .
    $hashedSecret
));

$isAuthentic = hash_equals($expectedSignature, (string) $request->input('md5sig'));
```

---

## 6. Concurrency, Deadlock Prevention & Settlement Pipeline

### 6.1 Canonical Lock Hierarchy
Whenever multiple models are locked, the order must be strictly preserved:
**`Vehicle` $\rightarrow$ `Booking` $\rightarrow$ `Proposal` $\rightarrow$ `PaymentTransaction`**.

In the webhook handler:
1. Lookup `PaymentTransaction` by `$orderReference` snapshot (without lock).
2. Resolve `$booking = $transaction->booking; $proposal = $transaction->proposal; $vehicleId = $proposal?->vehicle_id ?? $booking->vehicle_id;`
3. Enter `DB::transaction(...)`:
   - `Vehicle::where('id', $vehicleId)->lockForUpdate()->firstOrFail();`
   - `Booking::where('id', $booking->id)->lockForUpdate()->firstOrFail();`
   - `if ($proposal) { Proposal::where('id', $proposal->id)->lockForUpdate()->firstOrFail(); }`
   - `PaymentTransaction::where('id', $transaction->id)->lockForUpdate()->firstOrFail();`

### 6.2 Settlement Flow
1. Check `payment_events.provider_event_id`: if already processed, return HTTP 200 `{"status": "already_processed"}` immediately.
2. Check `transaction.status`: if already `Completed`, return HTTP 200.
3. Assert `$verificationResult->statusCode === '2'` (PayHere success). If failed or cancelled (`-1`, `-2`), mark transaction `Failed`, record event, and exit cleanly with HTTP 200.
4. Verify amount and currency parity against `$transaction->amount_minor_units` and `$transaction->currency`.
5. Check total settled amount: if `$existingSettled + $amount > $proposal->total_minor_units`, transition to `PaymentStatus::Discrepancy`, record alert, return HTTP 200.
6. Check proposal status:
   - If `$proposal->isExpired()` or `$booking->status->isTerminal()`:
     - Mark `$transaction->status = PaymentStatus::Completed`.
     - Record audit event: `payment.settled_on_expired_proposal`.
     - Do NOT call `confirm()`. Flag for manual operations reconciliation.
     - Return HTTP 200.
   - If actionable (`in_array($proposal->status, [ProposalStatus::Sent, ProposalStatus::Accepted])`):
     - Mark `$transaction->status = PaymentStatus::Completed`, `settled_at = now()`.
     - Call `BookingLifecycleService::confirm($booking, staff: null, channel: ConfirmationChannel::OnlinePayment, notes: "Automated payment settlement via {$provider} txn {$gatewayTxnId}", proposal: $proposal)`.
     - Call `FinancialDocumentService::generateReceipt($transaction)`.
     - Emit `booking.payment_settled` outbox event with explicit idempotency key: `evt_booking_{$booking->id}_txn_{$transaction->id}_settled_v1`.
     - Return HTTP 200 `{"status": "success"}`.

