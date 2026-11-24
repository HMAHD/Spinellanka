# Spinellanka Phase 07: Airport Pickup Core Architecture & Implementation Design

## 1. Executive Summary & Domain Scope

Phase 07 implements the first production booking vertical of the Spinellanka platform: **Airport Pickup Core**. It seamlessly unifies the Catalog (exact vehicle fleet), Concurrency & Availability (exact vehicle locking and 15-minute checkout holds), Pricing & Estimates (effective-dated rule engine and immutable itemized estimates), and Identity & Audit (role-based staff review and append-only audit trail).

### Key Business & Technical Invariants:
1. **Advance Reservation Only**: Every airport pickup reservation is submitted as **Pending Under Review**. Traveler submission is **NEVER** shown or recorded as confirmed.
2. **Exact Vehicle Allocation**: The traveler reserves the exact vehicle unit (make, model, year, license plate).
3. **Atomic Hold-to-Request Conversion**: Transitions an active 15-minute checkout hold into a `SubmittedRequest` block within a single database transaction under vehicle row lock, eliminating race conditions.
4. **Permanent Driver Chauffeured Verification**: Because Airport Pickup is a chauffeured service (`ServiceType::AirportPickup->includesDriver() === true`), a vehicle is eligible only if it has an active, assigned effective driver who is free of driver-unavailability schedule blocks.
5. **Private Guest Access Token**: Secured by a 256-bit CSPRNG token (`bin2hex(random_bytes(32))`) whose SHA-256 hash alone is persisted. Invalid, expired, or revoked tokens return an identical generic HTTP 404 without leaking booking existence.
6. **Separation of Contacts & Consent**: Booker (who arranges/pays) and Lead Traveler (who lands at CMB) are stored as distinct relational records. Mandatory policy acceptance (Terms and Privacy) is strictly separated from optional, unbundled, unchecked marketing consent.
7. **Staff Manual Confirmation**: Authorized staff possessing `Permission::BookingConfirm` confirm requests only after direct customer communication (WhatsApp/phone/email) and automated availability re-check under vehicle row lock.

---

## 2. System Architecture & Component Interaction

```
[ International Traveler (Browser) ]
                 │
                 ▼
[ AirportPickupBookingController ]
  ├── 1. POST /search ────► [ AirportPickupSuitabilityService ]
  │                           ├── Vehicle Capacity & Active Status Check
  │                           ├── DriverAssignmentService (Effective Driver Check)
  │                           ├── AvailabilityService::checkAvailability()
  │                           └── PricingService::calculateEstimate()
  │
  ├── 2. POST /hold ──────► [ AvailabilityService::createHold() ] (15-min TTL, Vehicle Lock)
  │                           └── [ EstimateService::snapshotEstimate() ] (24-hr TTL)
  │
  └── 3. POST /submit ────► [ BookingSubmissionService::submitAirportPickup() ]
                              ├── DB::transaction() + Vehicle Row Lock
                              ├── AvailabilityService::convertHoldToSubmittedBlock()
                              ├── Creates Booking (Status: Submitted, Reference: BKG-XXXXXXXX)
                              ├── Creates BookingContact (Booker & Lead Traveler)
                              ├── Creates AirportPickupDetail (Flight & Destination)
                              ├── Creates BookingPolicyAcceptance (Terms & Privacy Hashes)
                              ├── Issues 256-bit GuestAccessToken (SHA-256 Hash Stored)
                              └── Emits AuditEvent ('booking.submitted')
                                            │
                                            ▼
                    Redirects to: /booking/{reference}/{plainToken}
                                            │
                                            ▼
[ BookingStatusController::show() ] ──► "Request Received — Under Review"
  ├── Headers: X-Robots-Tag: noindex, Referrer-Policy: no-referrer
  └── Generic 404 on Token Failure (Anti-Enumeration)
```

---

## 3. Database Schema (5 Tables)

### 3.1 `bookings`
| Column | Type | Constraints / Description |
|---|---|---|
| `id` | BigIncrements | Primary Key |
| `reference` | String(16) | Unique format `BKG-XXXXXXXX` |
| `service_type` | String(32) | Enum `ServiceType::AirportPickup` (`airport_pickup`) |
| `status` | String(32) | Enum `BookingStatus` (`submitted`, `under_review`, `confirmed`, `declined`, `cancelled`, `expired`), indexed |
| `vehicle_id` | ForeignId | References `vehicles(id)`, `restrictOnDelete` |
| `estimate_id` | ForeignId | References `estimates(id)`, nullable, `nullOnDelete` |
| `schedule_block_id` | ForeignId | References `vehicle_schedule_blocks(id)`, nullable, `nullOnDelete` |
| `source` | String(32) | Enum `BookingSource` (`web`, `manual_phone`, `manual_whatsapp`, `manual_walkin`) |
| `assigned_staff_id` | ForeignId | References `users(id)`, nullable, `nullOnDelete` |
| `currency` | String(3) | Default `'USD'` |
| `total_amount_minor_units` | BigInteger | Total snapshot minor units (e.g. 6500 = $65.00) |
| `approximate_lkr_amount_minor_units` | BigInteger | Approximate LKR snapshot minor units |
| `request_hash` | String(64) | SHA-256 hash of submission payload (Unchanged guard) |
| `confirmation_channel` | String(32) | Enum `ConfirmationChannel` (`whatsapp`, `phone`, `email`), nullable |
| `confirmation_notes` | Text | Staff customer communication outcome notes, nullable |
| `cancellation_reason` | Text | Cancellation justification, nullable |
| `decline_reason` | Text | Staff decline justification, nullable |
| `confirmed_at` | Timestamp | Nullable |
| `cancelled_at` | Timestamp | Nullable |
| `declined_at` | Timestamp | Nullable |
| `expires_at` | Timestamp | Pending SLA review expiry (default 24h), indexed |
| `timestamps` | Timestamps | `created_at`, `updated_at` |

### 3.2 `booking_contacts`
| Column | Type | Constraints / Description |
|---|---|---|
| `id` | BigIncrements | Primary Key |
| `booking_id` | ForeignId | References `bookings(id)`, `cascadeOnDelete` |
| `type` | String(32) | Enum `ContactType` (`booker`, `lead_traveler`) |
| `first_name` | String(128) | Contact given name |
| `last_name` | String(128) | Contact family name |
| `phone` | String(32) | International format E.164 (e.g. `+447700900123`) |
| `is_whatsapp` | Boolean | Default `true` |
| `email` | String(255) | Nullable |
| `marketing_consent` | Boolean | Default `false` (Unbundled, unchecked, only on Booker) |
| `timestamps` | Timestamps | `created_at`, `updated_at` |
| **Unique** | Index | `['booking_id', 'type']` |

### 3.3 `airport_pickup_details`
| Column | Type | Constraints / Description |
|---|---|---|
| `id` | BigIncrements | Primary Key |
| `booking_id` | ForeignId | Unique, references `bookings(id)`, `cascadeOnDelete` |
| `airport` | String(16) | Airport IATA code, default `'CMB'` |
| `flight_number` | String(32) | Incoming flight number (e.g. `UL504`, `EK650`) |
| `arrival_time` | DateTime | Scheduled touchdown timestamp in `Asia/Colombo` |
| `destination_text` | String(500) | Exact preserved traveler destination text |
| `destination_place_id` | String(255) | Nullable place search ID |
| `destination_lat` | Decimal(10, 7)| Nullable coordinate |
| `destination_lng` | Decimal(10, 7)| Nullable coordinate |
| `destination_notes` | Text | Landmark and arrival notes, nullable |
| `passenger_count` | SmallInteger | Party size (1-10) |
| `luggage_count` | SmallInteger | Luggage bags (0-10) |
| `meet_and_greet` | Boolean | Default `true` |
| `name_board_text` | String(255) | Welcome board text |
| `waiting_instructions` | Text | Instructions for customs delays |

### 3.4 `booking_policy_acceptances`
| Column | Type | Constraints / Description |
|---|---|---|
| `id` | BigIncrements | Primary Key |
| `booking_id` | ForeignId | References `bookings(id)`, `cascadeOnDelete` |
| `policy_type` | String(64) | Policy type (`terms_of_service`, `privacy_policy`) |
| `version` | String(32) | Policy document version (e.g. `'2026-11-23'`) |
| `text_hash` | String(64) | SHA-256 hash of policy document text |
| `accepted_at` | Timestamp | Timestamp of acceptance in `Asia/Colombo` |
| `ip_address` | String(45) | Client IP, nullable |
| `user_agent` | Text | Client User-Agent, nullable |
| **Unique** | Index | `['booking_id', 'policy_type', 'version']` |

### 3.5 `guest_access_tokens`
| Column | Type | Constraints / Description |
|---|---|---|
| `id` | BigIncrements | Primary Key |
| `booking_id` | ForeignId | References `bookings(id)`, `cascadeOnDelete` |
| `token_hash` | String(64) | SHA-256 hash of 256-bit plain token, unique |
| `purpose` | String(32) | Default `'guest_access'` |
| `expires_at` | Timestamp | Default 30 days |
| `revoked_at` | Timestamp | Nullable |
| `last_used_at` | Timestamp | Nullable |

---

## 4. Availability & Concurrency Protocol

### 4.1 Atomic Hold Conversion
`AvailabilityService::convertHoldToSubmittedBlock()`:
1. Locates `BookingHold` by `BookingHold::hashToken($plainToken)`.
2. Locks the exact vehicle row via `Vehicle::where('id', $hold->vehicle_id)->lockForUpdate()->firstOrFail()`.
3. Re-verifies under lock: `status === HoldStatus::Active` and `expires_at > now('Asia/Colombo')`. If stale, calls `expireStaleHoldsForVehicle()` and throws `ScheduleConflictException`.
4. Mutates existing `VehicleScheduleBlock` in-place:
   - `kind = ScheduleBlockKind::SubmittedRequest`
   - `source_type = Booking::class`
   - `source_id = $booking->id`
   - `blocks_availability = true`
   - `expires_at = $pendingExpiresAt`
5. Updates `BookingHold`: `status = HoldStatus::Converted`, `released_at = now()`.
6. Emits `availability.hold.converted_to_request` audit record.

### 4.2 Staff Confirmation Protocol
`BookingLifecycleService::confirm()`:
1. Validates actor possesses `Permission::BookingConfirm`.
2. Acquires vehicle row lock.
3. Unchanged Request Guard: Re-computes request payload hash and asserts `hash_equals($booking->request_hash, $currentHash)`.
4. Calls `AvailabilityService::confirmSubmittedBlock()`:
   - Rechecks schedule conflicts via `getConflictingBlocks()` ignoring `$booking->schedule_block_id`.
   - If conflicts exist, strictly enforces `Permission::BookingOverrideConflict` and non-empty `override_reason`.
   - Updates block `kind = ScheduleBlockKind::ConfirmedBooking`, `expires_at = null`.
5. Updates `Booking`: `status = Confirmed`, `confirmed_at = now()`, `confirmation_channel = $channel`, `confirmation_notes = $notes`.
6. Emits `booking.confirmed` audit event.

---

## 5. Security & Privacy Controls

1. **Token Entropy & Storage**:
   - 256 bits of CSPRNG generated via `bin2hex(random_bytes(32))`.
   - Never stored in plaintext; stored as `hash('sha256', $plainToken)` with unique index.
2. **Anti-Enumeration Protection**:
   - Any query with an invalid, expired, revoked, or non-matching reference returns an identical generic HTTP 404 response.
3. **HTTP Response Privacy Headers**:
   - `X-Robots-Tag: noindex, nofollow`
   - `Referrer-Policy: no-referrer`
   - `Cache-Control: no-store, private`
4. **Data Minimization**:
   - Private status page exposes only essential operational flight, vehicle, and traveler name details. PII like payment instruments or full identity documents are not stored or exposed.

---

## 6. Staff Filament 5 Operational Interface

Located under the `Operations` group in the admin panel:
- **Resource**: `App\Filament\Resources\Operations\Bookings\BookingResource`
- **Review Queue**: Filterable by status (`Submitted`, `UnderReview`, `Confirmed`, `Declined`, `Cancelled`).
- **Actions**:
  - `Review`: Sets status to `UnderReview` and attributes staff.
  - `Confirm`: Collects communication channel (WhatsApp/Phone/Email) and customer notes; rechecks schedule availability before confirming.
  - `Decline`: Collects decline reason and releases schedule block.
  - `Cancel`: Collects cancellation reason and releases schedule block.
  - `Manual Booking`: Header action enabling telephone/WhatsApp intake reservations.

