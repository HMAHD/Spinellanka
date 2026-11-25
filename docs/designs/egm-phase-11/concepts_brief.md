# Phase 11 Concepts Brief: Transactional Outbox, Notifications & cPanel Resilience

* **Author:** Lead System Architect
* **Date:** 2026-11-25
* **Target Phase:** Phase 11 (`phase/11-outbox-notifications`)
* **Role:** Lead System Architect (Full Elephant-Goldfish Model Workflow)

---

## 1. Context & Business Domain

Spinellanka is an advance-booking luxury mobility platform in Sri Lanka operating across four core verticals:
1. **Airport Pickup** (advance flight arrival greeting);
2. **Airport Transfer** (origin to airport departure);
3. **Ride** (chauffeured multi-stop/point-to-point journey);
4. **Rent** (self-drive exact-vehicle hire).

In all four verticals, travelers submit reservations requiring operations review, manual confirmation, or quotation. Immediate communication with travelers (via WhatsApp and optional Email) and internal operations alerts are critical for trust. However, external notification providers (Meta WhatsApp Cloud API, Postmark) are inherently prone to transient latency, network partition, downtime, rate-limiting, and template rejection.

### The Fundamental Transactional Challenge
Under no circumstances may a notification failure cause a booking, schedule block, proposal, or payment transaction to roll back. Conversely, if a booking transaction rolls back due to a concurrent vehicle hold collision or validation error, no notification must ever be dispatched to the customer.

Furthermore, Spinellanka is deployed on shared cPanel hosting without persistent background daemons (like Redis/Horizon/Supervisor). Background execution must operate via one-minute cPanel cron draining database-backed queues without worker overlap.

---

## 2. Core Architectural Questions & Trade-Offs

### 2.1 Pattern: Direct Synchronous Dispatch vs Application Events vs Transactional Outbox
* **Option A: Synchronous HTTP Calls inside Booking Controllers/Services**
  * *Verdict*: **Strictly Rejected**. Binds booking transactions to external network latency (often 1–5 seconds per provider). If Meta or Postmark hangs or fails, the database connection stays locked, holds can fail, or the customer experiences an HTTP 500 while their booking actually succeeded.
* **Option B: Laravel In-Memory Events (`event(new BookingSubmitted($booking))`)**
  * *Verdict*: **Rejected for Durability**. If the event listener dispatches to a queue after commit, a crash between commit and queue enqueue drops the notification forever. If the queue is down, the event is lost.
* **Option C: Transactional Outbox Pattern (`outbox_events` + `message_deliveries`)**
  * *Verdict*: **Selected & Required by `docs/planning/02-system-architecture.md`**. An `outbox_events` record is inserted in the exact same `DB::transaction` as the `Booking` state change. Atomicity guarantees that if the booking commits, the outbox record commits; if the booking rolls back, the outbox record rolls back. An asynchronous worker or job then processes the outbox event and records attempts in `message_deliveries`.

### 2.2 Table Hierarchy: Single Table vs Normalized Two-Tier (`outbox_events` + `message_deliveries`)
* **Option A: Single `notifications` Table**
  * Blends the domain event trigger with channel delivery state.
  * *Verdict*: **Rejected**. A single domain event (e.g. `booking.submitted`) often triggers multiple messages across different channels (e.g. WhatsApp to booker, Email to booker, Internal alert to staff). A single table either duplicates event payloads or forces denormalized arrays.
* **Option B: Normalized Two-Tier Schema**
  * `outbox_events`: Stores the domain event type (`event_type`), aggregate type/ID (`aggregate_type`, `aggregate_id`), payload version (`payload_version`), immutable minimal payload (`payload`), stable idempotency key (`idempotency_key`), and processing status (`pending`, `processed`, `failed`).
  * `message_deliveries`: Stores individual channel dispatches (`channel`: `whatsapp`, `email`, `internal`), template reference (`template_name`, `template_version`), recipient snapshot (`recipient_phone`, `recipient_email`), provider identifier (`provider`), external reference (`provider_message_id`), attempts count, status (`pending`, `delivered`, `failed`, `delivery_unknown`), last error, and timestamps.
  * *Verdict*: **Selected**. Matches `docs/planning/03-domain-and-data-model.md` section "Messaging and integrations".

### 2.3 Provider Abstraction & Adapter Strategy
* **Contracts**:
  * `WhatsAppSender`: `send(WhatsAppMessage $message): DeliveryResult`
  * `TransactionalMailer`: `send(EmailMessage $message): DeliveryResult`
* **Implementations**:
  * `FakeWhatsAppSender` & `FakeTransactionalMailer`: Deterministic, in-memory recording, configurable simulated responses (success, retryable network failure, non-retryable 400 error, timeout ambiguity). Used by all automated tests.
  * `MetaWhatsAppSender`: Meta WhatsApp Cloud API v21.0/v22.0. Requires approved Meta Business account, phone number ID, system user token, and approved templates. Disabled when credentials are not configured.
  * `PostmarkTransactionalMailer`: Postmark REST API. Requires verified sending domain and server token. Disabled when credentials are not configured.
  * *Security Gate*: Production secrets are never committed. Live adapters are kept disabled until explicit client approval and account provisioning.

### 2.4 Retry Policy, Idempotency & Ambiguous Acceptance (`delivery_unknown`)
* **Named Failure Classes**:
  1. *Transient / Retryable*: HTTP 429 (Rate Limit), HTTP 500/502/503/504 (Server Error), Connection Timeout before handshake.
     * Policy: Exponential backoff with jitter (e.g. 3 attempts: +30s, +2m, +10m).
  2. *Permanent / Non-Retryable*: HTTP 400 (Bad Request), Invalid Phone Number / Unsubscribed, Unapproved Template Name, Missing Template Variable.
     * Policy: Immediate failure (`failed`). Never retried automatically. Staff visibility alerted.
  3. *Ambiguous / In-Flight (`delivery_unknown`)*: Request was transmitted, but socket timed out waiting for HTTP response headers, or provider responded with ambiguous 202/unknown status.
     * **Safety Invariant**: NEVER auto-retry an ambiguous send unless provider idempotency or a status reconciliation lookup proves that the previous attempt was not delivered. Auto-retrying an ambiguous WhatsApp message risks sending duplicate messages to international travelers.
     * Policy: Mark as `delivery_unknown`. Requires staff review and manual reconciliation/retry.

### 2.5 Data Minimization & Secret Redaction
* Payloads must contain only necessary contextual fields:
  * Booking Reference, Service Type, Pickup/Service Date & Time in `Asia/Colombo`, Vehicle Make/Model, Booker First Name, Status Page URL.
* Prohibited from Outbox Payloads & Logs:
  * Internal staff notes, customer payment card/CVV data, driver personal contact (before confirmed assignment), API tokens, webhook signing secrets.
* Recipient phone and email must be masked in staff lists and logs (e.g., `+94 77 ••••• 89`, `j•••••@example.com`).

### 2.6 cPanel Execution & Non-Overlapping Drain
* Shared hosting runs cron every 60 seconds.
* Command: `php artisan outbox:drain`
* Mechanism:
  * Acquires atomic lock: `Cache::lock('spinellanka_outbox_drain', 55)->get()`.
  * Runs queue worker with `--stop-when-empty --max-time=50 --max-jobs=50`.
  * Releases lock on exit.
  * Prevents worker pile-up or memory leakage on shared hosting.

---

## 3. Scope Boundaries for Phase 11

### In-Scope:
1. `outbox_events` and `message_deliveries` database tables and Eloquent models with indexes and rollback migrations.
2. `OutboxService` for transactional event recording within domain service methods (`submit`, `confirm`, `decline`, `cancel`).
3. Provider-neutral `WhatsAppSender` and `TransactionalMailer` interfaces and `DeliveryResult` DTO.
4. `FakeWhatsAppSender` and `FakeTransactionalMailer` for deterministic Pest tests.
5. `MetaWhatsAppSender` and `PostmarkTransactionalMailer` real adapters (disabled without secrets).
6. Queue jobs: `ProcessOutboxEventJob` and `SendMessageDeliveryJob` with retry classification and `delivery_unknown` safety.
7. cPanel-compatible `outbox:drain` command with non-overlapping cache lock.
8. Filament staff visibility: Deliveries relation manager in `BookingResource` and staff manual retry action with `BookingConfirm`/`BookingReview` permission checks.
9. Comprehensive test suite covering rollback, idempotency, retry bounds, ambiguity handling, redaction, and worker non-overlap.

### Non-Scope:
* No marketing campaigns or bulk newsletters.
* No inbound WhatsApp bot or conversational AI webhook routing.
* No arbitrary free-text staff customer messaging composer.
* No SMS provider (WhatsApp and Email only).
* No customer login or registration.

