# Phase 03 Concepts Brief: Catalog, Fleet, and Administration

* **Author:** Lead System Architect
* **Date:** 2026-11-23
* **Target Phase:** Phase 03 (`phase/03-catalog-fleet-admin`)
* **Role:** Lead System Architect (Full Elephant-Goldfish Model Workflow)

---

## 1. Context & Business Domain

Spinellanka is an advance-booking luxury transport platform in Sri Lanka operating around a fleet of exact selectable vehicles. Unlike aggregators or rideshare apps that deal with generic vehicle categories (e.g., "Sedan" or "SUV"), Spinellanka travelers select and book specific, verified vehicles with known specifications, luggage capacities, amenities, and dedicated drivers.

The operational reality requires:
1. **Exact-Vehicle Catalog**: Explicit public specifications (make, model, year, capacity, passenger comfort, transmission, A/C, luggage assumptions) separated from internal identifiers (license plate, registration details, fleet tracking ID).
2. **Service Eligibility**: Not every vehicle is eligible for all 4 distinct services (Airport Pickup, Airport Transfer, Ride, Rent). For example, a luxury van might be chauffeured only (Pickup, Transfer, Ride), while a compact SUV might also be available for self-drive Rent.
3. **Effective-Dated Driver Ties**: Chauffeured services rely on permanent or scheduled drivers. A vehicle has an effective permanent driver, but replacements, vacations, or sick leave require effective-dated assignments without losing historical audit trails or allowing conflicting overlapping permanent assignments.
4. **Extras & Compatibility**: Extras (child seats, booster seats, additional driver coverage, extra luggage racks, meet-and-greet, waiting time) have distinct applicability rules per service and per vehicle type.
5. **Turnaround & Buffer Defaults**: Preparation, cleaning, inspection, and repositioning require explicit turnaround buffer times (e.g., 60-120 minutes) attached to each vehicle to prevent unrealistic back-to-back schedules.
6. **Active & Maintenance Lifecycle**: Vehicles and drivers transition between active, maintenance, inactive, and retired states, directly impacting booking eligibility.
7. **Media & Storage Boundaries**: Public imagery requires verified photography, usage rights, photographer credits, and accessibility alt text. Raw or unapproved media, damage evidence, and driver operational documents must be stored privately and separated from public storage.
8. **No Fabricated Production Facts**: Initial production fleet state is empty until real verified records are entered by authorized staff. Development and automated testing must use unmistakably fictional test factories.

---

## 2. Core Architectural Questions & Trade-Offs

### 2.1 Relational Schema Design vs JSON Document Columns
- **Option A (Relational)**: Distinct relational tables (`vehicles`, `vehicle_media`, `drivers`, `vehicle_driver_assignments`, `extras`, `extra_compatibility`) with foreign keys, compound indexes, and strict constraints.
- **Option B (Hybrid / JSON attributes)**: Storing eligibility, amenities, and specs in a single JSON column on `vehicles`.
- **Verdict**: Option A (Relational) is required by `docs/planning/03-domain-and-data-model.md` and repository standards. Relational tables enable foreign key integrity, indexable availability queries, and strict MySQL/MariaDB schema typing. Service eligibility flags should be boolean columns or relational pivots to allow fast indexed lookups when travelers filter by service type.

### 2.2 Effective-Dated Driver Assignment Overlap Invariants
- A vehicle cannot have more than one active "permanent" driver assignment over any overlapping timeframe `[starts_at, ends_at)`.
- A driver cannot have more than one active permanent vehicle tie simultaneously.
- When `ends_at` is `NULL`, the assignment is open-ended (current permanent tie).
- How should overlap checks be enforced?
  - Database-level unique constraint on `(vehicle_id, ends_at)` only works if `NULL` is replaced by sentinel dates, but open-ended dates are naturally represented by `NULL`.
  - Application Service + Domain Validator inside database transactions with pessimistic row locking (`lockForUpdate`) on the vehicle and driver rows ensures absolute atomicity against concurrent staff assignments.

### 2.3 Public vs Private Field Separation
- Security requirement (`docs/planning/04-security-and-privacy.md`): Keep verified public fields distinct from internal operational fields.
  - Public fields: `public_name`, `make`, `model`, `year`, `vehicle_type`, `passenger_capacity`, `luggage_capacity`, `transmission`, `has_ac`, `amenities`, `eligible_for_pickup`, `eligible_for_transfer`, `eligible_for_ride`, `eligible_for_rent`, `turnaround_buffer_minutes`, `is_active`.
  - Internal fields: `internal_fleet_id`, `license_plate`, `vin_number`, `registration_expiry`, `insurance_expiry`, `internal_notes`, `maintenance_status`.
- This ensures that later public-facing endpoints (Phase 04) can serialize vehicles using explicit API resources without any risk of leaking sensitive license plate or operational tracking metadata.

### 2.4 Media Storage & Access Boundaries
- Public media: Optimized vehicle showcase images uploaded via Filament, stored on the `public` disk under a designated prefix (`vehicles/{id}/media/{hash}.{ext}`), requiring alt text, photographer credit, and copyright confirmation.
- Private media: Internal documents (vehicle registration papers, insurance cards, driver licenses) must be stored on the `private` disk under restricted paths (`private/fleet/{id}/...`), accessible only through signed, authenticated staff routes.

### 2.5 Capability Authorization & Audit Attribution
- Filament staff shell operates under Phase 02 capability permissions:
  - `fleet.view`: Authorized for both `Owner/Admin` and `Staff` roles. Enables read-only browsing of vehicles, media, drivers, assignments, and extras.
  - `fleet.manage`: Authorized for `Owner/Admin` only. Enables creating, editing, updating status, toggling maintenance, and modifying driver assignments or extras.
- Every state mutation (creation, edit, driver assignment, maintenance toggle, extra creation/edit) must trigger an immutable audit event via `App\Services\AuditService` recording the actor, action (`fleet.vehicle.create`, `fleet.driver_assignment.create`, etc.), before/after state diff, and client IP/user-agent.

---

## 3. High-Level Entity Relationships

```
+------------------+         +-------------------------------+         +------------------+
|     vehicles     | 1 --- * |  vehicle_driver_assignments   | * --- 1 |     drivers      |
+------------------+         +-------------------------------+         +------------------+
        | 1
        |
        +--- * +------------------+
               |  vehicle_media   |
               +------------------+

+------------------+         +-------------------------------+
|      extras      | 1 --- * |      extra_compatibility      | (scoped to service_type
+------------------+         +-------------------------------+  and/or vehicle_type)
```

---

## 4. Scope & Guardrails for Phase 03

### In Scope
1. Database migrations for `vehicles`, `vehicle_media`, `drivers`, `vehicle_driver_assignments`, `extras`, and `extra_compatibility`.
2. Eloquent models with strict typing, relationships, query scopes, and attribute casting.
3. Domain services:
   - `VehicleService`: Catalog creation, specification updates, maintenance toggling, public vs internal attribute boundaries.
   - `DriverAssignmentService`: Atomic effective-dated assignment management, overlap conflict prevention, and history preservation.
   - `ExtraService`: Extra creation, compatibility resolution against vehicle and service types.
4. Filament 5 Staff Resources:
   - `VehicleResource`: Table, view, create/edit forms with separate public vs internal tabs/sections, media upload relation manager.
   - `DriverResource`: Table, view, create/edit forms, active status toggles.
   - `VehicleDriverAssignmentResource` (or integrated RelationManager on Vehicle/Driver): Effective dating, start/end dates, permanent flag, conflict validation.
   - `ExtraResource`: Catalog of extras, pricing minor units (USD), service applicability checkboxes, compatibility rules.
5. Authorization Policies:
   - `VehiclePolicy`, `DriverPolicy`, `VehicleDriverAssignmentPolicy`, `ExtraPolicy` enforcing `fleet.view` and `fleet.manage`.
6. Audit Logging:
   - Detailed event attribution on every create/update/assignment change.
7. Development & Testing:
   - Model factories with fictional data (e.g. "Toyota Alphard", "Nissan Caravan" with fake test VINs).
   - Empty production seeders (zero fake fleet in production).
   - Test suite covering CRUD authorization, overlap prevention, migration rollback, media validation.

### Explicit Non-Scope
- No booking creation or checkout holds (Phase 06).
- No schedule availability block calculation or calendar matrices (Phase 05).
- No dynamic route pricing or distance calculation (Phase 07).
- No public guest/traveler web views or public APIs (Phase 04).
- No external provider integrations (maps, payment, SMS/WhatsApp).

