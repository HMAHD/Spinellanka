# EGM Phase 14 Design Draft: Operations Calendar & Driver Trip Access

## 1. Schema & Data Model

### A. New Table: `driver_assignments`
Stores the active and historical driver assignments for bookings.
```sql
CREATE TABLE driver_assignments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id BIGINT UNSIGNED NOT NULL,
    driver_id BIGINT UNSIGNED NOT NULL,
    vehicle_id BIGINT UNSIGNED NOT NULL,
    is_replacement BOOLEAN NOT NULL DEFAULT FALSE,
    replacement_reason TEXT NULL,
    replacement_authorized_by_user_id BIGINT UNSIGNED NULL,
    assigned_by_user_id BIGINT UNSIGNED NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'assigned', -- assigned, acknowledged, en_route, arrived, started, completed, issue_reported
    issue_reported_notes TEXT NULL,
    assigned_at DATETIME NOT NULL,
    acknowledged_at DATETIME NULL,
    en_route_at DATETIME NULL,
    arrived_at DATETIME NULL,
    started_at DATETIME NULL,
    completed_at DATETIME NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE RESTRICT,
    FOREIGN KEY (driver_id) REFERENCES drivers(id) ON DELETE RESTRICT,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE RESTRICT,
    FOREIGN KEY (replacement_authorized_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_driver_assign_booking (booking_id),
    INDEX idx_driver_assign_driver (driver_id, status)
);
```

### B. New Table: `driver_access_tokens`
Stores SHA-256 hashed trip tokens for drivers.
```sql
CREATE TABLE driver_access_tokens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    driver_assignment_id BIGINT UNSIGNED NOT NULL,
    booking_id BIGINT UNSIGNED NOT NULL,
    driver_id BIGINT UNSIGNED NOT NULL,
    token_hash VARCHAR(64) NOT NULL UNIQUE,
    purpose VARCHAR(32) NOT NULL DEFAULT 'driver_trip_access',
    expires_at DATETIME NOT NULL,
    revoked_at DATETIME NULL,
    revoked_reason VARCHAR(64) NULL, -- reassigned, driver_withdrawn, compromised, manual
    revoked_by_user_id BIGINT UNSIGNED NULL,
    last_used_at DATETIME NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (driver_assignment_id) REFERENCES driver_assignments(id) ON DELETE CASCADE,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE RESTRICT,
    FOREIGN KEY (driver_id) REFERENCES drivers(id) ON DELETE RESTRICT,
    FOREIGN KEY (revoked_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_driver_tokens_lookup (token_hash, revoked_at, expires_at)
);
```

### C. New Table: `driver_trip_updates`
Append-only log of driver milestone updates.
```sql
CREATE TABLE driver_trip_updates (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    driver_assignment_id BIGINT UNSIGNED NOT NULL,
    booking_id BIGINT UNSIGNED NOT NULL,
    driver_id BIGINT UNSIGNED NOT NULL,
    from_status VARCHAR(32) NOT NULL,
    to_status VARCHAR(32) NOT NULL,
    notes TEXT NULL,
    device_metadata JSON NULL,
    recorded_at DATETIME NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (driver_assignment_id) REFERENCES driver_assignments(id) ON DELETE CASCADE,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE RESTRICT,
    FOREIGN KEY (driver_id) REFERENCES drivers(id) ON DELETE RESTRICT,
    INDEX idx_trip_updates_assignment (driver_assignment_id, recorded_at)
);
```

### D. Table Alteration: `bookings`
Add foreign keys to identify current assigned driver and assignment.
```sql
ALTER TABLE bookings
    ADD COLUMN assigned_driver_id BIGINT UNSIGNED NULL AFTER selected_vehicle_id,
    ADD COLUMN current_driver_assignment_id BIGINT UNSIGNED NULL AFTER assigned_driver_id,
    ADD CONSTRAINT fk_bookings_assigned_driver FOREIGN KEY (assigned_driver_id) REFERENCES drivers(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_bookings_driver_assignment FOREIGN KEY (current_driver_assignment_id) REFERENCES driver_assignments(id) ON DELETE SET NULL;
```

---

## 2. Enums & Value Objects

- `App\Domain\Operations\Enums\DriverTripStatus`:
  - `Assigned = 'assigned'`
  - `Acknowledged = 'acknowledged'`
  - `EnRoute = 'en_route'`
  - `Arrived = 'arrived'`
  - `Started = 'started'`
  - `Completed = 'completed'`
  - `IssueReported = 'issue_reported'`
  - Helper methods: `label()`, `icon()`, `isTerminal()`, `allowedNextStates()`.
- `App\Domain\Operations\Enums\OperationalAlertSeverity`:
  - `Critical = 'critical'`
  - `Warning = 'warning'`
  - `Info = 'info'`
- `App\Domain\Operations\DTOs\CalendarItemDTO`:
  - Properties: id, vehicle_id, vehicle_name, vehicle_plate, kind, kind_label, kind_icon, status, status_label, starts_at, ends_at, effective_start, effective_end, booking_reference, service_type, service_label, driver_id, driver_name, is_replacement, has_conflict.
- `App\Domain\Operations\DTOs\OperationalAlertDTO`:
  - Properties: id, type, severity, title, message, action_url, action_label, booking_id, vehicle_id.

---

## 3. Core Domain Services

### A. `OperationsScheduleProjectionService`
- Queries `VehicleScheduleBlock` for a given interval in `Asia/Colombo`.
- Eager-loads `vehicle`, `driver`, and polymorphic `source` (`Booking` with service details and contacts).
- Projects into:
  - `projectMonth(Carbon $monthStart): array`
  - `projectWeek(Carbon $weekStart): array`
  - `projectDay(Carbon $day): array`
  - `projectAgenda(Carbon $startDate, int $days = 7): array`
- Implements WCAG 2.2 non-color discrimination: Every block includes text status, badge icon, and distinct styling.

### B. `OperationsAlertService`
- Computes real-time active alerts:
  1. `getConflictAlerts()`: finds any active schedule blocks overlapping on the same vehicle.
  2. `getDeadlineAlerts()`: checkout holds expiring in < 5 mins, proposal deadlines expiring in < 2 hours.
  3. `getStaleRequestAlerts()`: bookings in `submitted` status for > 24 hours without review.
  4. `getUnassignedTripAlerts()`: confirmed chauffeured bookings starting within 48 hours without an assigned driver.
  5. `getPaymentFrictionAlerts()`: confirmed bookings with pending balance starting within 24 hours.
  6. `getFailedDeliveryAlerts()`: outbox messages with status `failed`.

### C. `DriverAssignmentService`
- `resolveTiedDriver(Booking $booking): ?Driver`: Checks `VehicleDriverAssignment` for the booking's vehicle and pickup date.
- `assignDriver(Booking $booking, Driver $driver, User $staff, ?string $replacementReason = null): DriverAssignment`:
  - Validates booking is chauffeured (Airport Pickup, Airport Transfer, Ride).
  - Determines if assignment is replacement: `$isReplacement = ($driver->id !== $tiedDriver?->id)`.
  - If replacement: requires `$replacementReason`, asserts staff has `booking.review` or `booking.confirm`.
  - Revokes any existing active driver token for this booking.
  - Creates new `DriverAssignment` and updates `Booking` record.
  - Updates `VehicleScheduleBlock` linked to booking to set `driver_id = $driver->id`.
  - Issues new `DriverAccessToken`.
  - Records audit event `booking.driver_assigned`.
  - Emits transactional outbox event `driver.assigned`.
- `revokeAssignment(Booking $booking, User $staff, string $reason): void`.

### D. `DriverTripService`
- `validateToken(string $plainToken): ?DriverAccessToken`:
  - Hashes token using SHA-256.
  - Checks expiration and revocation.
  - Updates `last_used_at`.
- `updateTripStatus(DriverAccessToken $token, DriverTripStatus $newStatus, ?string $notes = null, ?array $deviceMetadata = null): DriverAssignment`:
  - Enforces atomic row locking on `DriverAssignment` and `Booking`.
  - Validates that `$newStatus` is an allowed transition from current status.
  - Appends to `driver_trip_updates`.
  - Updates milestone timestamp.
  - If `$newStatus === DriverTripStatus::Completed`: calls `BookingLifecycleService::complete($booking)`.
  - Records audit and outbox event `driver.trip_updated`.

---

## 4. UI & Presentation Layer

### A. Driver Mobile Trip Page (`/driver/trip/{token}`)
- Minimal, distraction-free mobile web UI (optimized for 320px–390px mobile screens).
- Displays ONLY permitted operational fields:
  - Header: Booking reference, service type badge, scheduled pickup time (Asia/Colombo).
  - Journey Card: Pickup location (with map navigation link), destination/stops, passenger count, luggage count.
  - Meeting / Flight Context: Flight number, arrival time, meeting point note.
  - Traveler Contact: Lead traveler first name, one-click WhatsApp/Call button.
  - Actions Area: Action button matching current allowed transition (e.g. "Acknowledge Trip", "On the Way", "Arrived at Pickup", "Start Trip", "Complete Trip").
  - Issue Reporting modal: "Report an Issue / Delay".
- **Zero Financial/Internal Leakage Guarantee**: No prices, currencies, payment status, customer address, internal staff notes, or other bookings.
- **Offline / Network Resilience**: Optimistic client-side pending state and retry indicator.
- **Privacy Shell**: Invalid, expired, or revoked tokens render a neutral error page with `X-Robots-Tag: noindex, nofollow` and `Referrer-Policy: no-referrer`.

### B. Filament Operations Calendar Page
- Custom Filament Page: `App\Filament\Pages\OperationsCalendar`.
- Navigation: Operations group, icon `heroicon-o-calendar-days`.
- Permissions: `Permission::BookingView` / `Permission::FleetView`.
- Multi-viewport tabs: Month, Week, Day, and Mobile Agenda view.
- Vehicle Filter: All vehicles or filter by individual vehicle.
- Operational Alert Header Banner: Summarizes active alerts with direct link to affected booking.

### C. Filament Booking Driver Assignment Action
- In `BookingResource`:
  - Table column showing Assigned Driver & Trip Status badge.
  - Modal action `assignDriver`:
    - Displays recommended permanent tied driver.
    - Select driver (tied or replacement).
    - If replacement: reveals required `replacement_reason` textarea.
    - Once assigned: displays formatted trip link with "Copy Trip Link" button.
    - Actions to "Rotate Trip Link" or "Revoke Driver Access".

