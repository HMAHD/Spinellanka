# Phase 16: Client Content Reconciliation, Configuration Transfer, and Safe Provider Activation
## Concepts Brief (EGM Phase 16 Brainstorm)

**Date:** 2026-11-27  
**Author:** Lead Release Integrator  
**Branch:** `phase/16-client-provider-activation`  
**Base:** `main` (`ff6d12e`, including merged Phase 15 PR #18)  
**Authority Context:** `CLAUDE.md`, `PRODUCT.md`, `DESIGN.md`, `roadmap.md` (§1–11, Phase 16), `docs/ux/07-client-input-checklist.md`, `docs/ux/08-acceptance-and-signoff.md` (`CHG-003` pending recording)

---

### 1. Problem Statement & Phase 16 Role

Phases 00–15 built the Spinellanka modular monolith, database schema, booking verticals, availability locking, pricing engine, outbox notifications, versioned proposals, hosted payments, operations calendar/driver trip links, Rent field handovers, incidents, and privacy retention. Phase 16 is **final reconciliation, reproducible configuration transfer, and provider/analytics activation**—not the first invention of fleet/rate/service rules.

Two real-world constraints govern this branch:
1. **Unverified client/provider inputs remain absent in `.env` and `docs/ux/07-client-input-checklist.md`**: Client-approved 5-vehicle data, real consented vehicle photography, live rates, legal/privacy copy, and sandbox credentials (`MAPS_API_KEY`, `META_WHATSAPP_*`, `POSTMARK_*`, `PAYHERE_*`, `SENTRY_LARAVEL_DSN`, off-host S3 backup target, `PLAUSIBLE_DOMAIN`) have not yet been supplied.
2. **Formal Staging Exception (`CHG-003`)**: The Project Lead authorized recording `CHG-003` in `docs/ux/08-acceptance-and-signoff.md` to permit **explicitly labeled staging-only synthetic fixtures** (including synthetic placeholder media marked non-production) so configuration export/import/rollback, approved calculation verification, and all four end-to-end staging journeys can run deterministically, while **production configuration export and public production promotion remain strictly blocked** until client-approved inputs replace synthetic fixtures.

---

### 2. Core Architectural Pillars

1. **Launch-Input Matrix & Staging-Data Inventory**
   - Every business value, asset, policy, and provider setting maps to: `Owner`, `Source`, `Status` (`Not started` / `Draft` / `Verified` / `Approved` / `Synthetic Staging Only`), `Approver & Date`, `Version`, `Conditions`, and `Effective Date`.
   - Staging data is partitioned into three strict classes:
     - **Class A — Approved Public/Business Configuration**: Only items marked `Verified`/`Approved` in `07-client-input-checklist.md` (Sections 6, 7, 8, primary USD currency).
     - **Class B — Synthetic Staging Fixtures (`CHG-003`)**: Explicitly tagged synthetic 5-vehicle catalog, pricing rules, exchange rate basis, extras, travelers, bookings, payments, and incidents. Blocked from `production_approved` configuration export.
     - **Class C — Excluded Private Driver Operational PII & Secrets**: Driver `full_name`, `phone`, `whatsapp`, `license_number`, `license_expiry`, `internal_notes`, plus all API tokens, secrets, user passwords, MFA secrets, and traveler PII. Strictly excluded from configuration packages and rejected on leakage.

2. **Versioned, Idempotent, Auditable Configuration Transfer & Rollback (`App\Domain\Configuration`)**
   - **No direct DB writes**: All imports and rollbacks execute through existing domain services (`VehicleService`, `ExtraService`, `PricingRuleService`, `ExchangeRateService`, `RentPolicy` manager) inside serializable transactions with `AuditService::record()`.
   - **Package Envelope**: `schema_version` (`1.0`), `package_classification` (`production_approved` | `staging_synthetic`), `source_revision`, `exported_at`, `exported_by`, `record_counts`, `checksum_sha256` (over canonical sorted JSON of `payload` + `record_counts` + `schema_version` + `package_classification`), and `payload`.
   - **Strict Validation & Guardrails**:
     - Reject schema version mismatch, corrupt JSON, missing required sections, checksum mismatch, or `production_approved` packages containing `SYNTHETIC` markers or unapproved media without rights/alt-text.
     - Reject any package containing forbidden secret/PII keys (`phone`, `whatsapp`, `email`, `full_name`, `license_number`, `license_expiry`, `password`, `secret`, `token`, `api_key`, `dsn`, etc.).
     - Enforce exact 5-vehicle invariant (`count(vehicles) === 5`, unique `internal_fleet_id` and `license_plate`, no duplicates).
     - Support **dry-run validation & drift detection** (returns exact `added`, `updated`, `unchanged`, `orphaned` diff without persisting changes).
     - Persist immutable `configuration_snapshots` so any import can be **rolled back** to the prior approved/active configuration version with full actor/timestamp/reason audit trail.

3. **Provider Sandbox Adapters & Safe Degradation**
   - **Google Maps Platform (`GoogleMapsPlaceSearchService`, `GoogleMapsRouteEstimateService`)**: Bound to `PlaceSearchContract` / `RouteEstimateContract` when `services.maps.provider === 'google_maps'` and `services.maps.key` is configured; safely falls back to `NullPlaceSearchService` / `NullRouteEstimateService` when unset or on API/quota/timeout failure. Enforces `regionCode=LK`, `Referer` origin restriction header if configured, bounded timeouts (3s connect / 8s total), and zero customer PII transmission.
   - **Meta WhatsApp Cloud API (`MetaWhatsAppSender`)**: Verified template/sender readiness gate; defaults safely to `FakeWhatsAppSender` when credentials are absent.
   - **Postmark, PayHere, Error Reporting (`ErrorReporter`), Off-Host Backups (`OffHostBackupContract`)**: Postmark & PayHere remain in contract-tested `fake` mode. Add provider-neutral `ErrorReporter` (with `NullErrorReporter` + `SentryErrorReporter` scrubbing PII/tokens/Ride coordinates) and `OffHostBackupContract` (with `NullOffHostBackupAdapter` + manifest/checksum verification command `backup:verify`), both safely disabled until credentials/buckets are provisioned.

4. **Consent-Aware Public-Only Analytics (Plausible)**
   - Controlled via `services.analytics.provider` (`plausible` | `none`) and `services.analytics.plausible_domain`.
   - **Public pages only**: Mounted exclusively on public marketing/catalog/info routes; **strictly omitted** from booking status/private guest pages, driver trip links, payment checkout/return, and Filament admin.
   - **Explicit opt-in consent**: Uses a compact, non-modal bottom bar (`<15%` viewport height, accessible Accept / Decline controls, footer preference reset) that only injects the `async defer fetchpriority="low"` script after explicit consent when `PLAUSIBLE_DOMAIN` is configured.

