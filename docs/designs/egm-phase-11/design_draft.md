# Phase 11 Design Draft: Transactional Outbox & Notification Resilience

* **Author:** Lead System Architect
* **Date:** 2026-11-25
* **Target Phase:** Phase 11 (`phase/11-outbox-notifications`)
* **Role:** Lead System Architect (Full Elephant-Goldfish Model Workflow)

---

## 1. System Invariants & Core Rules

1. **Transactional Atomicity**: An `outbox_events` record is created inside the same database transaction as the booking mutation (`submit`, `confirm`, `decline`, `cancel`). If the booking transaction rolls back, no outbox event exists.
2. **Failure Decoupling**: External provider failures (WhatsApp/email errors, network drops) NEVER roll back or alter booking state. The traveler reference and private link on screen remain the primary recovery mechanism.
3. **Idempotency at Every Tier**:
   - `outbox_events.idempotency_key` is unique (`evt_{aggregate_type}_{aggregate_id}_{event_type}_{version}`).
   - `message_deliveries.idempotency_key` is unique (`deliv_{outbox_event_id}_{channel}_{recipient_hash}`).
   - Duplicate queue job execution or retry attempts never send duplicate customer notifications where provider status or idempotency can prevent it.
4. **Ambiguous Acceptance Handling (`delivery_unknown`)**: If a provider request times out after transmission or returns an ambiguous accepted status without definitive delivery confirmation, the delivery state transitions to `delivery_unknown`. Automatic retry is strictly FORBIDDEN unless provider idempotency or status lookup proves safety. Manual staff reconciliation is required.
5. **Provider Neutrality & Isolation**: Application code depends solely on `WhatsAppSender` and `TransactionalMailer` interfaces. Provider implementations (Meta WhatsApp Cloud API, Postmark) remain disabled until official client accounts, domain verification, and template approvals are supplied. Fake adapters run all automated test suites.
6. **Data Minimization & Redaction**: Payloads store only non-sensitive template parameters (booking reference, vehicle make/model, service time in `Asia/Colombo`, customer first name). Internal notes, credit card data, driver contact (prior to dispatch), and secrets are strictly excluded. Recipient phone and email are masked in staff displays and logs.
7. **cPanel Short-Lived Execution**: Background workers execute via one-minute cPanel cron using an atomic non-overlapping cache lock (`spinellanka_outbox_drain`) with `--stop-when-empty --max-time=50 --max-jobs=50` to prevent worker pile-up on shared hosting.

---

## 2. Relational Database Schema

### 2.1 Table: `outbox_events`
Records domain events intended for external communication and background integration.

```sql
CREATE TABLE outbox_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_type VARCHAR(64) NOT NULL,
    aggregate_type VARCHAR(64) NOT NULL,
    aggregate_id BIGINT UNSIGNED NOT NULL,
    idempotency_key VARCHAR(128) NOT NULL UNIQUE,
    payload_version INT UNSIGNED NOT NULL DEFAULT 1,
    payload JSON NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'pending', -- pending, processing, processed, failed
    attempts INT UNSIGNED NOT NULL DEFAULT 0,
    last_error TEXT NULL,
    processed_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX idx_outbox_events_status_created (status, created_at),
    INDEX idx_outbox_events_aggregate (aggregate_type, aggregate_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 2.2 Table: `message_deliveries`
Records individual channel deliveries spawned from an outbox event.

```sql
CREATE TABLE message_deliveries (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    outbox_event_id BIGINT UNSIGNED NOT NULL,
    booking_id BIGINT UNSIGNED NULL,
    channel VARCHAR(32) NOT NULL, -- whatsapp, email, staff_alert
    recipient_type VARCHAR(32) NOT NULL, -- booker, lead_traveler, staff
    recipient_identifier VARCHAR(191) NOT NULL, -- phone number or email address
    template_name VARCHAR(64) NOT NULL,
    template_version VARCHAR(32) NOT NULL DEFAULT 'v1',
    template_params JSON NOT NULL,
    idempotency_key VARCHAR(128) NOT NULL UNIQUE,
    provider VARCHAR(64) NOT NULL, -- fake, meta_whatsapp, postmark
    provider_message_id VARCHAR(191) NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'pending', -- pending, sending, sent, delivered, failed, delivery_unknown
    attempts INT UNSIGNED NOT NULL DEFAULT 0,
    max_attempts INT UNSIGNED NOT NULL DEFAULT 3,
    last_attempted_at TIMESTAMP NULL,
    delivered_at TIMESTAMP NULL,
    failed_at TIMESTAMP NULL,
    last_error_code VARCHAR(64) NULL,
    last_error_message TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    CONSTRAINT fk_message_deliveries_outbox_event FOREIGN KEY (outbox_event_id) REFERENCES outbox_events (id) ON DELETE CASCADE,
    CONSTRAINT fk_message_deliveries_booking FOREIGN KEY (booking_id) REFERENCES bookings (id) ON DELETE CASCADE,
    INDEX idx_deliveries_status_attempts (status, attempts),
    INDEX idx_deliveries_booking_channel (booking_id, channel)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 3. Provider Abstractions & DTOs

### 3.1 DTOs
* `WhatsAppMessage`:
  * `recipientPhone: string`
  * `templateName: string`
  * `templateParams: array<string, mixed>`
  * `idempotencyKey: string`
* `EmailMessage`:
  * `recipientEmail: string`
  * `subject: string`
  * `templateName: string`
  * `templateParams: array<string, mixed>`
  * `idempotencyKey: string`
* `DeliveryResult`:
  * `status: DeliveryResultStatus` (`Sent`, `Accepted`, `Failed`, `Unknown`)
  * `providerMessageId: ?string`
  * `errorCode: ?string`
  * `errorMessage: ?string`
  * `isRetryable: bool`
  * `rawResponse: ?array<string, mixed>`

### 3.2 Interfaces
* `WhatsAppSender`:
  ```php
  interface WhatsAppSender {
      public function send(WhatsAppMessage $message): DeliveryResult;
  }
  ```
* `TransactionalMailer`:
  ```php
  interface TransactionalMailer {
      public function send(EmailMessage $message): DeliveryResult;
  }
  ```

---

## 4. Dispatcher, Queue Processing & Bounded Retry Matrix

### 4.1 Queue Jobs
1. `ProcessOutboxEventJob`:
   - Enqueued on `outbox_events` creation (`after_commit`).
   - Reads the event, inspects the booking and contacts.
   - Generates corresponding `MessageDelivery` records (e.g. WhatsApp to Booker; Email to Booker if email provided).
   - Dispatches `SendMessageDeliveryJob` for each created delivery.
   - Updates `outbox_events.status = 'processed'`.
2. `SendMessageDeliveryJob`:
   - Processes an individual `MessageDelivery`.
   - Acquires delivery row under lock or checks status (`pending`, `failed`, `delivery_unknown`).
   - Invokes the appropriate sender (`WhatsAppSender` or `TransactionalMailer`).
   - Evaluates `DeliveryResult`:
     - **Success (`Sent` / `Delivered`)**: Updates `status = 'delivered'`, `delivered_at = now()`, `provider_message_id`.
     - **Retryable Failure**: Increments attempts. If `attempts < max_attempts`, releases job with exponential backoff (`delay = 30 * (2 ** ($attempts - 1))`). If `attempts >= max_attempts`, marks `status = 'failed'`.
     - **Non-Retryable Failure**: Immediately marks `status = 'failed'`, records error code/message. Does not retry.
     - **Ambiguous Timeout (`Unknown`)**: Marks `status = 'delivery_unknown'`. DOES NOT auto-retry. Logs warning for staff review.

### 4.2 Retry Classification Matrix
| Error Type | Example | Classification | Auto-Retry Action |
|---|---|---|---|
| HTTP 429 | Rate limit reached | Transient | Backoff retry up to 3 attempts |
| HTTP 500/503 | Meta/Postmark server downtime | Transient | Backoff retry up to 3 attempts |
| Network Timeout | Socket timeout before HTTP connect | Transient | Backoff retry up to 3 attempts |
| HTTP 400 | Invalid phone number / bad format | Permanent | Mark `failed`, 0 auto-retries |
| HTTP 404 | Template not approved / missing | Permanent | Mark `failed`, 0 auto-retries |
| Recipient Opt-Out | Recipient blocked WhatsApp business | Permanent | Mark `failed`, 0 auto-retries |
| HTTP Read Timeout | Transmitted, but timed out waiting for response | Ambiguous | Mark `delivery_unknown`, 0 auto-retries |
| HTTP 202 Unknown | Provider accepted with no message ID | Ambiguous | Mark `delivery_unknown`, 0 auto-retries |

---

## 5. Staff Visibility & Operations in Filament

1. **Relation Manager**: `MessageDeliveriesRelationManager` attached to `BookingResource`:
   - Columns: Channel (`whatsapp`, `email`), Recipient (Masked: `+94 77 ••••• 89`), Template, Status Badge (`delivered` [green], `pending` [yellow], `delivery_unknown` [amber], `failed` [red]), Attempts, Provider Ref, Created At.
2. **Staff Manual Retry Action**:
   - Authorized only for users with `Permission::BookingUpdate` or `Permission::BookingConfirm`.
   - Available on deliveries in `failed` or `delivery_unknown` state.
   - Prompts for confirmation: "Confirm manual re-send. If this message was already received by the customer, re-sending may produce a duplicate."
   - Emits an append-only `AuditEvent`: `message_delivery.manually_retried`.
   - Resets status to `pending`, increments retry ceiling, and dispatches job immediately.

---

## 6. cPanel Queue Drain & Scheduler

1. **Artisan Command**: `app/Console/Commands/DrainOutboxQueueCommand.php` (`outbox:drain`):
   - Uses `Cache::lock('spinellanka_outbox_drain', 55)` to ensure non-overlap.
   - Invokes `Artisan::call('queue:work', ['--stop-when-empty' => true, '--max-time' => 50, '--max-jobs' => 50])`.
   - Records execution metrics and handles graceful shutdown.
2. **Scheduled Task** in `routes/console.php`:
   - `Schedule::command('outbox:drain')->everyMinute()->withoutOverlapping();`

