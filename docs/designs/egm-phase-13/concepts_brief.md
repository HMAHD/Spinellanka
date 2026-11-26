# EGM Phase 13 Concepts Brief: Hosted Payments, Ledger & Financial Documents

## 1. Problem Statement & Context
Following Phase 12, bookings requiring online card payments (full total or security deposit) pause safely at `AwaitingPayment` (or remain `Confirmed` with `pending_revision = true` during amendments). Spinellanka must now bridge this state to final confirmed fulfillment without compromising security, handling raw payment credentials, or relying on untrusted client browser returns.

In the Sri Lankan context, tourism mobility businesses utilize Central Bank of Sri Lanka (CBSL)-approved payment aggregators (specifically PayHere, supporting Visa, Mastercard, AMEX, and international multi-currency processing in USD and LKR with 3D Secure verification).

Spinellanka requires:
1. Provider-neutral hosted checkout session generation.
2. Cryptographically signed, idempotent server-to-server webhook verification.
3. An append-oriented transaction ledger where payments, offline receipts, and refunds exist as permanent immutable records.
4. Defense-in-depth protections ensuring browser returns never settle payments and race conditions never double-confirm bookings.
5. Versioned financial document generation (receipts, invoices, agreements).
6. Operational reconciliation of ambiguous states.

---

## 2. Core Architectural Pillars

### Pillar A: Provider-Neutral Payment Gateway & Webhook Contracts
- Application code interacts solely with `HostedPaymentGatewayInterface` and `PaymentWebhookVerifierInterface`.
- **Reference Sri Lankan Gateway Adapter**: `PayHereHostedPaymentGateway` and `PayHereWebhookVerifier` (CBSL compliant, multi-currency USD/LKR, MD5/SHA256 signature verification, server-to-server callbacks).
- **Deterministic Test Adapter**: `FakeHostedPaymentGateway` and `FakePaymentWebhookVerifier` for robust, deterministic local and CI testing without external dependencies.
- **Fail-Closed Provider Gate**: If merchant credentials or signature secrets are missing, external provider integration fails closed without crashing domain services or booking flows.

### Pillar B: Append-Oriented Transaction Ledger & Compensating Records
- The payment ledger is strictly append-oriented. Historical transactions are never mutated or deleted.
- Every payment attempt, settlement, or offline collection is recorded in `payment_transactions` with status (`pending`, `completed`, `failed`, `cancelled`), method (`online_card`, `bank_transfer`, `cash`, `card_terminal`), and payment type (`full`, `deposit`, `balance`, `extension`).
- Refunds are modeled as compensating records in a dedicated `refunds` table, paired with an offsetting transaction entry.
- Offline payments recorded by staff (cash to driver, bank wire) share the identical transaction model, audit trail, and receipt generation pipeline as online card payments.

### Pillar C: Cryptographic Webhook Settlement & Invariant Verification
- A payment is settled **only** when a valid, cryptographically verified server-to-server webhook is received and verified against 7 mandatory gates:
  1. **Signature Verification**: Validated against the shared merchant secret before parsing.
  2. **Timestamp & Replay Protection**: Verified within allowable tolerance windows.
  3. **Merchant Account Alignment**: Matched against the configured merchant ID.
  4. **Booking & Proposal Scope**: Matched against active `Booking` and immutable `Proposal` records.
  5. **Amount & Currency Parity**: Minor-unit amount and ISO currency must match the proposal snapshot exactly.
  6. **Proposal Actionability**: The proposal must not be expired, cancelled, or superseded.
  7. **Strict Idempotency**: Each unique provider transaction/event ID is processed exactly once via `webhook_receipts` and `payment_events`.

### Pillar D: Browser Return Decoupling & Display Context
- When a traveler finishes hosted payment and returns to `/booking/{reference}/{token}/payment/return`, browser parameters are treated strictly as display hints.
- The browser return endpoint **never** confirms a booking or marks a transaction settled.
- If the webhook has not yet been processed, the guest status page displays a polite "Awaiting Gateway Confirmation" pending state. Settlement occurs asynchronously via webhook.

### Pillar E: Versioned Financial Documents
- Financial documents are modeled in `financial_documents` with document type (`estimate`, `proposal`, `receipt`, `final_invoice`, `rental_agreement`), sequential reference, version number, immutable totals, and generated artifacts.
- When an online payment or offline transaction completes, a `Receipt` is generated automatically, made accessible on the private guest control page, and recorded in outbox events.

### Pillar F: Staff Controls, Reconciliation & Privacy Boundaries
- **Reconciliation Command**: `payments:reconcile` runs via scheduler to detect stale pending transactions (>30 min) and reconcile discrepancies.
- **Re-Authentication & Permissions**: Recording offline payments requires `payment.record_offline`; issuing refunds requires `payment.refund` with mandatory staff password re-authentication.
- **Zero Card Data Principle**: Spinellanka never collects, receives, logs, or stores Primary Account Numbers (PAN), CVVs, or card authentication data. Provider payloads are sanitized before archival.

