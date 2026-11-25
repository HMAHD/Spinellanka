# Spinellanka Phase 11: Transactional Outbox & Notification Resilience Architecture

## 1. Executive Summary & Architectural Invariants

Phase 11 implements the transactional outbox and notification subsystem for Spinellanka. It guarantees that domain state changes (booking submission, manual confirmation, decline, cancellation) and outbound communication intent commit atomically within a single database transaction, while completely decoupling booking persistence from external network latency and third-party provider failures.

### Key Business & Technical Invariants:
1. **Transactional Atomicity**: An `outbox_events` record is inserted inside the same `DB::transaction(...)` as the booking state mutation. If the booking transaction rolls back (e.g., availability collision or constraint error), the outbox event rolls back with it. No phantom notifications are ever dispatched.
2. **Failure Decoupling**: External communication failures (WhatsApp/email errors, gateway timeouts, provider rate-limiting) **NEVER** invalidate or roll back a booking. The traveler reference and private status URL on screen remain the primary recovery mechanism.
3. **Strict Idempotency at Every Tier**:
   - `outbox_events.idempotency_key` is unique (`evt_{aggregate_type}_{aggregate_id}_{event_type}_{version}`).
   - `message_deliveries.idempotency_key` is unique (`deliv_{outbox_event_id}_{channel}_{recipient_hash}`).
   - Duplicate worker executions or queue retries never send duplicate customer notifications where provider status or idempotency can prevent it.
4. **Ambiguous Acceptance Handling (`delivery_unknown`)**:
   - Meta WhatsApp Cloud API is fundamentally non-idempotent on dispatch and does not accept client-specified idempotency keys.
   - If an HTTP request times out after transmission or returns an ambiguous accepted status without a definitive message ID, the delivery state transitions to `delivery_unknown`.
   - Automatic retry of `delivery_unknown` is **strictly forbidden**. Manual staff reconciliation with duplicate-message warning and audit logging is required.
5. **Gateway Acceptance vs Handset Delivery**:
   - Synchronous HTTP 200 from Meta or Postmark confirms gateway acceptance (`sent`), not handset receipt (`delivered`).
   - Terminal status for successful synchronous dispatch is `sent` (with `sent_at = now()`). Handset receipt (`delivered`) is reserved for future webhook receipts.
6. **Data Minimization & Redaction**:
   - Payloads contain only minimal template variables (reference, customer first name, service time in `Asia/Colombo`, vehicle display name, status URL).
   - Internal staff notes, customer credit card data, driver personal contacts (prior to dispatch), and API secrets are strictly excluded.
   - Recipient phone and email are masked in staff tables and application logs.
7. **cPanel Shared Hosting Resilience**:
   - Non-daemon execution: Background jobs drain via one-minute cPanel cron.
   - Two-phase drain: An Outbox Sweeper polls orphaned pending events before running `queue:work`.
   - Worker runtime margins: Lock TTL 90s, worker `--max-time=35`, `--max-jobs=25`, HTTP timeout 8s max.
   - Schedule mutex: `withoutOverlapping(expiresAt: 2)->runInBackground()` prevents 24-hour scheduler deadlock if cPanel kills a worker.

---

## 2. Relational Database Schema

### 2.1 Table: `outbox_events`
Stores domain events generated within database transactions.

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
Stores discrete channel dispatches (WhatsApp, Email) spawned from an outbox event.

```sql
CREATE TABLE message_deliveries (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    outbox_event_id BIGINT UNSIGNED NOT NULL,
    booking_id BIGINT UNSIGNED NULL,
    channel VARCHAR(32) NOT NULL, -- whatsapp, email
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
    max_attempts INT UNSIGNED NOT NULL DEFAULT 5,
    last_attempted_at TIMESTAMP NULL,
    sent_at TIMESTAMP NULL,
    delivered_at TIMESTAMP NULL,
    failed_at TIMESTAMP NULL,
    last_error_code VARCHAR(64) NULL,
    last_error_message TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    CONSTRAINT fk_message_deliveries_outbox_event FOREIGN KEY (outbox_event_id) REFERENCES outbox_events (id) ON DELETE CASCADE,
    CONSTRAINT fk_message_deliveries_booking FOREIGN KEY (booking_id) REFERENCES bookings (id) ON DELETE SET NULL,
    INDEX idx_deliveries_status_attempts (status, attempts),
    INDEX idx_deliveries_status_attempted (status, last_attempted_at),
    INDEX idx_deliveries_booking_channel (booking_id, channel),
    INDEX idx_deliveries_provider_message (provider, provider_message_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 3. Domain Services & Outbox Integration

### 3.1 `OutboxService` Interface & Class
Located at `app/Domain/Messaging/Services/OutboxService.php`:

```php
namespace App\Domain\Messaging\Services;

use App\Domain\Messaging\Enums\OutboxEventStatus;
use App\Jobs\ProcessOutboxEventJob;
use App\Models\OutboxEvent;
use Illuminate\Database\Eloquent\Model;

class OutboxService
{
    public function record(
        string $eventType,
        Model $aggregate,
        array $payload,
        ?string $idempotencyKey = null,
        int $payloadVersion = 1,
    ): OutboxEvent {
        $key = $idempotencyKey ?? sprintf(
            'evt_%s_%s_%s_%s',
            strtolower(class_basename($aggregate)),
            $aggregate->getKey(),
            str_replace('.', '_', $eventType),
            $payloadVersion
        );

        $event = OutboxEvent::create([
            'event_type' => $eventType,
            'aggregate_type' => $aggregate->getMorphClass(),
            'aggregate_id' => $aggregate->getKey(),
            'idempotency_key' => $key,
            'payload_version' => $payloadVersion,
            'payload' => $payload,
            'status' => OutboxEventStatus::Pending,
            'attempts' => 0,
        ]);

        ProcessOutboxEventJob::dispatch($event->id)->afterCommit();

        return $event;
    }
}
```

### 3.2 Domain Event Instrumentation Points
1. **`BookingSubmissionService`** (`submitAirportPickup`, `submitAirportTransfer`, `submitRide`, `submitRent`):
   - At step 10 (inside `DB::transaction`), calls `OutboxService::record()` with `event_type: 'booking.submitted'`.
   - Injects the plain status URL (`route('booking.status', ['reference' => $booking->reference, 'token' => $plainGuestToken])`) into the payload so the worker can format customer links without reversing the token hash.
2. **`BookingLifecycleService`** (`confirm`, `decline`, `cancel`):
   - Inside `DB::transaction`, calls `OutboxService::record()` with `booking.confirmed`, `booking.declined`, `booking.cancelled`.
   - Injects reference, customer details, reason/notes, and support contacts.
3. **`ManualBookingService`** (`createManualAirportPickup`, `createManualAirportTransfer`, `createManualRide`, `createManualRent`):
   - Inside `DB::transaction`, calls `OutboxService::record()` with `booking.confirmed` so manually created confirmed reservations trigger customer notification.

---

## 4. Polymorphic Payload Data Mapping Across the 4 Verticals

To standardize notification parameters across the four verticals, the payload mapping follows this convention:

| Field | Airport Pickup | Airport Transfer | Ride | Rent (Self-Drive) |
|---|---|---|---|---|
| `service_title` | Airport Pickup | Airport Transfer | Chauffeured Ride | Self-Drive Vehicle Hire |
| `service_start_colombo` | `$detail->arrival_time` | `$detail->chosen_pickup_time` | `$detail->pickup_time` | `$detail->pickup_datetime` |
| `origin_text` | Bandaranaike International Airport (CMB) | `$detail->origin_text` | `$detail->origin_text` | `$detail->pickup_location_text` |
| `destination_text`| `$detail->destination_text` | Bandaranaike International Airport (CMB) | `$detail->destination_text` | `$detail->return_location_text` |
| `vehicle_display`| `$booking->vehicle->public_name` | `$booking->vehicle->public_name` | `$booking->vehicle->public_name` | `$booking->vehicle->public_name` |

---

## 5. Provider Contracts & DTOs

### 5.1 Enums
* `MessageChannel`: `WhatsApp = 'whatsapp'`, `Email = 'email'`
* `RecipientType`: `Booker = 'booker'`, `LeadTraveler = 'lead_traveler'`, `Staff = 'staff'`
* `OutboxEventStatus`: `Pending = 'pending'`, `Processing = 'processing'`, `Processed = 'processed'`, `Failed = 'failed'`
* `MessageDeliveryStatus`: `Pending = 'pending'`, `Sending = 'sending'`, `Sent = 'sent'`, `Delivered = 'delivered'`, `Failed = 'failed'`, `DeliveryUnknown = 'delivery_unknown'`
* `DeliveryResultStatus`: `Accepted = 'accepted'`, `Failed = 'failed'`, `Unknown = 'unknown'`

### 5.2 Contracts
```php
namespace App\Domain\Messaging\Contracts;

use App\Domain\Messaging\DTOs\DeliveryResult;
use App\Domain\Messaging\DTOs\EmailMessage;
use App\Domain\Messaging\DTOs\WhatsAppMessage;

interface WhatsAppSender
{
    public function send(WhatsAppMessage $message): DeliveryResult;
}

interface TransactionalMailer
{
    public function send(EmailMessage $message): DeliveryResult;
}
```

### 5.3 Canonical Templates & Meta Positional Mapping
* `booking_submitted_v1` (`en_US`): `{{1}}` Customer Name, `{{2}}` Reference, `{{3}}` Service Title, `{{4}}` Start Time Colombo, `{{5}}` Status URL
* `booking_confirmed_v1` (`en_US`): `{{1}}` Customer Name, `{{2}}` Reference, `{{3}}` Vehicle Display, `{{4}}` Start Time Colombo, `{{5}}` Status URL
* `booking_declined_v1` (`en_US`): `{{1}}` Customer Name, `{{2}}` Reference, `{{3}}` Support Contact
* `booking_cancelled_v1` (`en_US`): `{{1}}` Customer Name, `{{2}}` Reference, `{{3}}` Support Contact

---

## 6. Provider Adapters

1. **`FakeWhatsAppSender` & `FakeTransactionalMailer`**:
   - In-memory sent message tracking (`$sentMessages`).
   - Configurable response drivers: `alwaysSucceed()`, `alwaysFail(code, msg, retryable)`, `alwaysTimeout()`, `sequence(...)`.
   - Used in all automated Pest feature tests.
2. **`MetaWhatsAppSender`**:
   - Calls Meta WhatsApp Cloud API v21.0: `POST https://graph.facebook.com/v21.0/{phone_number_id}/messages`.
   - Normalizes phone numbers strictly to E.164 digits without symbols (e.g. `+94 77 123 4567` $\to$ `94771234567`).
   - Catches all transport exceptions; converts connect timeouts to `failed(retryable)`, and read timeouts to `unknown(non-retryable)`.
   - Disabled when `services.meta_whatsapp.system_user_token` or `phone_number_id` is missing.
3. **`PostmarkTransactionalMailer`**:
   - Calls Postmark API: `POST https://api.postmarkapp.com/email/withTemplate`.
   - Disabled when `services.postmark.server_token` is missing.

---

## 7. Queue Jobs & Dispatcher Lifecycle

1. **`ProcessOutboxEventJob`**:
   - Reads `OutboxEvent`.
   - Inspects aggregate booking and associated contacts.
   - For Booker:
     - If `$contact->is_whatsapp` and phone is present: creates `MessageDelivery` for `WhatsApp`.
     - If `$contact->email` is present: creates `MessageDelivery` for `Email`.
   - For Internal Staff Alert:
     - Creates `MessageDelivery` for `Email` with `recipient_type = 'staff'`, sent to `config('mail.operations_alert_address')`.
   - Uses `firstOrCreate` on `idempotency_key` to avoid duplicate insertion on retry.
   - Dispatches `SendMessageDeliveryJob` for each created delivery.
   - Updates `outbox_events.status = 'processed'`.
2. **`SendMessageDeliveryJob`**:
   - Properties: `$tries = 5`, `$timeout = 25`, `$backoff = [30, 120, 600]`.
   - Uses atomic row update (`status = 'sending'`, `last_attempted_at = now()`) inside a transaction to prevent concurrent worker execution.
   - Calls sender adapter wrapped in safety try/catch.
   - On `DeliveryResultStatus::Accepted`: updates `status = 'sent'`, `sent_at = now()`, `provider_message_id`.
   - On `DeliveryResultStatus::Failed`:
     - If `result->isRetryable` and `$delivery->attempts < $delivery->max_attempts`: releases job with backoff.
     - Else: updates `status = 'failed'`, `failed_at = now()`, `last_error_code`, `last_error_message`.
   - On `DeliveryResultStatus::Unknown`:
     - Updates `status = 'delivery_unknown'`, `last_error_code = 'DELIVERY_UNKNOWN'`.
     - **DOES NOT AUTO-RETRY**.
   - Implements `failed(?Throwable $exception)` lifecycle hook to guarantee `status = 'failed'` if Laravel kills the job.

---

## 8. cPanel Background Queue Drain & Scheduler

1. **Artisan Command: `app/Console/Commands/DrainOutboxQueueCommand.php` (`outbox:drain`)**:
   - Acquires atomic cache lock: `Cache::lock('spinellanka_outbox_drain', 90)->get()`.
   - **Phase 1 (Outbox Poller/Sweeper)**:
     ```php
     OutboxEvent::query()
         ->where('status', OutboxEventStatus::Pending)
         ->where('created_at', '<=', now()->subSeconds(15))
         ->where('attempts', '<', 5)
         ->limit(50)
         ->get()
         ->each(function (OutboxEvent $event) {
             $event->increment('attempts');
             ProcessOutboxEventJob::dispatch($event->id);
         });
     ```
   - **Phase 2 (Queue Worker)**:
     ```php
     Artisan::call('queue:work', [
         '--queue' => 'notifications,default',
         '--stop-when-empty' => true,
         '--max-time' => 35,
         '--max-jobs' => 25,
         '--tries' => 3,
     ]);
     ```
   - Releases lock on completion.
2. **Scheduler Registration in `routes/console.php`**:
   ```php
   Schedule::command('outbox:drain')
       ->everyMinute()
       ->withoutOverlapping(expiresAt: 2)
       ->runInBackground();
   ```

---

## 9. Filament Staff Visibility & Operations

1. **Relation Manager**: `app/Filament/Resources/Operations/Bookings/RelationManagers/MessageDeliveriesRelationManager.php`
   - Mounted in `BookingResource::getRelations()`.
   - Columns:
     - Channel badge (`whatsapp` [green], `email` [blue]).
     - Recipient: masked using `$record->maskedRecipient()` (`+94 77 ••••• 89`, `j•••••@example.com`).
     - Template Name & Version.
     - Status badge: `sent` [teal], `pending` [yellow], `sending` [blue], `delivery_unknown` [amber], `failed` [red].
     - Attempts count (`attempts` / `max_attempts`).
     - Provider Message ID.
     - Error Code / Message (redacted of secrets and raw PII).
     - Timestamps (`created_at`, `sent_at`, `failed_at`).
2. **Staff Manual Retry Action**:
   - Visible on rows where `status IN ('failed', 'delivery_unknown')`.
   - Authorized only for users with `Permission::BookingReview->value` or `Permission::BookingConfirm->value`.
   - Modal warning: *"Warning: If the customer already received this notification, manual re-sending may produce a duplicate message. Confirm only if delivery failure is confirmed."*
   - Records an `AuditEvent`: `message_delivery.manually_retried`.
   - Updates `status = 'pending'`, increments `max_attempts`, and dispatches `SendMessageDeliveryJob`.

