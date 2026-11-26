# Spinellanka Phase 14: Operations Calendar & Driver Trip Access
## Technical Design Specification (Final Resolved)

**Branch**: `phase/14-calendar-driver-operations`  
**Review Status**: Approved via EGM Goldfish Synthesis (`docs/designs/egm-phase-14/goldfish_review_report.md`)  
**Timezone Reference**: All operational projections and timestamps anchored in `Asia/Colombo`.

---

## 1. Domain Models & Database Schema

### A. Database Migrations (Sequenced to Prevent Circular Deadlocks)

#### Migration 1: Create Driver Operations Tables
`database/migrations/2026_04_01_000001_create_driver_operations_tables.php`
```php
Schema::create('driver_assignments', function (Blueprint $table) {
    $table->id();
    $table->foreignId('booking_id')->constrained('bookings')->restrictOnDelete();
    $table->foreignId('driver_id')->constrained('drivers')->restrictOnDelete();
    $table->foreignId('vehicle_id')->constrained('vehicles')->restrictOnDelete();
    $table->boolean('is_replacement')->default(false);
    $table->text('replacement_reason')->nullable();
    $table->foreignId('replacement_authorized_by_user_id')->nullable()->constrained('users')->nullOnDelete();
    $table->foreignId('assigned_by_user_id')->nullable()->constrained('users')->nullOnDelete();
    $table->string('status', 32)->default('assigned'); // assigned, acknowledged, en_route, arrived, started, completed, superseded, cancelled
    $table->text('issue_reported_notes')->nullable();
    $table->dateTime('issue_reported_at')->nullable();
    $table->dateTime('assigned_at');
    $table->dateTime('acknowledged_at')->nullable();
    $table->dateTime('en_route_at')->nullable();
    $table->dateTime('arrived_at')->nullable();
    $table->dateTime('started_at')->nullable();
    $table->dateTime('completed_at')->nullable();
    $table->timestamps();

    $table->index('booking_id', 'idx_driver_assign_booking');
    $table->index(['driver_id', 'status'], 'idx_driver_assign_driver');
});

Schema::create('driver_access_tokens', function (Blueprint $table) {
    $table->id();
    $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
    $table->foreignId('driver_assignment_id')->constrained('driver_assignments')->cascadeOnDelete();
    $table->foreignId('driver_id')->constrained('drivers')->restrictOnDelete();
    $table->string('token_hash', 64)->unique();
    $table->dateTime('expires_at');
    $table->dateTime('revoked_at')->nullable();
    $table->string('revocation_reason', 128)->nullable();
    $table->timestamps();

    $table->index(['booking_id', 'revoked_at'], 'idx_driver_tokens_booking_active');
    $table->index(['driver_assignment_id', 'revoked_at'], 'idx_driver_tokens_assignment_active');
    $table->index('expires_at', 'idx_driver_tokens_expires');
});

Schema::create('driver_trip_updates', function (Blueprint $table) {
    $table->id();
    $table->foreignId('driver_assignment_id')->constrained('driver_assignments')->cascadeOnDelete();
    $table->string('status', 32);
    $table->text('notes')->nullable();
    $table->string('client_device_timestamp', 64)->nullable();
    $table->dateTime('recorded_at');
    $table->timestamps();

    $table->index(['driver_assignment_id', 'status'], 'idx_driver_updates_assign');
});
```

#### Migration 2: Alter Bookings Table
`database/migrations/2026_04_01_000002_add_driver_assignment_columns_to_bookings_table.php`
```php
Schema::table('bookings', function (Blueprint $table) {
    $table->foreignId('assigned_driver_id')->nullable()->after('vehicle_id')->constrained('drivers')->nullOnDelete();
    $table->foreignId('current_driver_assignment_id')->nullable()->after('assigned_driver_id')->constrained('driver_assignments')->nullOnDelete();
    $table->dateTime('completed_at')->nullable()->after('declined_at');
});
```

---

## 2. Enums & Value Objects

### A. `DriverTripStatus`
`app/Domain/Operations/Enums/DriverTripStatus.php`
```php
namespace App\Domain\Operations\Enums;

enum DriverTripStatus: string
{
    case Assigned = 'assigned';
    case Acknowledged = 'acknowledged';
    case EnRoute = 'en_route';
    case Arrived = 'arrived';
    case Started = 'started';
    case Completed = 'completed';
    case Superseded = 'superseded';
    case Cancelled = 'cancelled';

    public function order(): int
    {
        return match ($this) {
            self::Assigned => 1,
            self::Acknowledged => 2,
            self::EnRoute => 3,
            self::Arrived => 4,
            self::Started => 5,
            self::Completed => 6,
            self::Superseded, self::Cancelled => 99,
        };
    }

    public function allowedNextStates(): array
    {
        return match ($this) {
            self::Assigned => [self::Acknowledged, self::Cancelled],
            self::Acknowledged => [self::EnRoute, self::Cancelled],
            self::EnRoute => [self::Arrived, self::Cancelled],
            self::Arrived => [self::Started, self::Cancelled],
            self::Started => [self::Completed],
            self::Completed, self::Superseded, self::Cancelled => [],
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Assigned => 'Assigned',
            self::Acknowledged => 'Acknowledged',
            self::EnRoute => 'En Route',
            self::Arrived => 'Arrived at Pickup',
            self::Started => 'Trip Started',
            self::Completed => 'Completed',
            self::Superseded => 'Superseded',
            self::Cancelled => 'Cancelled',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Assigned => 'heroicon-o-bell',
            self::Acknowledged => 'heroicon-o-hand-thumb-up',
            self::EnRoute => 'heroicon-o-truck',
            self::Arrived => 'heroicon-o-map-pin',
            self::Started => 'heroicon-o-play',
            self::Completed => 'heroicon-o-check-badge',
            self::Superseded => 'heroicon-o-arrow-path',
            self::Cancelled => 'heroicon-o-x-circle',
        };
    }
}
```

### B. `BookingStatus::Completed` Addition
In `app/Domain/Booking/Enums/BookingStatus.php`:
```php
case Completed = 'completed';

public function isTerminal(): bool
{
    return in_array($this, [self::Declined, self::Cancelled, self::Expired, self::Completed], true);
}
```

### C. `OperationalAlertSeverity`
`app/Domain/Operations/Enums/OperationalAlertSeverity.php`:
- `Critical = 'critical'`
- `Warning = 'warning'`
- `Info = 'info'`

---

## 3. Data Transfer Objects (DTOs)

### A. `DriverTripViewDTO` (Zero Financial Leakage Guarantee)
`app/Domain/Operations/DTOs/DriverTripViewDTO.php`
Strict allowlist ensuring no prices, currencies, deposits, balances, discounts, or internal staff notes reach chauffeur screens:
```php
readonly class DriverTripViewDTO
{
    public function __construct(
        public string $reference,
        public ServiceType $service_type,
        public string $service_type_label,
        public Carbon $scheduled_pickup_time,
        public string $scheduled_pickup_time_formatted,
        public string $pickup_location_text,
        public ?string $pickup_navigation_url,
        public ?string $destination_text,
        public ?string $destination_navigation_url,
        public array $intermediate_stops, // array of ['location' => string, 'notes' => ?string]
        public ?string $flight_number,
        public ?Carbon $flight_arrival_time,
        public ?string $name_board_text,
        public ?string $waiting_instructions,
        public int $passenger_count,
        public int $luggage_count,
        public string $lead_traveler_first_name,
        public ?string $lead_traveler_phone_clean,
        public ?string $lead_traveler_whatsapp_url,
        public DriverTripStatus $current_status,
        public string $current_status_label,
        public array $allowed_next_milestones,
        public ?string $issue_notes,
        public ?Carbon $issue_reported_at,
        public string $vehicle_name,
        public string $vehicle_license_plate,
    ) {}
}
```

### B. `CalendarItemDTO` & `AgendaItemDTO`
- Contains block metadata, vehicle details, schedule times, kind, non-color visual markers (badges, icons, border styles), and whether an operational conflict is detected.
- Handles polymorphic source safely (null for Maintenance / DriverUnavailability; Booking details for rides).

---

## 4. Domain Services

### A. `BookingLifecycleService::complete()`
`app/Domain/Booking/Services/BookingLifecycleService.php`:
- Verifies `$booking->status === BookingStatus::Confirmed`.
- Locks row with `lockForUpdate()`.
- Updates `status = BookingStatus::Completed`, `completed_at = Carbon::now('Asia/Colombo')`.
- Records audit action `booking.completed` with `actorType: $actor ? 'staff' : 'driver'`.
- Dispatches transactional outbox event `booking.completed` (`evt_booking_{id}_completed_v1`).

### B. `DriverAssignmentService`
`app/Domain/Operations/Services/DriverAssignmentService.php`:
1. `resolveTiedDriver(Vehicle $vehicle, Carbon $serviceDate): ?Driver`: Queries `VehicleDriverAssignment` with `scopeOverlapping`, prioritizing `is_permanent = true` and earliest `starts_at`.
2. `assignDriver(Booking $booking, Driver $driver, User $staff, bool $isReplacement = false, ?string $replacementReason = null): DriverAssignment`:
   - Validates that driver has no overlapping active chauffeured block on *any* vehicle.
   - If `$isReplacement`, requires non-empty reason and authorization check (`BookingReview` or `BookingConfirm`).
   - Supersedes any previous active assignment for this booking.
   - Updates all grouped schedule blocks (`group_id`) to attribute `$driver->id`.
   - Revokes any existing active driver tokens for this booking.
   - Generates high-entropy 256-bit token (`random_bytes(32)`), stores SHA-256 hash in `driver_access_tokens`, with expiry set to `$bookingEnd->copy()->addHours(48)`.
   - Updates `$booking->update(['assigned_driver_id' => $driver->id, 'current_driver_assignment_id' => $assignment->id])`.
   - Records audit event `driver.assigned` and outbox event `driver.assigned`.
3. `rotateTripToken(Booking $booking, User $staff, string $reason): array`: Revokes active tokens and issues a new token.
4. `revokeDriverAccess(Booking $booking, User $staff, string $reason): void`: Revokes tokens and marks assignment `cancelled`.

### C. `DriverTripService`
`app/Domain/Operations/Services/DriverTripService.php`:
1. `validateToken(string $rawToken): ?DriverAccessToken`: Hashes with `hash('sha256', $rawToken)`, queries `driver_access_tokens` where `revoked_at IS NULL` and `expires_at > now()`. Returns null if invalid or expired.
2. `updateTripStatus(DriverAccessToken $tokenRecord, DriverTripStatus $newStatus, ?string $notes = null): DriverAssignment`:
   - Enforces DB transaction with `lockForUpdate()` on assignment and booking.
   - Verifies booking is in `Confirmed` state.
   - **Idempotency**: If current status === `$newStatus`, returns immediately without duplicates.
   - **Monotonicity**: Verifies `$newStatus` is in `allowedNextStates()`.
   - Updates milestone timestamp on `driver_assignments` and appends to `driver_trip_updates`.
   - If `$newStatus === DriverTripStatus::Completed`: calls `BookingLifecycleService::complete()`.
   - Audits with `actor: null`, `actorType: 'driver'`.
   - Records outbox event `driver.trip_updated` with deterministic idempotency key.
3. `reportIssue(DriverAccessToken $tokenRecord, string $notes): DriverAssignment`:
   - Appends to `driver_trip_updates` with status `issue_reported`.
   - Updates `issue_reported_notes` and `issue_reported_at` on `driver_assignments` without altering active milestone.
   - Audits and dispatches outbox alert.

### D. `OperationsScheduleProjectionService`
`app/Domain/Operations/Services/OperationsScheduleProjectionService.php`:
- Queries `VehicleScheduleBlock` for date ranges in `Asia/Colombo`.
- Eager loads polymorphic `source` using `morphWith` for `Booking` relations.
- Runs in-memory interval sweep to flag overlaps on the same vehicle (excluding same `group_id`).
- Formats `CalendarItemDTO` and `AgendaItemDTO`.

### E. `OperationsAlertService`
`app/Domain/Operations/Services/OperationsAlertService.php`:
- Detects schedule overlaps (vehicle & driver).
- Detects imminent deadlines (<5m checkout holds, <2h proposal deadlines).
- Detects stale submissions (>24h unreviewed).
- Detects unassigned chauffeured confirmed trips within 48h.
- Detects payment frictions and failed message deliveries.

---

## 5. Web Routes, Controllers & Views

### A. Routes
`routes/web.php`:
```php
Route::get('/driver/trip/{token}', [DriverTripController::class, 'show'])->name('driver.trip.show');
Route::post('/driver/trip/{token}/status', [DriverTripController::class, 'updateStatus'])->name('driver.trip.status');
Route::post('/driver/trip/{token}/issue', [DriverTripController::class, 'reportIssue'])->name('driver.trip.issue');
```
*Note: Status and issue endpoints use stateless token authorization with the raw token, immune to session CSRF 419 errors during mobile cellular dead zones.*

### B. Headers on Driver Views
Both valid trip view and neutral privacy shell enforce:
- `X-Robots-Tag: noindex, nofollow`
- `Referrer-Policy: no-referrer`
- `Cache-Control: no-store, no-cache, must-revalidate, private`

### C. Dedicated Layout
`resources/views/layouts/driver.blade.php`:
- Distraction-free mobile viewport (no desktop site nav).
- Contrast $\ge 4.5:1$, touch targets $\ge 48\times 48\text{px}$.
- Responsive down to 320px–390px.
- Client-side pending / offline sync indicator.

---

## 6. Filament Integration

1. **Operations Calendar Page**: `App\Filament\Pages\OperationsCalendar` with Month, Week, Day, and Agenda tabs, vehicle filter, and operational alerts banner.
2. **Booking Resource Actions**:
   - `assign_driver` modal action: recommends tied driver, replacement driver selection with required reason, copy trip link.
   - `rotate_driver_link` and `revoke_driver_access` actions.
   - Table column for assigned driver and trip status badge.

