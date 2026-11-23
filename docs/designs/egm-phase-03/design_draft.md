# Architecture Design Draft: Phase 03 — Catalog, Fleet, and Administration

* **Author:** Lead System Architect
* **Date:** 2026-11-23
* **Target Release:** Phase 03 (`phase/03-catalog-fleet-admin`)
* **Review Protocol:** Full Elephant-Goldfish Model (EGM) Workflow

---

## 1. Executive Summary & Architectural Goals

Spinellanka operates an advance-booking luxury transport platform in Sri Lanka centered on an exact five-vehicle fleet. Travelers do not book a generic class of car; they select an exact, verified vehicle with authentic photos, confirmed seating and luggage capacities, verified amenities, and dedicated drivers.

Phase 03 establishes the foundational **Catalog Module** within the Spinellanka modular monolith. It provides:
1. Exact-vehicle catalog with verified public specifications strictly separated from internal operational tracking identifiers.
2. Verified media metadata with explicit storage boundaries (public showcase vs private operational assets) and accessibility compliance.
3. Driver profiles with verified operational information, public trust credentials, and active status tracking.
4. Effective-dated vehicle-driver assignments with concurrency locking and overlap conflict prevention.
5. Service eligibility matrix (Airport Pickup, Airport Transfer, Ride, Rent) and turnaround buffer defaults per vehicle.
6. Extras and service/vehicle compatibility matrix with integer minor-unit pricing.
7. Thin Filament 5 resources for administrative operations protected by granular capability permissions (`fleet.view`, `fleet.manage`) and full audit trail attribution.
8. Unmistakably fictional test factories and zero production fleet data fabrication.

---

## 2. Domain Model & Relational Schema

### 2.1 Entity Relationship Diagram

```
+-----------------------------------+
|             vehicles              |
+-----------------------------------+
| id: bigint unsigned (PK)          |
| public_name: varchar(255)         |
| internal_fleet_id: varchar(50) UI |
| make: varchar(100)                |
| model: varchar(100)               |
| year: smallint unsigned           |
| license_plate: varchar(50)        |
| vin: varchar(100) nullable        |
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
| disk: varchar(50)    |   | starts_at: datetime                |
| media_type: varchar  |   | ends_at: datetime nullable         |
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
| pricing_type: varchar(30)         |         | is_compatible: boolean             |
| max_quantity: tinyint unsigned    |         | timestamps                         |
| is_active: boolean                |         +------------------------------------+
| timestamps                        |
+-----------------------------------+
```

---

## 3. Detailed Table Specifications & Constraints

### 3.1 Table: `vehicles`
- `id`: `BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY`
- `public_name`: `VARCHAR(255) NOT NULL` — The client-facing approved display title (e.g. "Toyota Alphard Executive Lounge 2024").
- `internal_fleet_id`: `VARCHAR(50) NOT NULL UNIQUE` — Internal tracking identifier (e.g. "FLEET-001").
- `make`: `VARCHAR(100) NOT NULL` (e.g. "Toyota").
- `model`: `VARCHAR(100) NOT NULL` (e.g. "Alphard").
- `year`: `SMALLINT UNSIGNED NOT NULL` (e.g. 2024).
- `license_plate`: `VARCHAR(50) NOT NULL` — Internal operational vehicle registration. **Never exposed to public endpoints**.
- `vin`: `VARCHAR(100) NULL` — Internal vehicle identification number.
- `vehicle_type`: `VARCHAR(50) NOT NULL` — Backed enum `VehicleType` (`luxury_van`, `sedan`, `suv`, `minivan`, `compact`).
- `passenger_capacity`: `TINYINT UNSIGNED NOT NULL` (e.g. 5).
- `luggage_capacity`: `TINYINT UNSIGNED NOT NULL` (e.g. 4 standard suitcases).
- `transmission`: `VARCHAR(20) NOT NULL` — Backed enum `TransmissionType` (`automatic`, `manual`).
- `has_ac`: `BOOLEAN NOT NULL DEFAULT TRUE`.
- `accessibility_features`: `JSON NULL` — Array of verified physical accessibility features (e.g. `["wheelchair_ramp", "low_step"]`).
- `amenities`: `JSON NULL` — Array of amenities (e.g. `["wifi", "leather_seats", "rear_screens", "bottled_water"]`).
- `eligible_for_pickup`: `BOOLEAN NOT NULL DEFAULT FALSE` — Airport Pickup eligible.
- `eligible_for_transfer`: `BOOLEAN NOT NULL DEFAULT FALSE` — Airport Transfer eligible.
- `eligible_for_ride`: `BOOLEAN NOT NULL DEFAULT FALSE` — Multi-stop Ride eligible.
- `eligible_for_rent`: `BOOLEAN NOT NULL DEFAULT FALSE` — Self-drive Rent eligible.
- `turnaround_buffer_minutes`: `SMALLINT UNSIGNED NOT NULL DEFAULT 90` — Turnaround buffer in minutes for cleaning, inspection, and repositioning.
- `status`: `VARCHAR(30) NOT NULL DEFAULT 'active'` — Backed enum `VehicleStatus` (`active`, `maintenance`, `retired`).
- `internal_notes`: `TEXT NULL` — Operational notes, maintenance history, private restrictions.
- `created_at`, `updated_at`: `TIMESTAMP NULL`.
- **Indexes:**
  - `INDEX idx_vehicles_status (status)`
  - `INDEX idx_vehicles_type_status (vehicle_type, status)`
  - `INDEX idx_vehicles_eligibility (status, eligible_for_pickup, eligible_for_transfer, eligible_for_ride, eligible_for_rent)`

### 3.2 Table: `vehicle_media`
- `id`: `BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY`
- `vehicle_id`: `BIGINT UNSIGNED NOT NULL`, `FOREIGN KEY REFERENCES vehicles(id) ON DELETE CASCADE`
- `file_path`: `VARCHAR(255) NOT NULL` — Relative path on the storage disk.
- `disk`: `VARCHAR(50) NOT NULL DEFAULT 'public'`
- `media_type`: `VARCHAR(50) NOT NULL` — Backed enum `MediaType` (`exterior_front`, `exterior_rear`, `cabin`, `luggage`, `dashboard`, `feature`)
- `alt_text`: `VARCHAR(255) NOT NULL` — Factual, accessible descriptive text.
- `photographer`: `VARCHAR(255) NULL` — Attribution/photographer name.
- `usage_rights`: `VARCHAR(255) NULL` — License or ownership documentation reference.
- `sort_order`: `SMALLINT UNSIGNED NOT NULL DEFAULT 0`
- `is_approved`: `BOOLEAN NOT NULL DEFAULT FALSE` — Must be approved by staff before appearing publicly.
- `created_at`, `updated_at`: `TIMESTAMP NULL`
- **Indexes:**
  - `INDEX idx_vehicle_media_sort (vehicle_id, is_approved, sort_order)`

### 3.3 Table: `drivers`
- `id`: `BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY`
- `public_name`: `VARCHAR(255) NOT NULL` — First name and initial (e.g. "Sunil P.") for public reassurance.
- `full_name`: `VARCHAR(255) NOT NULL` — Full legal name for operational dispatch and identity records.
- `phone`: `VARCHAR(50) NOT NULL` — Primary operational mobile number.
- `whatsapp`: `VARCHAR(50) NULL` — Operational WhatsApp contact.
- `portrait_path`: `VARCHAR(255) NULL` — Path to verified portrait image.
- `public_trust_facts`: `JSON NULL` — Factual trust markers (e.g. `["12 years chauffeuring experience", "English and Sinhala fluent", "SLTDA Tourist Driver Certified"]`).
- `license_number`: `VARCHAR(100) NULL` — Internal operational driver license number.
- `license_expiry`: `DATE NULL` — Internal operational license expiration date.
- `status`: `VARCHAR(30) NOT NULL DEFAULT 'active'` — Backed enum `DriverStatus` (`active`, `inactive`, `on_leave`).
- `internal_notes`: `TEXT NULL`
- `created_at`, `updated_at`: `TIMESTAMP NULL`
- **Indexes:**
  - `INDEX idx_drivers_status (status)`

### 3.4 Table: `vehicle_driver_assignments`
- `id`: `BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY`
- `vehicle_id`: `BIGINT UNSIGNED NOT NULL`, `FOREIGN KEY REFERENCES vehicles(id) ON DELETE RESTRICT`
- `driver_id`: `BIGINT UNSIGNED NOT NULL`, `FOREIGN KEY REFERENCES drivers(id) ON DELETE RESTRICT`
- `starts_at`: `DATETIME NOT NULL` — Invariant schedule timestamp.
- `ends_at`: `DATETIME NULL` — `NULL` indicates open-ended permanent or ongoing assignment.
- `is_permanent`: `BOOLEAN NOT NULL DEFAULT FALSE` — Identifies default tied driver for chauffeured services.
- `reason`: `VARCHAR(255) NULL` — Operational reason (e.g. "Primary tied driver", "Covering vacation 2026-12-01 to 2026-12-07").
- `assigned_by_user_id`: `BIGINT UNSIGNED NULL`, `FOREIGN KEY REFERENCES users(id) ON DELETE SET NULL`
- `created_at`, `updated_at`: `TIMESTAMP NULL`
- **Indexes:**
  - `INDEX idx_vda_vehicle_window (vehicle_id, starts_at, ends_at)`
  - `INDEX idx_vda_driver_window (driver_id, starts_at, ends_at)`
  - `INDEX idx_vda_permanent (vehicle_id, is_permanent, ends_at)`

### 3.5 Table: `extras`
- `id`: `BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY`
- `name`: `VARCHAR(255) NOT NULL` (e.g. "Child Safety Seat", "Booster Seat", "Additional Rent Driver")
- `slug`: `VARCHAR(100) NOT NULL UNIQUE` (e.g. "child-safety-seat")
- `description`: `TEXT NULL`
- `price_minor_units`: `INT UNSIGNED NOT NULL DEFAULT 0` — Stored as integer cents (e.g., $15.00 is stored as `1500`).
- `pricing_type`: `VARCHAR(30) NOT NULL DEFAULT 'per_booking'` — Backed enum `ExtraPricingType` (`per_booking`, `per_day`).
- `max_quantity`: `TINYINT UNSIGNED NOT NULL DEFAULT 1`
- `is_active`: `BOOLEAN NOT NULL DEFAULT TRUE`
- `created_at`, `updated_at`: `TIMESTAMP NULL`
- **Indexes:**
  - `INDEX idx_extras_active (is_active)`

### 3.6 Table: `extra_compatibility`
- `id`: `BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY`
- `extra_id`: `BIGINT UNSIGNED NOT NULL`, `FOREIGN KEY REFERENCES extras(id) ON DELETE CASCADE`
- `service_type`: `VARCHAR(50) NULL` — Backed enum `ServiceType` (`airport_pickup`, `airport_transfer`, `ride`, `rent`) or NULL for all services.
- `vehicle_type`: `VARCHAR(50) NULL` — Backed enum `VehicleType` or NULL for all vehicle types.
- `vehicle_id`: `BIGINT UNSIGNED NULL`, `FOREIGN KEY REFERENCES vehicles(id) ON DELETE CASCADE` — Specific vehicle override if applicable.
- `is_compatible`: `BOOLEAN NOT NULL DEFAULT TRUE`
- `created_at`, `updated_at`: `TIMESTAMP NULL`
- **Indexes:**
  - `INDEX idx_extra_compat_lookup (extra_id, service_type, vehicle_type)`

---

## 4. Invariants, Business Rules, and Service Architecture

### 4.1 Effective-Dated Driver Assignment Overlap Invariants
1. **Vehicle-Driver Permanent Exclusivity**:
   - A vehicle can have at most one active permanent driver assignment at any point in time.
   - A driver can be the permanent driver of at most one vehicle at any point in time.
2. **Date Interval Overlap Check**:
   - Two intervals `[A_start, A_end)` and `[B_start, B_end)` overlap if and only if:
     `A_start < (B_end ?? '9999-12-31 23:59:59') AND (A_end ?? '9999-12-31 23:59:59') > B_start`.
   - If a new assignment is permanent, it MUST NOT overlap with any existing permanent assignment for the same vehicle or driver.
3. **Concurrency Locking via Pessimistic Row Locks**:
   - The assignment service must execute inside a `DB::transaction()` that acquires pessimistic row locks (`lockForUpdate()`) on the target `Vehicle` and `Driver` records:
   ```php
   return DB::transaction(function () use ($data) {
       $vehicle = Vehicle::where('id', $data['vehicle_id'])->lockForUpdate()->firstOrFail();
       $driver = Driver::where('id', $data['driver_id'])->lockForUpdate()->firstOrFail();
       // Validate overlap against existing assignments
       ...
       return VehicleDriverAssignment::create($data);
   });
   ```

### 4.2 Turnaround Buffer Invariant
- Every vehicle must define a `turnaround_buffer_minutes` integer.
- Lower bound: minimum 30 minutes (preventing instantaneous re-hire).
- Upper bound: maximum 720 minutes (12 hours). Default: 90 minutes.

### 4.3 Public vs Internal Field Boundary & Serialization
- Public endpoints (in Phase 04 and beyond) must NEVER expose:
  - `license_plate`, `vin`, `internal_fleet_id`, `internal_notes` of vehicles.
  - `full_name`, `phone`, `license_number`, `license_expiry`, `internal_notes` of drivers.
- Public data is strictly scoped through explicit API/Eloquent resources or View Models.

### 4.4 Media Upload & Validation Boundaries
- Allowed MIME types: `image/jpeg`, `image/png`, `image/webp`.
- Maximum file size: 5MB.
- File storage: Stored with cryptographically random filenames under `vehicles/{vehicle_id}/media/{sha256}.{ext}` on the configured public disk.
- Required fields on media record: `alt_text` (accessibility requirement) and `media_type`.
- Approval gate: Newly uploaded images have `is_approved = false` by default until verified.

---

## 5. Filament 5 Administrative Resources

### 5.1 `VehicleResource`
- **Location:** `app/Filament/Resources/Catalog/VehicleResource.php`
- **Navigation:** Grouped under `"Catalog & Fleet"`, icon `heroicon-o-truck`.
- **Authorization:** `fleet.view` for navigation visibility and viewAny/view; `fleet.manage` for create, edit, delete.
- **Form Structure (Tabs):**
  1. *Public Specifications*: `public_name`, `make`, `model`, `year`, `vehicle_type`, `passenger_capacity`, `luggage_capacity`, `transmission`, `has_ac`, `amenities`, `accessibility_features`.
  2. *Service Eligibility*: Checkboxes for `eligible_for_pickup`, `eligible_for_transfer`, `eligible_for_ride`, `eligible_for_rent`.
  3. *Operational & Turnaround*: `turnaround_buffer_minutes`, `status` (Active / Maintenance / Retired).
  4. *Internal Details*: `internal_fleet_id`, `license_plate`, `vin`, `internal_notes`.
- **Relation Managers:**
  - `MediaRelationManager`: Manage showcase photography, reordering, alt-text, and approval toggles.
  - `DriverAssignmentsRelationManager`: View effective-dated assignment history and schedule driver ties.
- **Table Columns:**
  - `public_name` (searchable, sortable)
  - `internal_fleet_id` (searchable, badge)
  - `vehicle_type` (badge)
  - `passenger_capacity` / `luggage_capacity`
  - Service Badges: Visual indicator for Pickup, Transfer, Ride, Rent eligibility.
  - `turnaround_buffer_minutes`
  - `status` (Color badges: active=green, maintenance=amber, retired=gray)

### 5.2 `DriverResource`
- **Location:** `app/Filament/Resources/Catalog/DriverResource.php`
- **Navigation:** Grouped under `"Catalog & Fleet"`, icon `heroicon-o-user-group`.
- **Form Structure:**
  1. *Public Profile*: `public_name`, `portrait_path`, `public_trust_facts` (Tags / Key-Value list).
  2. *Operational Contact*: `full_name`, `phone`, `whatsapp`.
  3. *Compliance & Internal*: `license_number`, `license_expiry`, `status`, `internal_notes`.
- **Table Columns:**
  - `public_name` (searchable)
  - `phone` (searchable)
  - `current_vehicle` (shows currently assigned vehicle)
  - `status` (active / inactive / on_leave)

### 5.3 `ExtraResource`
- **Location:** `app/Filament/Resources/Catalog/ExtraResource.php`
- **Navigation:** Grouped under `"Catalog & Fleet"`, icon `heroicon-o-puzzle-piece`.
- **Form Structure:**
  - `name`, `slug`, `description`.
  - `price_minor_units` (Formatted in USD, stored as cents).
  - `pricing_type` (per_booking / per_day).
  - `max_quantity`, `is_active`.
  - *Compatibility Pivot/Repeater*: Configure service type and vehicle type compatibility.
- **Table Columns:**
  - `name`, `slug`, `price` ($ USD), `pricing_type`, `is_active`.

---

## 6. Security, Policies, and Audit Integration

### 6.1 Policy Matrix (`App\Policies\Catalog\`)

| Resource | Policy Method | Required Permission | Staff Role | Owner/Admin Role |
|:---|:---|:---|:---:|:---:|
| `Vehicle` | `viewAny`, `view` | `fleet.view` | **ALLOWED** | **ALLOWED** |
| `Vehicle` | `create`, `update`, `delete` | `fleet.manage` | **DENIED** | **ALLOWED** |
| `Driver` | `viewAny`, `view` | `fleet.view` | **ALLOWED** | **ALLOWED** |
| `Driver` | `create`, `update`, `delete` | `fleet.manage` | **DENIED** | **ALLOWED** |
| `VehicleDriverAssignment` | `viewAny`, `view` | `fleet.view` | **ALLOWED** | **ALLOWED** |
| `VehicleDriverAssignment` | `create`, `update`, `delete` | `fleet.manage` | **DENIED** | **ALLOWED** |
| `Extra` | `viewAny`, `view` | `fleet.view` | **ALLOWED** | **ALLOWED** |
| `Extra` | `create`, `update`, `delete` | `fleet.manage` | **DENIED** | **ALLOWED** |

### 6.2 Audit Event Logging
All mutations must call `App\Services\AuditService::log()`:
- `fleet.vehicle.created`: Vehicle ID, public name, internal fleet ID.
- `fleet.vehicle.updated`: Diff of changed attributes.
- `fleet.vehicle.status_changed`: Previous status, new status, reason.
- `fleet.driver.created`: Driver ID, public name, operational phone.
- `fleet.driver.updated`: Diff of changed operational or trust attributes.
- `fleet.driver_assignment.created`: Vehicle ID, Driver ID, window `[starts_at, ends_at]`, permanent flag.
- `fleet.driver_assignment.closed`: Assignment ID, closed `ends_at`.
- `fleet.extra.created`: Extra ID, name, pricing.
- `fleet.extra.updated`: Diff of extra attributes or compatibility rules.

---

## 7. Migration, Rollback, and Test Strategy

### 7.1 Migration Order
1. `create_vehicles_table`
2. `create_vehicle_media_table`
3. `create_drivers_table`
4. `create_vehicle_driver_assignments_table`
5. `create_extras_table`
6. `create_extra_compatibility_table`

### 7.2 Rollback Safety
- Each migration contains an exact, symmetrical `down()` method dropping child foreign keys before dropping tables.
- Migrations do not alter or delete existing Phase 01/02 tables (`users`, `roles`, `permissions`, `audit_events`).
- Rollback can be executed without leaving orphaned constraints or schema drift.

### 7.3 Testing Strategy
1. **Clean & Upgrade Migrations:** Test clean migrate and migrate:rollback.
2. **Model & Relation Integrity:** Test foreign key cascades, cascade deletions of media on vehicle deletion, restrict on delete for assigned vehicles/drivers.
3. **Effective-Dated Overlap Prevention:**
   - Test that two permanent assignments for the same vehicle cannot overlap.
   - Test that two permanent assignments for the same driver cannot overlap.
   - Test that non-overlapping assignments succeed.
   - Test that temporary non-permanent assignments follow business rules.
4. **Service Eligibility & Compatibility:**
   - Test filtering vehicles by service eligibility flags.
   - Test extra compatibility query resolution.
5. **Authorization Tests:**
   - Verify Staff can view but cannot create/update/delete vehicles, drivers, or extras.
   - Verify Owner/Admin can perform full CRUD.
   - Verify unauthenticated users are redirected to login.
6. **Audit Verification:**
   - Verify that every vehicle, driver, assignment, and extra mutation writes an immutable record to `audit_events`.
7. **Production Fleet Fact Guarantee:**
   - Verify that database seeders run in production create NO fictional fleet, driver, or extra records.
   - Model factories use obvious test placeholders (e.g., "Test Vehicle A", "VIN-TEST-9999").

