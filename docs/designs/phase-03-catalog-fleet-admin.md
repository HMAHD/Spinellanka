# Design Document: Phase 03 — Catalog, Fleet, and Administration

* **Author:** Lead System Architect
* **Date:** 2026-11-23
* **Status:** Resolved — Pending Project Lead Approval
* **Target Release:** Phase 03 (`phase/03-catalog-fleet-admin`)
* **Review Protocol:** Full Elephant-Goldfish Model (EGM) Workflow (Concepts Brief -> Design Draft -> 3 Parallel Goldfish Reviews -> Synthesis Report -> Resolved Architecture)

---

## 1. Executive Summary & Architectural Overview

Spinellanka operates an advance-booking luxury transport platform in Sri Lanka centered on an exact five-vehicle fleet. In this domain, travelers do not book an abstract category of car; they select an exact, verified vehicle with confirmed seating and luggage capacities, authentic photos, verified amenities, and dedicated drivers.

Phase 03 establishes the foundational **Catalog Module** within the Spinellanka modular monolith. Following the comprehensive Elephant-Goldfish Model (EGM) architectural review, this resolved design addresses every critical concurrency, storage, data integrity, and security invariant:
1. **Exact-Vehicle Catalog**: Verified public specifications strictly separated from internal operational tracking identifiers (license plate, VIN, internal fleet ID, registration expiry).
2. **Deterministic Concurrency & Overlap Prevention**: Mathematical interval algebra, canonical lock ordering across parent entities, and locking reads (`lockForUpdate()`) on child assignment records prevent MariaDB deadlocks and snapshot phantom reads.
3. **Universal Physical Driver Exclusivity**: A driver can never be assigned to two vehicles concurrently (permanent or temporary). Temporary assignments take precedence over permanent base drivers during their active window.
4. **Quarantine Storage & EXIF Stripping**: Unapproved media is quarantined on private storage; approved media is stripped of EXIF geolocation metadata and promoted to public storage.
5. **Filament 5 Administrative Shell**: Thin resources compliant with Filament 5 (`Schema $schema`, `components()`, `paginationMode(PaginationMode::Simple)`, typed `Heroicon` enums) with relation managers overriding actions via `using()` to enforce domain service validation.
6. **Granular Capability Authorization & Append-Only Audit**: All catalog routes, resources, and relation actions are gated by canonical permissions (`fleet.view`, `fleet.manage`) and record structured forensic audit events via `App\Domain\Audit\Services\AuditService::record()`.
7. **Strict Eloquent Attributes**: All models declare explicit `$attributes`, `$casts`, and `$hidden` arrays to satisfy `Model::shouldBeStrict()` and prevent sensitive operational data leaks.
8. **Fictional Test Factories & Zero Production Data**: Zero production fleet facts are seeded. Factories generate explicitly fictional test data (`TEST-FLEET-###`).

---

## 2. Domain Model & Relational Schema

### 2.1 Entity Relationship Diagram

```
+-----------------------------------+
|             vehicles              |
+-----------------------------------+
| id: bigint unsigned (PK)          |
| public_name: varchar(255)         |
| internal_fleet_id: varchar(50) UQ |
| make: varchar(100)                |
| model: varchar(100)               |
| year: smallint unsigned           |
| license_plate: varchar(50) UQ     |
| vin: varchar(100) nullable        |
| registration_expiry: date nullable|
| insurance_expiry: date nullable   |
| vehicle_type: varchar(50)         |
| passenger_capacity: tinyint unsgnd|
| luggage_capacity: tinyint unsgnd  |
| transmission: varchar(20)         |
| has_ac: boolean                   |
| accessibility_features: json      |
| amenities: json                   |
| eligible_for_pickup: boolean      |
| eligible_for_transfer: boolean    |
| eligible_for_ride: boolean        |
| eligible_for_rent: boolean        |
| turnaround_buffer_minutes: smallint|
| status: varchar(30)               |
| internal_notes: text nullable     |
| timestamps (created_at,updated_at)|
+-----------------------------------+
       | 1                        | 1
       |                          |
       | *                        | *
+----------------------+   +------------------------------------+
|    vehicle_media     |   |    vehicle_driver_assignments      |
+----------------------+   +------------------------------------+
| id: bigint unsigned  |   | id: bigint unsigned (PK)           |
| vehicle_id: bigint FK|   | vehicle_id: bigint unsigned FK     |
| file_path: varchar   |   | driver_id: bigint unsigned FK      |
| disk: varchar(50)    |   | starts_at: datetime (Asia/Colombo) |
| media_type: varchar  |   | ends_at: datetime nullable (A/C)   |
| alt_text: varchar    |   | is_permanent: boolean              |
| photographer: varchar|   | reason: varchar(255) nullable      |
| usage_rights: varchar|   | assigned_by_user_id: bigint FK     |
| sort_order: smallint |   | timestamps                         |
| is_approved: boolean |   +------------------------------------+
| timestamps           |                  * |
+----------------------+                    | 1
                                  +------------------------------------+
                                  |              drivers               |
                                  +------------------------------------+
                                  | id: bigint unsigned (PK)           |
                                  | public_name: varchar(255)          |
                                  | full_name: varchar(255)            |
                                  | phone: varchar(50)                 |
                                  | whatsapp: varchar(50) nullable     |
                                  | portrait_path: varchar nullable    |
                                  | public_trust_facts: json nullable  |
                                  | license_number: varchar nullable   |
                                  | license_expiry: date nullable      |
                                  | status: varchar(30)                |
                                  | internal_notes: text nullable      |
                                  | timestamps                         |
                                  +------------------------------------+

+-----------------------------------+         +------------------------------------+
|              extras               | 1 --- * |        extra_compatibility         |
+-----------------------------------+         +------------------------------------+
| id: bigint unsigned (PK)          |         | id: bigint unsigned (PK)           |
| name: varchar(255)                |         | extra_id: bigint unsigned FK       |
| slug: varchar(100) UNIQUE         |         | service_type: varchar(50) nullable |
| description: text nullable        |         | vehicle_type: varchar(50) nullable |
| price_minor_units: int unsigned   |         | vehicle_id: bigint FK nullable     |
| currency: varchar(3) default USD  |         | is_compatible: boolean             |
| pricing_type: varchar(30)         |         | scope_key: varchar(120) VIRTUAL UQ |
| max_quantity: tinyint unsigned    |         | timestamps                         |
| is_active: boolean                |         +------------------------------------+
| timestamps                        |
+-----------------------------------+
```

---

## 3. Detailed Database Schema Specifications

### 3.1 Migration: `create_vehicles_table`
```php
Schema::create('vehicles', function (Blueprint $table) {
    $table->id();
    $table->string('public_name');
    $table->string('internal_fleet_id', 50)->unique();
    $table->string('make', 100);
    $table->string('model', 100);
    $table->unsignedSmallInteger('year');
    $table->string('license_plate', 50)->unique();
    $table->string('vin', 100)->nullable();
    $table->date('registration_expiry')->nullable();
    $table->date('insurance_expiry')->nullable();
    $table->string('vehicle_type', 50);
    $table->unsignedTinyInteger('passenger_capacity');
    $table->unsignedTinyInteger('luggage_capacity');
    $table->string('transmission', 20);
    $table->boolean('has_ac')->default(true);
    $table->json('accessibility_features')->nullable();
    $table->json('amenities')->nullable();
    $table->boolean('eligible_for_pickup')->default(false);
    $table->boolean('eligible_for_transfer')->default(false);
    $table->boolean('eligible_for_ride')->default(false);
    $table->boolean('eligible_for_rent')->default(false);
    $table->unsignedSmallInteger('turnaround_buffer_minutes')->default(90);
    $table->string('status', 30)->default('active');
    $table->text('internal_notes')->nullable();
    $table->timestamps();

    // Indexes
    $table->index('status', 'idx_vehicles_status');
    $table->index(['vehicle_type', 'status'], 'idx_vehicles_type_status');
    $table->index(
        ['status', 'eligible_for_pickup', 'eligible_for_transfer', 'eligible_for_ride', 'eligible_for_rent'],
        'idx_vehicles_eligibility'
    );

    // MariaDB DDL Check Constraints
    $table->check('year BETWEEN 2000 AND 2100', 'chk_vehicles_year');
    $table->check('passenger_capacity BETWEEN 1 AND 50', 'chk_vehicles_passenger_cap');
    $table->check('luggage_capacity BETWEEN 0 AND 50', 'chk_vehicles_luggage_cap');
    $table->check('turnaround_buffer_minutes BETWEEN 30 AND 720', 'chk_vehicles_turnaround_buffer');
    $table->check("status IN ('active', 'maintenance', 'retired')", 'chk_vehicles_status');
    $table->check("transmission IN ('automatic', 'manual')", 'chk_vehicles_transmission');
});
```

### 3.2 Migration: `create_vehicle_media_table`
```php
Schema::create('vehicle_media', function (Blueprint $table) {
    $table->id();
    $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
    $table->string('file_path');
    $table->string('disk', 50)->default('public');
    $table->string('media_type', 50);
    $table->string('alt_text');
    $table->string('photographer')->nullable();
    $table->string('usage_rights')->nullable();
    $table->unsignedSmallInteger('sort_order')->default(0);
    $table->boolean('is_approved')->default(false);
    $table->timestamps();

    // Indexes
    $table->index(['vehicle_id', 'is_approved', 'sort_order'], 'idx_vehicle_media_sort');
});
```

### 3.3 Migration: `create_drivers_table`
```php
Schema::create('drivers', function (Blueprint $table) {
    $table->id();
    $table->string('public_name');
    $table->string('full_name');
    $table->string('phone', 50);
    $table->string('whatsapp', 50)->nullable();
    $table->string('portrait_path')->nullable();
    $table->json('public_trust_facts')->nullable();
    $table->string('license_number', 100)->nullable();
    $table->date('license_expiry')->nullable();
    $table->string('status', 30)->default('active');
    $table->text('internal_notes')->nullable();
    $table->timestamps();

    // Indexes
    $table->index('status', 'idx_drivers_status');
    $table->check("status IN ('active', 'inactive', 'on_leave')", 'chk_drivers_status');
});
```

### 3.4 Migration: `create_vehicle_driver_assignments_table`
```php
Schema::create('vehicle_driver_assignments', function (Blueprint $table) {
    $table->id();
    $table->foreignId('vehicle_id')->constrained('vehicles')->restrictOnDelete();
    $table->foreignId('driver_id')->constrained('drivers')->restrictOnDelete();
    $table->dateTime('starts_at'); // Authoritative Asia/Colombo wall-clock
    $table->dateTime('ends_at')->nullable();
    $table->boolean('is_permanent')->default(false);
    $table->string('reason')->nullable();
    $table->foreignId('assigned_by_user_id')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();

    // Indexes
    $table->index(['vehicle_id', 'starts_at', 'ends_at'], 'idx_vda_vehicle_window');
    $table->index(['driver_id', 'starts_at', 'ends_at'], 'idx_vda_driver_window');
    $table->index(['vehicle_id', 'is_permanent', 'ends_at'], 'idx_vda_vehicle_perm');
    $table->index(['driver_id', 'is_permanent', 'ends_at'], 'idx_vda_driver_perm');

    // MariaDB DDL Check Constraint for valid interval
    $table->check('ends_at IS NULL OR ends_at > starts_at', 'chk_vda_valid_interval');
});
```

### 3.5 Migration: `create_extras_table`
```php
Schema::create('extras', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('slug', 100)->unique();
    $table->text('description')->nullable();
    $table->unsignedInteger('price_minor_units')->default(0);
    $table->string('currency', 3)->default('USD');
    $table->string('pricing_type', 30)->default('per_booking');
    $table->unsignedTinyInteger('max_quantity')->default(1);
    $table->boolean('is_active')->default(true);
    $table->timestamps();

    // Indexes & Constraints
    $table->index('is_active', 'idx_extras_active');
    $table->check("currency IN ('USD', 'LKR')", 'chk_extras_currency');
    $table->check("pricing_type IN ('per_booking', 'per_day')", 'chk_extras_pricing_type');
    $table->check('max_quantity >= 1', 'chk_extras_max_quantity');
    $table->check('price_minor_units >= 0', 'chk_extras_price_non_negative');
});
```

### 3.6 Migration: `create_extra_compatibility_table`
```php
Schema::create('extra_compatibility', function (Blueprint $table) {
    $table->id();
    $table->foreignId('extra_id')->constrained('extras')->cascadeOnDelete();
    $table->string('service_type', 50)->nullable();
    $table->string('vehicle_type', 50)->nullable();
    $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->cascadeOnDelete();
    $table->boolean('is_compatible')->default(true);
    $table->timestamps();

    // Virtual generated column for deterministically enforcing uniqueness across nullable columns
    $table->string('scope_key', 120)->virtualAs(
        "CONCAT(extra_id, ':', COALESCE(service_type, '*'), ':', COALESCE(vehicle_type, '*'), ':', COALESCE(vehicle_id, 0))"
    );
    $table->unique('scope_key', 'uq_extra_compat_scope');
    $table->index(['extra_id', 'service_type', 'vehicle_type'], 'idx_extra_compat_lookup');
});
```

---

## 4. Strict Model Specifications (`Model::shouldBeStrict()`)

All models reside under `App\Models\` and include explicit `$attributes`, `$casts`, and `$hidden` arrays.

### 4.1 Model: `App\Models\Vehicle`
```php
namespace App\Models;

use App\Domain\Catalog\Enums\TransmissionType;
use App\Domain\Catalog\Enums\VehicleStatus;
use App\Domain\Catalog\Enums\VehicleType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vehicle extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $attributes = [
        'has_ac' => true,
        'eligible_for_pickup' => false,
        'eligible_for_transfer' => false,
        'eligible_for_ride' => false,
        'eligible_for_rent' => false,
        'turnaround_buffer_minutes' => 90,
        'status' => 'active',
        'accessibility_features' => '[]',
        'amenities' => '[]',
        'vin' => null,
        'registration_expiry' => null,
        'insurance_expiry' => null,
        'internal_notes' => null,
    ];

    protected $hidden = [
        'license_plate',
        'vin',
        'internal_fleet_id',
        'registration_expiry',
        'insurance_expiry',
        'internal_notes',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'passenger_capacity' => 'integer',
            'luggage_capacity' => 'integer',
            'has_ac' => 'boolean',
            'eligible_for_pickup' => 'boolean',
            'eligible_for_transfer' => 'boolean',
            'eligible_for_ride' => 'boolean',
            'eligible_for_rent' => 'boolean',
            'turnaround_buffer_minutes' => 'integer',
            'registration_expiry' => 'date',
            'insurance_expiry' => 'date',
            'accessibility_features' => 'array',
            'amenities' => 'array',
            'status' => VehicleStatus::class,
            'vehicle_type' => VehicleType::class,
            'transmission' => TransmissionType::class,
        ];
    }

    public function media(): HasMany
    {
        return $this->hasMany(VehicleMedia::class)->orderBy('sort_order');
    }

    public function driverAssignments(): HasMany
    {
        return $this->hasMany(VehicleDriverAssignment::class);
    }
}
```

### 4.2 Model: `App\Models\VehicleMedia`
```php
namespace App\Models;

use App\Domain\Catalog\Enums\MediaType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleMedia extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $attributes = [
        'disk' => 'public',
        'sort_order' => 0,
        'is_approved' => false,
        'photographer' => null,
        'usage_rights' => null,
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_approved' => 'boolean',
            'media_type' => MediaType::class,
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }
}
```

### 4.3 Model: `App\Models\Driver`
```php
namespace App\Models;

use App\Domain\Catalog\Enums\DriverStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Driver extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $attributes = [
        'status' => 'active',
        'whatsapp' => null,
        'portrait_path' => null,
        'public_trust_facts' => '[]',
        'license_number' => null,
        'license_expiry' => null,
        'internal_notes' => null,
    ];

    protected $hidden = [
        'full_name',
        'phone',
        'whatsapp',
        'license_number',
        'license_expiry',
        'internal_notes',
    ];

    protected function casts(): array
    {
        return [
            'license_expiry' => 'date',
            'public_trust_facts' => 'array',
            'status' => DriverStatus::class,
        ];
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(VehicleDriverAssignment::class);
    }
}
```

### 4.4 Model: `App\Models\VehicleDriverAssignment`
```php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleDriverAssignment extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $attributes = [
        'is_permanent' => false,
        'ends_at' => null,
        'reason' => null,
        'assigned_by_user_id' => null,
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_permanent' => 'boolean',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function assignedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_user_id');
    }
}
```

### 4.5 Model: `App\Models\Extra`
```php
namespace App\Models;

use App\Domain\Catalog\Enums\ExtraPricingType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Extra extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $attributes = [
        'price_minor_units' => 0,
        'currency' => 'USD',
        'pricing_type' => 'per_booking',
        'max_quantity' => 1,
        'is_active' => true,
        'description' => null,
    ];

    protected function casts(): array
    {
        return [
            'price_minor_units' => 'integer',
            'pricing_type' => ExtraPricingType::class,
            'max_quantity' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function compatibilities(): HasMany
    {
        return $this->hasMany(ExtraCompatibility::class);
    }
}
```

### 4.6 Model: `App\Models\ExtraCompatibility`
```php
namespace App\Models;

use App\Domain\Booking\ServiceType;
use App\Domain\Catalog\Enums\VehicleType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExtraCompatibility extends Model
{
    use HasFactory;

    protected $table = 'extra_compatibility';
    protected $guarded = ['id'];

    protected $attributes = [
        'is_compatible' => true,
        'service_type' => null,
        'vehicle_type' => null,
        'vehicle_id' => null,
    ];

    protected function casts(): array
    {
        return [
            'is_compatible' => 'boolean',
            'service_type' => ServiceType::class,
            'vehicle_type' => VehicleType::class,
        ];
    }

    public function extra(): BelongsTo
    {
        return $this->belongsTo(Extra::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }
}
```

---

## 5. Domain Enums (`App\Domain\Catalog\Enums\`)

All enums implement `Filament\Support\Contracts\HasLabel` and `HasColor` where applicable:

```php
// VehicleType.php
enum VehicleType: string implements HasLabel {
    case LuxuryVan = 'luxury_van';
    case Sedan = 'sedan';
    case Suv = 'suv';
    case Minivan = 'minivan';
    case Compact = 'compact';
    // label() mapping
}

// TransmissionType.php
enum TransmissionType: string implements HasLabel {
    case Automatic = 'automatic';
    case Manual = 'manual';
    // label() mapping
}

// VehicleStatus.php
enum VehicleStatus: string implements HasLabel, HasColor {
    case Active = 'active';
    case Maintenance = 'maintenance';
    case Retired = 'retired';
    // label() and color() mapping (active=success, maintenance=warning, retired=gray)
}

// MediaType.php
enum MediaType: string implements HasLabel {
    case ExteriorFront = 'exterior_front';
    case ExteriorRear = 'exterior_rear';
    case Cabin = 'cabin';
    case Luggage = 'luggage';
    case Dashboard = 'dashboard';
    case Feature = 'feature';
    // label() mapping
}

// DriverStatus.php
enum DriverStatus: string implements HasLabel, HasColor {
    case Active = 'active';
    case Inactive = 'inactive';
    case OnLeave = 'on_leave';
    // label() and color() mapping
}

// ExtraPricingType.php
enum ExtraPricingType: string implements HasLabel {
    case PerBooking = 'per_booking';
    case PerDay = 'per_day';
    // label() mapping
}
```
*(Note: `ServiceType` is reused directly from `App\Domain\Booking\ServiceType`).*

---

## 6. Domain Services & Business Invariants

### 6.1 `DriverAssignmentService`
* **Namespace:** `App\Domain\Catalog\Services\DriverAssignmentService`
* **Exception:** `App\Domain\Catalog\Exceptions\DriverAssignmentConflictException`

```php
namespace App\Domain\Catalog\Services;

use App\Domain\Audit\Services\AuditService;
use App\Domain\Catalog\Enums\DriverStatus;
use App\Domain\Catalog\Exceptions\DriverAssignmentConflictException;
use App\Models\Driver;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleDriverAssignment;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class DriverAssignmentService
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    public function assignPermanentDriver(
        Vehicle $vehicle,
        Driver $driver,
        CarbonInterface $startsAt,
        ?string $reason = null,
        ?User $assignedBy = null
    ): VehicleDriverAssignment {
        return DB::transaction(function () use ($vehicle, $driver, $startsAt, $reason, $assignedBy) {
            // 1. Canonical Lock Ordering: sort entity IDs deterministically
            $this->lockEntitiesCanonical($vehicle->id, $driver->id);

            // Re-fetch locked instances
            $lockedVehicle = Vehicle::findOrFail($vehicle->id);
            $lockedDriver = Driver::findOrFail($driver->id);

            if ($lockedDriver->status !== DriverStatus::Active) {
                throw new DriverAssignmentConflictException("Driver '{$lockedDriver->public_name}' is not active.");
            }

            $startsAtLocal = Carbon::parse($startsAt)->setTimezone('Asia/Colombo');

            // 2. Validate Overlap using Locking Reads (lockForUpdate)
            $this->validateDriverNoOverlap($lockedDriver->id, $startsAtLocal, null);
            $this->validateVehiclePermanentNoOverlap($lockedVehicle->id, $startsAtLocal, null);

            // 3. Auto-close any existing open-ended permanent assignment for this vehicle
            $activePerm = VehicleDriverAssignment::query()
                ->where('vehicle_id', $lockedVehicle->id)
                ->where('is_permanent', true)
                ->whereNull('ends_at')
                ->lockForUpdate()
                ->first();

            if ($activePerm) {
                if ($activePerm->starts_at->greaterThanOrEqualTo($startsAtLocal)) {
                    throw new DriverAssignmentConflictException("New permanent assignment starts before prior assignment.");
                }
                $activePerm->update(['ends_at' => $startsAtLocal]);
                $this->auditService->record(
                    action: 'fleet.driver_assignment.closed',
                    actor: $assignedBy,
                    summary: "Closed permanent driver assignment for vehicle {$lockedVehicle->public_name}",
                    metadata: ['assignment_id' => $activePerm->id, 'closed_at' => $startsAtLocal->toIso8601String()],
                    auditable: $activePerm
                );
            }

            // 4. Create new permanent assignment
            $assignment = VehicleDriverAssignment::create([
                'vehicle_id' => $lockedVehicle->id,
                'driver_id' => $lockedDriver->id,
                'starts_at' => $startsAtLocal,
                'ends_at' => null,
                'is_permanent' => true,
                'reason' => $reason ?? 'Primary tied driver',
                'assigned_by_user_id' => $assignedBy?->id,
            ]);

            $this->auditService->record(
                action: 'fleet.driver_assignment.created',
                actor: $assignedBy,
                summary: "Assigned permanent driver {$lockedDriver->public_name} to vehicle {$lockedVehicle->public_name}",
                metadata: [
                    'vehicle_id' => $lockedVehicle->id,
                    'driver_id' => $lockedDriver->id,
                    'is_permanent' => true,
                    'starts_at' => $startsAtLocal->toIso8601String(),
                ],
                auditable: $assignment
            );

            return $assignment;
        });
    }

    public function assignTemporaryDriver(
        Vehicle $vehicle,
        Driver $driver,
        CarbonInterface $startsAt,
        CarbonInterface $endsAt,
        ?string $reason = null,
        ?User $assignedBy = null,
        ?int $excludeAssignmentId = null
    ): VehicleDriverAssignment {
        return DB::transaction(function () use ($vehicle, $driver, $startsAt, $endsAt, $reason, $assignedBy, $excludeAssignmentId) {
            $this->lockEntitiesCanonical($vehicle->id, $driver->id);

            $lockedVehicle = Vehicle::findOrFail($vehicle->id);
            $lockedDriver = Driver::findOrFail($driver->id);

            if ($lockedDriver->status !== DriverStatus::Active) {
                throw new DriverAssignmentConflictException("Driver '{$lockedDriver->public_name}' is not active.");
            }

            $startsAtLocal = Carbon::parse($startsAt)->setTimezone('Asia/Colombo');
            $endsAtLocal = Carbon::parse($endsAt)->setTimezone('Asia/Colombo');

            if ($endsAtLocal->lessThanOrEqualTo($startsAtLocal)) {
                throw new DriverAssignmentConflictException("Temporary assignment ends_at must be strictly after starts_at.");
            }

            // Universal Physical Driver Exclusivity: Driver cannot be double-booked anywhere
            $this->validateDriverNoOverlap($lockedDriver->id, $startsAtLocal, $endsAtLocal, $excludeAssignmentId);

            // Vehicle Temporary Exclusivity: Vehicle cannot have two overlapping temporary drivers
            $this->validateVehicleTemporaryNoOverlap($lockedVehicle->id, $startsAtLocal, $endsAtLocal, $excludeAssignmentId);

            $assignment = VehicleDriverAssignment::create([
                'vehicle_id' => $lockedVehicle->id,
                'driver_id' => $lockedDriver->id,
                'starts_at' => $startsAtLocal,
                'ends_at' => $endsAtLocal,
                'is_permanent' => false,
                'reason' => $reason,
                'assigned_by_user_id' => $assignedBy?->id,
            ]);

            $this->auditService->record(
                action: 'fleet.driver_assignment.created',
                actor: $assignedBy,
                summary: "Assigned temporary driver {$lockedDriver->public_name} to vehicle {$lockedVehicle->public_name}",
                metadata: [
                    'vehicle_id' => $lockedVehicle->id,
                    'driver_id' => $lockedDriver->id,
                    'is_permanent' => false,
                    'starts_at' => $startsAtLocal->toIso8601String(),
                    'ends_at' => $endsAtLocal->toIso8601String(),
                ],
                auditable: $assignment
            );

            return $assignment;
        });
    }

    public function getEffectiveDriver(Vehicle $vehicle, CarbonInterface $at): ?Driver
    {
        $atLocal = Carbon::parse($at)->setTimezone('Asia/Colombo');

        // Precedence 1: Active temporary replacement assignment
        $tempAssignment = VehicleDriverAssignment::query()
            ->where('vehicle_id', $vehicle->id)
            ->where('is_permanent', false)
            ->where('starts_at', '<=', $atLocal)
            ->where('ends_at', '>', $atLocal)
            ->with('driver')
            ->first();

        if ($tempAssignment && $tempAssignment->driver->status === DriverStatus::Active) {
            return $tempAssignment->driver;
        }

        // Precedence 2: Base permanent tied driver
        $permAssignment = VehicleDriverAssignment::query()
            ->where('vehicle_id', $vehicle->id)
            ->where('is_permanent', true)
            ->where('starts_at', '<=', $atLocal)
            ->where(function ($q) use ($atLocal) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>', $atLocal);
            })
            ->with('driver')
            ->first();

        if ($permAssignment && $permAssignment->driver->status === DriverStatus::Active) {
            return $permAssignment->driver;
        }

        return null;
    }

    protected function lockEntitiesCanonical(int $vehicleId, int $driverId): void
    {
        // Enforce strict lock acquisition order to avoid AB-BA deadlocks
        Vehicle::where('id', $vehicleId)->lockForUpdate()->firstOrFail();
        Driver::where('id', $driverId)->lockForUpdate()->firstOrFail();
    }

    protected function validateDriverNoOverlap(int $driverId, Carbon $start, ?Carbon $end, ?int $excludeId = null): void
    {
        $conflict = VehicleDriverAssignment::query()
            ->where('driver_id', $driverId)
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->where(function ($q) use ($start, $end) {
                $q->where(function ($sub) use ($end) {
                    if ($end !== null) {
                        $sub->where('starts_at', '<', $end);
                    }
                })->where(function ($sub) use ($start) {
                    $sub->whereNull('ends_at')->orWhere('ends_at', '>', $start);
                });
            })
            ->lockForUpdate()
            ->exists();

        if ($conflict) {
            throw new DriverAssignmentConflictException("Driver is already scheduled on an overlapping assignment.");
        }
    }

    protected function validateVehiclePermanentNoOverlap(int $vehicleId, Carbon $start, ?Carbon $end, ?int $excludeId = null): void
    {
        $conflict = VehicleDriverAssignment::query()
            ->where('vehicle_id', $vehicleId)
            ->where('is_permanent', true)
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->where(function ($q) use ($start, $end) {
                $q->where(function ($sub) use ($end) {
                    if ($end !== null) {
                        $sub->where('starts_at', '<', $end);
                    }
                })->where(function ($sub) use ($start) {
                    $sub->whereNull('ends_at')->orWhere('ends_at', '>', $start);
                });
            })
            ->lockForUpdate()
            ->exists();

        if ($conflict) {
            throw new DriverAssignmentConflictException("Vehicle already has an overlapping permanent assignment.");
        }
    }

    protected function validateVehicleTemporaryNoOverlap(int $vehicleId, Carbon $start, Carbon $end, ?int $excludeId = null): void
    {
        $conflict = VehicleDriverAssignment::query()
            ->where('vehicle_id', $vehicleId)
            ->where('is_permanent', false)
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->where('starts_at', '<', $end)
            ->where('ends_at', '>', $start)
            ->lockForUpdate()
            ->exists();

        if ($conflict) {
            throw new DriverAssignmentConflictException("Vehicle already has a temporary driver covering this window.");
        }
    }
}
```

### 6.2 `VehicleService`
* **Namespace:** `App\Domain\Catalog\Services\VehicleService`
Handles vehicle lifecycle mutations, status changes, and retirement invariants:
```php
public function updateStatus(Vehicle $vehicle, VehicleStatus $newStatus, ?string $reason = null, ?User $actor = null): Vehicle
{
    return DB::transaction(function () use ($vehicle, $newStatus, $reason, $actor) {
        $oldStatus = $vehicle->status;
        $vehicle->update(['status' => $newStatus]);

        // When a vehicle is retired, close any open-ended driver assignments
        if ($newStatus === VehicleStatus::Retired) {
            $now = Carbon::now('Asia/Colombo');
            $activeAssignments = $vehicle->driverAssignments()->whereNull('ends_at')->get();
            foreach ($activeAssignments as $assignment) {
                $assignment->update(['ends_at' => $now]);
            }
        }

        $this->auditService->record(
            action: 'fleet.vehicle.status_changed',
            actor: $actor,
            summary: "Changed status of vehicle {$vehicle->public_name} from {$oldStatus->value} to {$newStatus->value}",
            metadata: ['old_status' => $oldStatus->value, 'new_status' => $newStatus->value, 'reason' => $reason],
            auditable: $vehicle
        );

        return $vehicle;
    });
}
```

### 6.3 `ExtraService`
* **Namespace:** `App\Domain\Catalog\Services\ExtraService`
Implements the 4-tier compatibility resolution hierarchy:
```php
public function isCompatible(Extra $extra, ServiceType $serviceType, ?Vehicle $vehicle = null): bool
{
    if (!$extra->is_active) {
        return false;
    }

    $rules = ExtraCompatibility::where('extra_id', $extra->id)->get();

    // Fallback: If no compatibility rules exist, extra is globally compatible
    if ($rules->isEmpty()) {
        return true;
    }

    // Tier 1: Exact Vehicle Match
    if ($vehicle !== null) {
        $tier1 = $rules->first(fn ($r) => $r->vehicle_id === $vehicle->id && ($r->service_type === null || $r->service_type === $serviceType));
        if ($tier1 !== null) {
            return $tier1->is_compatible;
        }
    }

    // Tier 2: Service Type + Vehicle Type Match
    if ($vehicle !== null) {
        $tier2 = $rules->first(fn ($r) => $r->service_type === $serviceType && $r->vehicle_type === $vehicle->vehicle_type);
        if ($tier2 !== null) {
            return $tier2->is_compatible;
        }
    }

    // Tier 3: Service-wide Match (vehicle_type IS NULL and vehicle_id IS NULL)
    $tier3 = $rules->first(fn ($r) => $r->service_type === $serviceType && $r->vehicle_type === null && $r->vehicle_id === null);
    if ($tier3 !== null) {
        return $tier3->is_compatible;
    }

    // Tier 4: Vehicle Type Match (service_type IS NULL)
    if ($vehicle !== null) {
        $tier4 = $rules->first(fn ($r) => $r->service_type === null && $r->vehicle_type === $vehicle->vehicle_type);
        if ($tier4 !== null) {
            return $tier4->is_compatible;
        }
    }

    // Default Allowlist Fallback: If rules exist but none match, extra is incompatible
    return false;
}
```

---

## 7. Storage Boundaries & Media Lifecycle

### 7.1 Vehicle Showcase Photography
1. **Upload Quarantine**:
   - Initial uploads land on the private disk: `storage/app/quarantine/vehicles/{id}/{hash}.{ext}`.
   - Record created in `vehicle_media` with `is_approved = false`.
2. **Approval & Promotion**:
   - Authorized staff (`fleet.manage`) reviews the image and clicks "Approve & Publish".
   - Service reads image, strips all EXIF GPS/camera metadata, converts to WebP, and stores under `storage/app/public/vehicles/{id}/media/{sha256}.webp`.
   - Record updated with `is_approved = true`, `disk = 'public'`, and `file_path`.
   - Quarantined file is deleted.
3. **Deletion & Cascade**:
   - When a `VehicleMedia` record is deleted, an Eloquent observer unlinks the physical file from the storage disk.

### 7.2 Driver Portrait Storage
- Stored under `storage/app/public/drivers/portraits/{sha256}.{ext}`.
- Max size 2MB, allowed MIME: `image/jpeg`, `image/png`, `image/webp`.
- EXIF metadata stripped upon upload.
- Prior portrait file unlinked on replacement or driver deletion.

---

## 8. Filament 5 Administrative Staff Shell

### 8.1 Directory & Namespace Structure
```
app/Filament/Resources/Catalog/
├── Vehicles/
│   ├── VehicleResource.php
│   ├── Pages/
│   │   ├── ListVehicles.php
│   │   ├── CreateVehicle.php
│   │   └── EditVehicle.php
│   └── RelationManagers/
│       ├── MediaRelationManager.php
│       └── DriverAssignmentsRelationManager.php
├── Drivers/
│   ├── DriverResource.php
│   └── Pages/
│       └── ManageDrivers.php
└── Extras/
    ├── ExtraResource.php
    └── Pages/
        └── ManageExtras.php
```

### 8.2 Navigation & Icons
- Navigation Group: `"Catalog & Fleet"`
- `VehicleResource`: `Heroicon::OutlinedTruck`
- `DriverResource`: `Heroicon::OutlinedUserGroup`
- `ExtraResource`: `Heroicon::OutlinedPuzzlePiece`

### 8.3 RelationManager Delegation: `DriverAssignmentsRelationManager`
To ensure domain validation and concurrency locking are never bypassed, `DriverAssignmentsRelationManager` overrides `CreateAction` using `using()`:
```php
CreateAction::make()
    ->using(function (array $data, RelationManager $livewire): Model {
        /** @var Vehicle $vehicle */
        $vehicle = $livewire->getOwnerRecord();
        $service = app(DriverAssignmentService::class);

        if ($data['is_permanent'] ?? false) {
            return $service->assignPermanentDriver(
                vehicle: $vehicle,
                driver: Driver::findOrFail($data['driver_id']),
                startsAt: Carbon::parse($data['starts_at'], 'Asia/Colombo'),
                reason: $data['reason'] ?? null,
                assignedBy: auth()->user()
            );
        }

        return $service->assignTemporaryDriver(
            vehicle: $vehicle,
            driver: Driver::findOrFail($data['driver_id']),
            startsAt: Carbon::parse($data['starts_at'], 'Asia/Colombo'),
            endsAt: Carbon::parse($data['ends_at'], 'Asia/Colombo'),
            reason: $data['reason'] ?? null,
            assignedBy: auth()->user()
        );
    });
```

### 8.4 Currency Handling in `ExtraResource`
In `ExtraResource`:
```php
// Form Component:
TextInput::make('price_minor_units')
    ->label('Price (USD)')
    ->numeric()
    ->prefix('$')
    ->required()
    ->formatStateUsing(fn (?int $state): ?string => $state !== null ? number_format($state / 100, 2, '.', '') : null)
    ->dehydrateStateUsing(fn (?string $state): int => $state !== null ? (int) round(((float) $state) * 100) : 0);

// Table Column:
TextColumn::make('price_minor_units')
    ->label('Price')
    ->money('USD', divideBy: 100)
    ->sortable();
```

---

## 9. Security, Policies, and Audit Integration

### 9.1 Policy Matrix (`App\Policies\`)

All policies reside in `app/Policies/` for standard Laravel auto-discovery and reference canonical enum permissions from `App\Domain\Identity\Permission`:

| Model | Policy Class | View Action (`fleet.view`) | Manage Actions (`fleet.manage`) | Delete Rules |
|:---|:---|:---:|:---:|:---|
| `Vehicle` | `VehiclePolicy` | Staff & Owner/Admin | Owner/Admin only | Denied if assignments or bookings exist |
| `Driver` | `DriverPolicy` | Staff & Owner/Admin | Owner/Admin only | Denied if assignments exist |
| `VehicleDriverAssignment` | `VehicleDriverAssignmentPolicy` | Staff & Owner/Admin | Owner/Admin only | Allowed with audit attribution |
| `VehicleMedia` | `VehicleMediaPolicy` | Staff & Owner/Admin | Owner/Admin only | Unlinks file from disk on deletion |
| `Extra` | `ExtraPolicy` | Staff & Owner/Admin | Owner/Admin only | Soft-disable via `is_active` preferred |

### 9.2 Forensic Audit Trail
All mutations invoke `App\Domain\Audit\Services\AuditService::record()`:
- `fleet.vehicle.created`: Vehicle ID, public name, internal fleet ID.
- `fleet.vehicle.updated`: Attribute diff.
- `fleet.vehicle.status_changed`: Old status, new status, operational reason.
- `fleet.driver.created`: Driver ID, public name, operational phone.
- `fleet.driver.updated`: Attribute diff.
- `fleet.driver.status_changed`: Old status, new status, operational reason.
- `fleet.driver_assignment.created`: Vehicle ID, Driver ID, window `[starts_at, ends_at]`, permanence.
- `fleet.driver_assignment.closed`: Assignment ID, closed timestamp.
- `fleet.media.uploaded`: Vehicle ID, media type, alt text.
- `fleet.media.approved`: Vehicle ID, media ID, promoted path.
- `fleet.extra.created`: Extra ID, name, pricing.
- `fleet.extra.updated`: Attribute diff, compatibility rule changes.

---

## 10. Migration, Rollback, and Test Strategy

### 10.1 Migration Sequence & Rollback
1. `create_vehicles_table`
2. `create_vehicle_media_table`
3. `create_drivers_table`
4. `create_vehicle_driver_assignments_table`
5. `create_extras_table`
6. `create_extra_compatibility_table`

Rollback drops tables in reverse order, dropping foreign key constraints prior to table dropping.

### 10.2 Fictional Factory Data Guarantee
No production fleet, driver, claim, or image facts are seeded. Model factories generate unmistakably fictional data:
- `VehicleFactory`: `public_name = "Test Luxury Van A"`, `internal_fleet_id = "TEST-FLEET-" . fake()->unique()->numerify('###')`, `license_plate = "TEST-" . fake()->unique()->numerify('####')`, `vin = "TESTVIN00000" . fake()->unique()->numerify('####')`.
- `DriverFactory`: `public_name = "Test Driver " . fake()->firstName()`, `full_name = "Test Full Driver Name"`, `phone = "+94770000000"`, `license_number = "TEST-LIC-" . fake()->numerify('#####')`.

### 10.3 Automated Test Suite Matrix
- `tests/Feature/Catalog/VehicleManagementTest.php`:
  - CRUD authorization (`fleet.view` vs `fleet.manage`).
  - Validation of turnaround buffer (30–720 min boundaries).
  - Uniqueness enforcement on `internal_fleet_id` and `license_plate`.
  - Sensitive field `$hidden` verification on serialization.
- `tests/Feature/Catalog/DriverAssignmentOverlapTest.php`:
  - Permanent assignment overlap rejection on same vehicle.
  - Driver cross-vehicle overlap rejection (physical driver exclusivity).
  - Temporary coverage coexistence with permanent driver.
  - Auto-closing prior permanent assignment on new permanent assignment.
  - Effective driver resolution precedence at point-in-time `$at`.
- `tests/Feature/Catalog/ExtraCompatibilityTest.php`:
  - Evaluation across all 4 precedence tiers (exact vehicle, vehicle+service, service-wide, vehicle-wide).
  - Fallback allowlist behavior when compatibility rules exist.
  - Global compatibility when zero rules exist.
- `tests/Feature/Catalog/MediaStorageTest.php`:
  - Unapproved media quarantine on private disk.
  - Promotion to public disk and EXIF stripping on approval.
  - File deletion on media record deletion.
- `tests/Feature/Catalog/CatalogAuditTest.php`:
  - Verification that every vehicle, driver, assignment, and extra mutation records an immutable entry in `audit_events`.

