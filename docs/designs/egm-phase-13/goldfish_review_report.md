# EGM Phase 13 Goldfish Review Report: Hosted Payments, Ledger & Documents

**Review Date:** 2026-11-26  
**Target Documents:** `docs/designs/egm-phase-13/concepts_brief.md`, `docs/designs/egm-phase-13/design_draft.md`  
**Reviewers:**
1. Hostile Design Critic (`egm-design`)
2. Technical Quality Auditor (`egm-codebase-details`)
3. Pragmatic Tech Lead (`egm-implementation-readiness`)

---

## 1. Consolidated Review Findings & Consensus Decisions

| ID | Finding & Threat Vector | Severity | Reviewer(s) | Consensus Architectural Resolution |
| :--- | :--- | :--- | :--- | :--- |
| **REV-01** | **Confirmation Channel & Staff Guard Crash**: `ConfirmationChannel` lacks `OnlinePayment`, and `confirm()` throws if staff is null without `GuestPrivateLink`. Also missing `$notes` argument. | **Blocker** | Auditor, Tech Lead | Add `ConfirmationChannel::OnlinePayment = 'online_payment'`. Update `BookingLifecycleService::confirm()` to permit `OnlinePayment` with null `$staff`. Pass automated transaction notes. |
| **REV-02** | **Gateway Status Code Check**: Draft ignored `status_code`, potentially confirming bookings on declined/cancelled callbacks (`status_code = -2`). | **Blocker** | Design Critic | Verifier and settlement service must explicitly assert `status_code === '2'` (PayHere Success) before initiating confirmation. Failed callbacks mark transaction `Failed`. |
| **REV-03** | **Global Lock Sequence**: Inverted locks between `PaymentTransaction`, `Booking`, and `Vehicle` risk InnoDB deadlocks. | **Blocker** | Design Critic, Auditor | Enforce canonical hierarchy across all domains: `Vehicle` $\rightarrow$ `Booking` $\rightarrow$ `Proposal` $\rightarrow$ `PaymentTransaction`. Webhook handler resolves IDs first, then acquires locks in this strict order. |
| **REV-04** | **Concurrent Webhook Ingestion & Retries**: Duplicate webhooks could race or crash on `webhook_receipts.event_id` unique constraint. | **Blocker** | Design Critic, Auditor | Remove `unique` from `webhook_receipts.event_id` (treat it as an append-only delivery log). Enforce idempotency on `payment_events.provider_event_id` and check transaction status inside row lock. |
| **REV-05** | **Double-Settlement & Overpayment**: Webhook arrival after offline payment or concurrent checkouts could double-charge or over-confirm. | **Blocker** | Design Critic | Calculate settled balance before confirmation: if settled amount + transaction amount > proposal total, transition to `PaymentStatus::Discrepancy` / flag for staff reconciliation, do not double-confirm. |
| **REV-06** | **Orphaned Funds on Expired Proposals**: Webhook arriving after 15-min hold expiry or cancellation could discard paid funds or crash on expired hold confirmation. | **Blocker** | Design Critic, Auditor | Decouple ledger capture from booking confirmation: mark transaction `Completed`, do NOT confirm booking into expired schedule block, flag booking with `payment.settled_on_expired_proposal` for staff resolution, return HTTP 200 to gateway. |
| **REV-07** | **Refund Enum & Ledger Invariant**: `PaymentType` lacked `Refund`, and `cascadeOnDelete` on `payment_transactions` violates ledger immutability. | **Blocker** | Design Critic, Auditor, Tech Lead | Add `Refund = 'refund'` to `PaymentType`. Change `cascadeOnDelete` to `restrictOnDelete` on `payment_transactions.booking_id`. Store negative minor units for compensating entries. |
| **REV-08** | **Document Concurrency & Privacy**: Document number generation could collide on `MAX(id)`; PDF/HTML files on public disk risk PII leakage. | **Blocker** | Design Critic, Tech Lead | Atomic document sequence generator via locked sequence counter. Financial documents rendered via token-authenticated private controller with `noindex` and `Cache-Control: private, no-store`. |
| **REV-09** | **PayHere Cryptographic Quirks**: Exact MD5 hashing, uppercase inner/outer secret, amount formatting, and timing-safe comparison (`hash_equals`). | **Major** | Design Critic, Tech Lead | Document and implement exact formulas: `strtoupper(md5($merchant_id . $order_id . number_format($amount / 100, 2, '.', '') . $currency . strtoupper(md5($merchant_secret))))` and webhook signature formula with `$status_code`. |
| **REV-10** | **Reconciliation Interface Gap**: Interface lacked query capability for scheduled reconciliation. | **Major** | Design Critic | Add `queryTransaction(string $orderReference): ?PaymentVerificationResult` to `HostedPaymentGatewayInterface`. |
| **REV-11** | **CSRF Exemption for Webhooks**: Laravel 13 `routes/web.php` middleware group enforces CSRF on all routes. | **Major** | Tech Lead | Exempt `webhooks/payment/*` in `bootstrap/app.php` via `$middleware->validateCsrfTokens(except: ['webhooks/payment/*'])`. |
| **REV-12** | **Proposal Actionability Semantics**: Draft checked `isActionable()`, which returns false for `Accepted` proposals. | **Major** | Auditor | Change settlement condition to explicit check: `in_array($proposal->status, [ProposalStatus::Sent, ProposalStatus::Accepted], true) && ! $proposal->isExpired()`. |

---

## 2. Implementation Readiness Protocol

All reviewers agreed that once the above 12 resolutions are integrated into `docs/designs/phase-13-payments-documents.md`, the architecture is 100% implementation-ready with zero ambiguity.

