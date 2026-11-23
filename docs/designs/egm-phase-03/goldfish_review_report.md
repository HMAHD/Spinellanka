# Goldfish Architecture Review Report: Phase 03 — Catalog, Fleet, and Administration

* **Compiler & Synthesizer:** Lead System Architect
* **Date:** 2026-11-23
* **Target Document:** [design_draft.md](file:///Users/akash/Documents/Spinellanka/docs/designs/egm-phase-03/design_draft.md)
* **Reviewers:**
  1. **Hostile Design Critic** (`egm-design`) — Focus: Concurrency, data integrity, storage boundaries, relational invariants.
  2. **Pragmatic Tech Lead** (`egm-implementation-readiness`) — Focus: Implementation completeness, service interfaces, schemas, Filament 5 integration.
  3. **Technical Quality Auditor** (`egm-codebase-details`) — Focus: Codebase compatibility, `Model::shouldBeStrict()`, audit contracts, Filament 5 APIs.

---

## Executive Summary of Findings

All three Goldfish reviewers conducted independent audits of `design_draft.md`. While the high-level architecture and scope match the product roadmap, the reviewers identified **critical vulnerabilities, runtime blockers, and integration gaps** that must be resolved prior to implementation:

1. **Deadlock & Snapshot Anomalies in Concurrency Locking**: Inconsistent lock ordering between vehicles and drivers risks MariaDB InnoDB deadlocks; snapshot reads in MariaDB `REPEATABLE READ` allow phantom overlap inserts unless child assignment rows are locked directly (`lockForUpdate()`).
2. **Broken Overlap Invariant Logic**: The draft allowed drivers to be given overlapping temporary assignments across multiple vehicles (violating physical exclusivity) and lacked clear precedence between permanent and temporary cover assignments.
3. **Broken Audit Service Contract**: The draft referenced a non-existent `App\Services\AuditService::log()`. The repository's actual service is `App\Domain\Audit\Services\AuditService::record()`, which expects polymorphic auditable models and structured metadata.
4. **Missing Model Defaults & `Model::shouldBeStrict()` Crashes**: Under strict mode, instantiating new models without explicit `$attributes` causes runtime `MissingAttributeException`.
5. **Filament 5 API Compatibility**: Form schemas must use `Schema $schema` and `->components()`, tables must use `->paginationMode(PaginationMode::Simple)`, and actions must use `->recordActions()` and `->toolbarActions()`. Furthermore, relation managers must override `CreateAction` using `using()` to prevent bypassing `DriverAssignmentService`.
6. **Storage Security & EXIF Leaks**: Storing unapproved media directly on the public disk allows public static URL access, bypassing approval. EXIF metadata must be stripped.
7. **Schema & Compatibility Gaps**: Table `extras` lacks an ISO currency column (`currency: VARCHAR(3) NOT NULL DEFAULT 'USD'`). Table `extra_compatibility` lacks compound uniqueness and a deterministic 4-tier resolution hierarchy. MariaDB DDL `CHECK` constraints are missing for turnaround buffers, capacities, and date intervals. Models lack `$hidden` attributes for sensitive identifiers (license plates, VINs, driver phones).

---

## Consolidated Findings Matrix

| Finding ID | Severity | Category | Source | Summary & Resolution |
|:---|:---:|:---|:---:|:---|
| **F-01** | **P0** | Concurrency | Design Critic | **Lock ordering & child table locking**: Prevent AB-BA deadlocks by sorting entity IDs deterministically; prevent snapshot phantom reads by querying `vehicle_driver_assignments` with `lockForUpdate()`. |
| **F-02** | **P0** | Domain Invariants | Design Critic / Tech Lead | **Universal physical driver exclusivity**: A driver cannot have overlapping assignments across any vehicles (permanent or temporary). Temporary replacement takes precedence over permanent base driver during its active window. |
| **F-03** | **P0** | Runtime Blocker | Tech Lead / Auditor | **Audit service contract correction**: Replace `App\Services\AuditService::log()` with `App\Domain\Audit\Services\AuditService::record()` with proper polymorphic auditable parameters. |
| **F-04** | **P0** | Domain Services | Tech Lead | **Explicit domain service interfaces**: Specify complete PHP class interfaces, method signatures, exceptions (`DriverAssignmentConflictException`), and return types for `DriverAssignmentService`, `VehicleService`, and `ExtraService`. |
| **F-05** | **P0** | Filament 5 Bypass | Tech Lead | **Filament RelationManager delegation**: Override `CreateAction::make()->using(...)` in `DriverAssignmentsRelationManager` to route assignment mutations through `DriverAssignmentService`. |
| **F-06** | **P0** | Runtime Blocker | Auditor | **Strict model attribute defaults**: Define explicit `$attributes` and `$casts` on all 6 catalog models to prevent `MissingAttributeException` under `Model::shouldBeStrict()`. |
| **F-07** | **P0** | Security / Storage | Design Critic | **Quarantine storage & EXIF stripping**: Keep unapproved media on private quarantine disk; promote to public disk only upon approval; strip EXIF geolocation metadata; specify driver portrait storage. |
| **F-08** | **P1** | Data Model | Design Critic / Tech Lead | **Extras ISO currency column**: Add `currency: VARCHAR(3) NOT NULL DEFAULT 'USD'` with a check constraint to table `extras`. |
| **F-09** | **P1** | Data Model | Design Critic / Tech Lead | **Extra compatibility uniqueness & resolution**: Add virtual column or unique index on `extra_compatibility` and codify the 4-tier precedence hierarchy with allowlist fallback. |
| **F-10** | **P1** | Authorization | Design Critic / Auditor | **`VehicleMediaPolicy` & policy discovery**: Add `VehicleMediaPolicy` to authorization matrix; place all policies in `app/Policies/` for standard Laravel auto-discovery. |
| **F-11** | **P1** | Schema Constraints | Design Critic / Tech Lead | **MariaDB DDL check constraints**: Add database-level `CHECK` constraints for turnaround buffers (30–720 min), capacities (1–50), and date intervals (`ends_at IS NULL OR ends_at > starts_at`). |
| **F-12** | **P1** | Information Disclosure | Design Critic | **Model `$hidden` attributes**: Mark `license_plate`, `vin`, `internal_fleet_id`, `internal_notes`, `driver.phone`, and `driver.license_number` as `$hidden` on models. |
| **F-13** | **P1** | Filament 5 APIs | Auditor | **Filament 5 signature compliance**: Standardize on `Schema $schema`, `components()`, `paginationMode(PaginationMode::Simple)`, `recordActions()`, and typed `Heroicon` enums. |
| **F-14** | **P1** | Schema Completeness | Tech Lead | **Missing columns from Concepts Brief**: Add `registration_expiry: DATE NULL`, `insurance_expiry: DATE NULL`, and unique constraint on `license_plate` to `vehicles`. |
| **F-15** | **P1** | Timezone Handling | Auditor / Design Critic | **`Asia/Colombo` schedule timezone normalization**: Normalize Carbon inputs to `Asia/Colombo` wall-clock time before querying or inserting assignment `DATETIME` columns. |
| **F-16** | **P2** | Lifecycle | Design Critic / Tech Lead | **Retirement / deactivation rules**: Forbid soft deletes; auto-close or block open-ended assignments when a vehicle is retired or driver deactivated; add `fleet.driver.status_changed` audit event. |
| **F-17** | **P2** | Directory Structure | Auditor | **Pluralized Filament namespaces**: Organize resources into `app/Filament/Resources/Catalog/Vehicles/`, `Drivers/`, and `Extras/`. |
| **F-18** | **P2** | Testing / Factories | Tech Lead | **Concrete test matrix & fictional factories**: Enumerate Pest/PHPUnit test files and provide explicit fictional factory attribute definitions (`TEST-FLEET-###`, etc.). |

---

## Resolution Strategy for `docs/designs/phase-03-catalog-fleet-admin.md`

All 18 findings will be comprehensively resolved in the final resolved architecture document:
1. **Section 2 & 3 (Data Model & Schema)**: Incorporate missing columns (`currency`, `registration_expiry`, `insurance_expiry`), unique constraints, MariaDB `CHECK` constraints, `$attributes`, `$casts`, and `$hidden` arrays.
2. **Section 4 (Domain Services & Invariants)**: Provide complete PHP class signatures for `DriverAssignmentService`, `VehicleService`, and `ExtraService`, with deterministic lock ordering, child table locking reads, universal driver exclusivity, and the 4-tier extra compatibility algorithm.
3. **Section 5 (Storage & Media)**: Detail the quarantine-to-public promotion workflow, EXIF metadata stripping, and driver portrait lifecycle.
4. **Section 6 (Filament 5 Staff Resources)**: Provide full Filament 5 resource specifications, pluralized namespaces, typed `Heroicon` enums, `using()` delegation in relation managers, and USD-to-cents field dehydration.
5. **Section 7 (Security, Authorization & Audit)**: Map canonical permissions (`fleet.view`, `fleet.manage`), place policies in `app/Policies/`, include `VehicleMediaPolicy`, and provide exact `AuditService::record()` invocation contracts.
6. **Section 8 (Migration, Rollback & Test Plan)**: Document the migration sequence, rollback steps, concrete test suite matrix, and fictional factory generators.

