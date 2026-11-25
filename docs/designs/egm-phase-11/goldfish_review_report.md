# Phase 11 Goldfish Review Report: Outbox & Notifications

* **Date:** 2026-11-25
* **Phase:** Phase 11 (`phase/11-outbox-notifications`)
* **Reviewers:**
  1. Hostile Design Critic (`87397950-f328-4f9e-a129-7c6d4eae3189`)
  2. Pragmatic Tech Lead (`c83a6d0b-82b5-48bd-8774-2ebc6f4291cf`)
  3. Technical Quality Auditor (`865d8914-3c27-40f8-b261-08f1993533bb`)

---

## 1. Executive Summary & Consensus

All three subagents completed independent deep audits across system architecture, implementation feasibility, and code compatibility. The reviewers unanimously agreed on the critical architectural strengths of Phase 11:
- True transactional atomicity by committing outbox records in the domain transaction;
- Strict isolation of booking state from external provider delivery failures;
- Strict data minimization and secret isolation;
- Safe handling of ambiguous provider timeouts via `delivery_unknown`.

The reviewers also uncovered **5 unanimous Blockers** and **6 Major architectural hazards** that require immediate correction in the resolved design before coding starts.

---

## 2. Synthesized Blockers & Resolutions

### Blocker 1: The Outbox Enqueue Gap & Sweeper Requirement
* **Problem:** `outbox:drain` initially only invoked `queue:work`. If a web process dies after `DB::commit()` but before or during `after_commit` queue dispatch, the `outbox_events` row remains in `status = 'pending'` forever because nothing polls `outbox_events`.
* **Resolution:** `DrainOutboxQueueCommand` implements a two-phase execution:
  1. *Outbox Poller/Sweeper*: Queries `outbox_events` in `pending` created $\ge 15$ seconds ago and dispatches `ProcessOutboxEventJob`.
  2. *Queue Worker*: Executes `queue:work` with `--stop-when-empty --max-time=35 --max-jobs=25`.

### Blocker 2: Meta WhatsApp Non-Idempotency Reality
* **Problem:** Meta WhatsApp Cloud API does not accept client-provided idempotency keys in payload/headers, and provides no REST query to verify if an unacknowledged message was received. Assuming provider idempotency is an architectural fallacy.
* **Resolution:**
  - Formally document Meta's non-idempotent dispatch.
  - Implement atomic row claiming: transition `status = 'sending'`, `last_attempted_at = now()` inside a micro-transaction before calling the HTTP client.
  - On timeout or ambiguous response, transition to `status = 'delivery_unknown'`.
  - Strictly forbid automatic retries of `delivery_unknown` sends.
  - Staff manual retry in Filament requires explicit confirmation warning about duplicate message risk, creates an append-only audit record (`message_delivery.retried`), and increments attempts.

### Blocker 3: cPanel Cron Overlap & 24-Hour Mutex Deadlock
* **Problem:** Lock TTL of 55s with worker runtime of 50s causes worker overlap if an HTTP call takes 10s at $t=48\text{s}$. Furthermore, `withoutOverlapping()` defaults to 24-hour expiration if a cPanel process is killed.
* **Resolution:**
  - Lock TTL: 90 seconds.
  - Worker `--max-time`: 35 seconds (25-second buffer before next minute cron).
  - Worker `--max-jobs`: 25.
  - HTTP timeout: Connect 3s, Read 8s (max 8s).
  - Scheduler: `withoutOverlapping(expiresAt: 2)->runInBackground()`.

### Blocker 4: Non-Existent Permission Reference `Permission::BookingUpdate`
* **Problem:** `Permission::BookingUpdate` does not exist in `app/Domain/Identity/Permission.php`. Calling it crashes at runtime.
* **Resolution:** Use canonical permissions: `Permission::BookingReview->value` and `Permission::BookingConfirm->value`.

### Blocker 5: `GuestAccessToken` Hash Irreversibility & Status URL Capture
* **Problem:** `guest_access_tokens` stores only the SHA-256 hash. The plain token cannot be retrieved asynchronously in a queue job.
* **Resolution:** In `BookingSubmissionService`, pass the plain token or full `$statusUrl` in the outbox payload before transaction commit.

---

## 3. Synthesized Major Issues & Resolutions

### Major 1: Missed Lifecycle Hook in `ManualBookingService`
* **Resolution:** Instrument all 4 methods of `ManualBookingService` (`createManualAirportPickup`, `createManualAirportTransfer`, `createManualRide`, `createManualRent`) to record `booking.confirmed` outbox events.

### Major 2: Status Nomenclature: `sent` vs `delivered`
* **Resolution:** Provider HTTP 200 confirms gateway acceptance (`sent`), not handset receipt (`delivered`). Status transitions to `sent` with `sent_at = now()`. Webhook receipts in Phase 13 will handle `delivered`.

### Major 3: Channel Scope & Phone Number Sanitization
* **Resolution:** Channels are strictly `whatsapp` and `email`. Phone numbers must be normalized to E.164 digits without symbols before dispatch. If `BookingContact.is_whatsapp == false`, suppress WhatsApp dispatch and fallback to email.

### Major 4: Queue Job Lifecycle Hooks & Exception Catching
* **Resolution:**
  - Set `$tries = 5`, `$timeout = 25`, `$backoff = [30, 120, 600]`.
  - Implement `failed(?Throwable $exception)` on `SendMessageDeliveryJob` to update `status = 'failed'` and record `last_error_message`.
  - Adapters must catch transport exceptions internally and return `DeliveryResult::unknown(...)` or `DeliveryResult::failed(...)`.

### Major 5: PII and Error Message Redaction
* **Resolution:** Sanitize error messages before saving to database and logging. Mask recipient phone and email in Filament tables and logs.

### Major 6: Foreign Key on `booking_id`
* **Resolution:** Change foreign key constraint on `message_deliveries.booking_id` to `ON DELETE SET NULL` to preserve legal delivery audit records.

---

## 4. Final Verdict & Gate
**Approved with Required Resolutions**: All findings have been integrated into the final resolved architectural design: `docs/designs/phase-11-outbox-notifications.md`.

