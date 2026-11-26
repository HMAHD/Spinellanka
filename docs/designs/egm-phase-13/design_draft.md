# EGM Phase 13 Design Draft: Hosted Payments, Ledger & Documents

## 1. Component Architecture & Interfaces

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
                 └────────┬──────────────────┘    │      (Status: Pending)   │
                          │                       └──────────────────────────┘
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
 │  - Amount & Currency Checks  │   │  Guest Status Page: Pending   │
 │  - Idempotency Gate          │   │  or Settled (via Webhook)     │
 └──────────────┬───────────────┘   └───────────────────────────────┘
                │
                ├─────────────────────────────────────────────────┐
                ▼ (Advances Booking)                              ▼ (Generates Document)
 ┌──────────────────────────────┐                  ┌───────────────────────────────┐
 │  BookingLifecycleService     │                  │   FinancialDocumentService    │
 │  - confirm(booking, prop)    │                  │   - generateReceipt()         │
 │  - releases pending_revision │                  │   - store versioned metadata  │
 └──────────────┬───────────────┘                  └──────────────┬────────────────┘
                │ Emits Outbox & Audit                            │
                ▼                                                 ▼
 ┌──────────────────────────────┐                  ┌───────────────────────────────┐
 │   Outbox & Audit Services    │                  │  Guest View / Download Link   │
 └──────────────────────────────┘                  └───────────────────────────────┘
```

---

## 2. Contracts & DTOs

### 2.1 Domain Enums
- **`PaymentStatus`**: `Pending`, `Completed`, `Failed`, `Cancelled`, `Refunded`, `PartiallyRefunded`.
- **`PaymentMethod`**: `OnlineCard`, `BankTransfer`, `Cash`, `CardTerminal`.
- **`PaymentType`**: `Full`, `Deposit`, `Balance`, `RentExtension`, `SecurityDeposit`.
- **`FinancialDocumentType`**: `Estimate`, `Proposal`, `Receipt`, `FinalInvoice`, `RentalAgreement`.
- **`RefundStatus`**: `Pending`, `Completed`, `Rejected`, `Failed`.

### 2.2 Core Interfaces
```php
namespace App\Domain\Payment\Contracts;

use App\Domain\Payment\DTOs\CheckoutSession;
use App\Domain\Payment\DTOs\PaymentVerificationResult;
use App\Models\Booking;
use App\Models\Proposal;
use Illuminate\Http\Request;

interface HostedPaymentGatewayInterface
{
    /**
     * Create a hosted payment checkout session.
     */
    public function createCheckoutSession(
        Booking $booking,
        Proposal $proposal,
        int $amountMinorUnits,
        string $currency,
        string $returnUrl,
        string $cancelUrl,
        string $notifyUrl
    ): CheckoutSession;

    /**
     * Check whether this gateway adapter is configured and active.
     */
    public function isConfigured(): bool;

    /**
     * Provider identifier (e.g. 'payhere', 'fake').
     */
    public function identifier(): string;
}

interface PaymentWebhookVerifierInterface
{
    /**
     * Verify incoming webhook request signature and payload authenticity.
     */
    public function verify(Request $request): PaymentVerificationResult;

    /**
     * Provider identifier matching this verifier.
     */
    public function identifier(): string;
}
```

---

## 3. Database Schema

### 3.1 `payment_transactions` Table
- `id` (bigIncrements)
- `booking_id` (foreignId -> bookings, cascadeOnDelete)
- `proposal_id` (foreignId -> proposals, nullable, nullOnDelete)
- `reference` (string, unique, format: `TXN-{booking_ref}-{random}`)
- `gateway_provider` (string: `payhere`, `fake`, `offline`)
- `gateway_transaction_id` (string, nullable, indexed)
- `payment_method` (string: `online_card`, `bank_transfer`, `cash`, `card_terminal`)
- `payment_type` (string: `full`, `deposit`, `balance`, `rent_extension`, `security_deposit`)
- `status` (string: `pending`, `completed`, `failed`, `cancelled`, `refunded`, `partially_refunded`)
- `amount_minor_units` (bigInteger)
- `currency` (string, 3 chars: USD, LKR, EUR, etc.)
- `idempotency_key` (string, unique)
- `recorded_by_user_id` (foreignId -> users, nullable, nullOnDelete)
- `notes` (text, nullable)
- `metadata` (json, nullable)
- `settled_at` (dateTime, nullable, Asia/Colombo)
- `timestamps`

### 3.2 `payment_events` Table
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

### 3.3 `webhook_receipts` Table
- `id` (bigIncrements)
- `provider` (string, indexed)
- `event_id` (string, unique)
- `signature_valid` (boolean)
- `source_ip` (string, nullable)
- `payload_summary` (json, nullable)
- `received_at` (dateTime, Asia/Colombo)
- `timestamps`

### 3.4 `refunds` Table
- `id` (bigIncrements)
- `payment_transaction_id` (foreignId -> payment_transactions)
- `booking_id` (foreignId -> bookings)
- `reference` (string, unique, format: `REF-{txn_ref}-{random}`)
- `amount_minor_units` (bigInteger)
- `currency` (string, 3 chars)
- `reason` (text)
- `status` (string: `pending`, `completed`, `failed`)
- `gateway_refund_id` (string, nullable)
- `approved_by_user_id` (foreignId -> users)
- `processed_at` (dateTime, nullable, Asia/Colombo)
- `timestamps`

### 3.5 `financial_documents` Table
- `id` (bigIncrements)
- `booking_id` (foreignId -> bookings)
- `proposal_id` (foreignId -> proposals, nullable)
- `payment_transaction_id` (foreignId -> payment_transactions, nullable)
- `document_type` (string: `estimate`, `proposal`, `receipt`, `final_invoice`, `rental_agreement`)
- `document_number` (string, unique, e.g. `REC-2026-0001`)
- `version` (unsignedInteger, default 1)
- `amount_minor_units` (bigInteger)
- `currency` (string, 3 chars)
- `file_path` (string, nullable)
- `checksum` (string, nullable)
- `metadata` (json, nullable)
- `issued_at` (dateTime, Asia/Colombo)
- `timestamps`

---

## 4. Webhook Processing & Settlement Flow

1. **Endpoint**: `POST /webhooks/payment/{provider}`.
2. **Signature Verification**: Verifier checks hash against secret. If signature invalid:
   - Record in `webhook_receipts` (`signature_valid = false`).
   - Abort 400 Bad Request.
3. **Idempotency Gate**: Check `webhook_receipts` for `event_id`.
   - If already processed: return HTTP 200 immediately (`{"status": "already_processed"}`).
4. **Transaction & Proposal Match**:
   - Query transaction by `order_id` / `reference`.
   - Lock `Vehicle`, `Booking`, and `PaymentTransaction` rows deterministically.
   - Verify `$transaction->amount_minor_units === $webhook->amountMinorUnits`.
   - Verify `$transaction->currency === $webhook->currency`.
   - Verify `$booking->current_proposal_id === $transaction->proposal_id`.
   - Verify proposal is actionable (`status === ProposalStatus::Accepted` or `Sent`).
5. **Settlement Execution**:
   - Update `$transaction->status = PaymentStatus::Completed`, `settled_at = now()`.
   - Record `PaymentEvent`.
   - Call `BookingLifecycleService::confirm($booking, staff: null, channel: ConfirmationChannel::OnlinePayment, proposal: $proposal)`.
   - Call `FinancialDocumentService::generateReceipt($transaction)`.
   - Record audit event: `payment.settled`.
   - Record outbox event: `booking.payment_settled`.

---

## 5. Offline Payments, Refunds & Reconciliation

1. **Offline Payment**:
   - Staff action in Filament on `BookingResource` (`Record Offline Payment`).
   - Requires `payment.record_offline` permission.
   - Creates `PaymentTransaction` with `gateway_provider = 'offline'`, `payment_method = 'cash' | 'bank_transfer' | 'card_terminal'`, status `Completed`.
   - If booking was `AwaitingPayment`, automatically calls `BookingLifecycleService::confirm()`.
2. **Refunds**:
   - Staff action in Filament (`Issue Refund`).
   - Requires `payment.refund` permission and password re-authentication (`rule('current_password:web')`).
   - Checks `$refundAmount <= $transaction->remainingRefundableAmount()`.
   - Inserts `Refund` record and compensating negative `PaymentTransaction` of type `refund`.
3. **Reconciliation**:
   - Artisan command `payments:reconcile`: checks pending transactions older than 30 minutes, flags expired checkout sessions, or logs discrepancies.

