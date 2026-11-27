# Spinellanka Phase 16: Client Content Reconciliation, Configuration Transfer & Safe Provider Activation
## Final Resolved Technical Specification

**Branch:** `phase/16-client-provider-activation`  
**Base:** `main` (`ff6d12e`, including merged Phase 15 PR #18)  
**Review Status:** APPROVED via EGM Goldfish Synthesis (`docs/designs/egm-phase-16/goldfish_review_report.md`)  
**Authority Alignment:** Preserves `CLAUDE.md`, `PRODUCT.md`, `DESIGN.md`, `docs/ux/07-client-input-checklist.md`, and `docs/ux/08-acceptance-and-signoff.md` (`CHG-003`)  
**Timezone Reference:** Business schedule & effective dates in `Asia/Colombo`; snapshot & audit timestamps in UTC.

---

## 1. Data Classification & Authority Artifacts

1. **Launch-Input Matrix** (`docs/planning/phase-16-launch-input-matrix.md` and `docs/ux/07-client-input-checklist.md`):
   - Maps every checklist item (§1–§17) with `Owner`, `Source`, `Status`, `Approver / Date`, `Version`, `Conditions`, and `Effective Date`.
   - Items verified/approved: §6 (Airport Transfer), §7 (Ride), §8 (Rent eligibility/terms), §9.1 Primary currency (`USD`), plus `CHG-003` staging synthetic allowance and Phase 16 Plausible analytics / Google Maps / Meta WhatsApp adapter approvals.
   - All unverified production client inputs (§1 Brand, §2–3 Real Fleet/Photos/Drivers, §4–5 Geography/Pickup, §9.2–9.3 Real Rates, §10–16 Legal/Support/Templates, plus Postmark/PayHere/Sentry/Off-host S3 credentials) remain explicitly **Omitted / Blocked** for production export.
2. **Staging-Data Inventory** (`docs/planning/phase-16-staging-data-inventory.md`):
   - **Class A — Approved Business Policy Configuration**: `RentPolicy` defaults (§8), Airport Transfer & Ride configuration (`config/booking.php`).
   - **Class B — Synthetic Staging Fixtures (`CHG-003`)**: Exactly 5 synthetic vehicles (`SYNTH-VEH-01`..`05`), synthetic extras, synthetic pricing rules + approved-pattern calculation verification, synthetic USD/LKR rate basis, synthetic public-safe driver records, and synthetic travelers/bookings/payments/incidents.
   - **Class C — Excluded Private Operational PII & Secrets**: Real driver operational PII (`full_name`, `phone`, `whatsapp`, `license_number`, `license_expiry`, `internal_notes`), vehicle `vin`/`registration_expiry`/`insurance_expiry`/`internal_notes`, user credentials/MFA secrets, traveler PII, payment secrets, and API keys.

---

## 2. Database Schema & Migration

`database/migrations/2026_11_27_100000_create_configuration_snapshots_table.php`:

```php
Schema::create('configuration_snapshots', function (Blueprint $table): void {
    $table->id();
    $table->unsignedInteger('version_number')->unique();
    $table->string('schema_version', 16);
    $table->string('package_classification', 32); // production_approved | staging_synthetic
    $table->string('source_revision', 64);
    $table->string('checksum_sha256', 64);
    $table->json('record_counts');
    $table->longText('payload_json'); // Exact canonical signed JSON payload
    $table->boolean('is_active')->default(false);
    $table->foreignId('applied_by_user_id')->nullable()->constrained('users')->nullOnDelete();
    $table->dateTime('applied_at')->nullable(); // Stored in UTC
    $table->foreignId('rolled_back_from_id')->nullable()->constrained('configuration_snapshots')->nullOnDelete();
    $table->string('rollback_reason', 255)->nullable();
    $table->text('notes')->nullable();
    $table->timestamps();

    $table->index(['is_active', 'version_number'], 'idx_config_snapshots_active_version');
    $table->index('package_classification', 'idx_config_snapshots_classification');
});
```

---

## 3. Configuration Export / Import / Rollback (`App\Domain\Configuration\`)

### 3.1 Canonical Package Envelope (`schema_version = "1.0"`)
- Top-level keys: `schema_version`, `package_classification`, `source_revision`, `exported_at`, `exported_by_role` (non-PII), `exclusions`, `approval_manifest`, `record_counts`, `checksum_sha256`, `payload`.
- `payload` sections (purely DB-backed business config):
  - `vehicles`: exactly 5 non-retired vehicles (`internal_fleet_id`, `public_name`, `make`, `model`, `year`, `license_plate`, `vehicle_type`, `passenger_capacity`, `luggage_capacity`, `transmission`, `has_ac`, `fuel_tank_capacity_litres`, `accessibility_features`, `amenities`, `eligible_for_pickup`, `eligible_for_transfer`, `eligible_for_ride`, `eligible_for_rent`, `turnaround_buffer_minutes`, `status`).
  - `vehicle_media`: keyed by `(vehicle_internal_fleet_id, media_type, sort_order)` with `file_path`, `disk`, `alt_text`, `photographer`, `usage_rights`, `is_approved`.
  - `extras`: keyed by `slug`.
  - `extra_compatibility`: keyed by `(extra_slug, service_type, vehicle_type, vehicle_internal_fleet_id, is_compatible)`.
  - `pricing_rules`: keyed by `metadata['config_key']` (deterministic natural key) with `service_type`, `vehicle_internal_fleet_id`, `priority`, `is_active`, `effective_from` / `effective_to` (`Asia/Colombo` ISO-8601), `notes`, `metadata`, and sorted `components`.
  - `exchange_rate_bases`: latest rate (`rate`, `source_note`, `effective_at` in `Asia/Colombo`).
  - `rent_policy`: singleton active `RentPolicy` attributes.

### 3.2 Canonical Checksum & Security Validator (`ConfigurationPackageValidator`)
- Recursive canonicalization: sort associative array keys (`ksort($value, SORT_STRING)`) when `!array_is_list($value)`; preserve indexed list order. Encode with `JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE`.
- `checksum_sha256` = `hash('sha256', canonicalJson(['schema_version', 'package_classification', 'record_counts', 'approval_manifest', 'payload']))`.
- **Schema & Parity**: Assert `schema_version === '1.0'`, valid `PackageClassification`, all required sections present, and `record_counts` matches actual counts (with `pricing_rule_components` equal to total nested components across `pricing_rules`).
- **Secret & PII Exclusion Scan**:
  - Reject any forbidden key anywhere inside `payload` (or unexpected top-level keys): `full_name`, `phone`, `whatsapp`, `email`, `license_number`, `license_expiry`, `vin`, `registration_expiry`, `insurance_expiry`, `internal_notes`, `password`, `current_password`, `reauth_password`, `secret`, `token`, `api_key`, `dsn`, `app_key`, `merchant_secret`, `system_user_token`, `server_token`, `private_key`, `driver_operational_pii`, `users`, `bookings`, `booking_contacts`, `payment_transactions`, `incidents`, `handovers`.
  - Reject credential/PII value patterns inside `payload` string values (Google API key `AIza...`, `Bearer ...`, `base64:...`, URL credentials `scheme://user:pass@`, raw emails, E.164 phone numbers `+\d{8,15}`).
- **5-Vehicle & Classification Rules**:
  - Exactly 5 non-retired vehicles with unique `internal_fleet_id` and `license_plate`.
  - `production_approved`: reject any `SYNTH-` / `TEST-` markers, require non-empty `approval_manifest`, and require every `is_approved=true` media item to have `alt_text`, `photographer`, `usage_rights`, and an existing file on disk.
  - `staging_synthetic`: throw `ConfigurationSecurityException` if `app()->isProduction()` is true.

### 3.3 Idempotent Import & Safe Rollback (`ConfigurationTransferService`)
- Serialized via `Cache::lock('spinellanka_config_transfer', 30)` + `ConfigurationSnapshot::lockForUpdate()` inside `DB::transaction()`.
- **Dry-Run (`validateAndDryRun`)**: Computes `ConfigurationDriftReport` (`added`, `updated`, `unchanged`, `missing_from_package`, `warnings`) without mutating DB.
- **Apply (`import(..., dryRun: false)`)**:
  - Auto-captures pre-import baseline snapshot (`version_number = max + 1`, tagged `baseline` in notes) before first mutation if no snapshot is active.
  - Short-circuits idempotently when drift report has zero changes and current active snapshot `checksum_sha256` matches.
  - **Vehicles**: Two-pass `license_plate` swap (`SWAP-{id}-{hash}` temporary plates inside transaction) if plates trade between vehicles; uses `VehicleService`. Refuses to retire vehicles missing from package if they have active future bookings or open `VehicleDriverAssignment`s.
  - **VehicleMedia**: Upserts by natural key; deactivates (`is_approved = false`) omitted rows instead of deleting (avoiding `VehicleMedia::deleted` disk unlink!).
  - **PricingRules**: Matches by `metadata['config_key']`. Skips unchanged rules (no `version` increment!). On drift, calls `PricingRuleService::deactivateRule()` + `PricingRuleService::createRule()` so `Estimate`/`EstimateItem` FKs stay immutable.
  - **Extras, ExchangeRateBasis, RentPolicy**: Upsert via `ExtraService`, `ExchangeRateService` (record only if latest `rate` or `source_note` changed), and `RentPolicyService`.
  - Records `ConfigurationSnapshot` (`is_active = true`, UTC `applied_at`) and `AuditService::record('configuration.imported', ...)`.
- **Rollback (`rollback(?int $snapshotId, ?User $actor, string $reason)`)**:
  - Defaults to most recent prior snapshot. Restores state through the same domain services (calling `ExchangeRateService::recordRate()` with `now('Asia/Colombo')` if rate differs so `getLatestRate()` resolves the reverted rate), marks reverted snapshot active via new snapshot record with `rolled_back_from_id` pointing to the target snapshot, and records `AuditService::record('configuration.rolled_back', ...)`.

### 3.4 Runtime Production Guard
- `ProviderReadinessService` & public catalog routes fail closed in `app()->isProduction()` if an active `staging_synthetic` snapshot or `SYNTH-VEH-` vehicles exist.

---

## 4. Provider Adapters, Backups, Exception Scrubbing & Analytics

1. **Google Maps Platform (`GoogleMapsPlaceSearchService`, `GoogleMapsRouteEstimateService`)**:
   - Bound in `AppServiceProvider` when `services.maps.provider === 'google_maps'` and `filled(config('services.maps.key'))`; otherwise `NullPlaceSearchService` / `NullRouteEstimateService`.
   - Implements `search()`, `getDetails()`, and `estimateRoute()` using Places API (New) and Routes API v2 (`regionCode=LK`, `X-Goog-Api-Key`, `X-Goog-FieldMask`, `connectTimeout(3)->timeout(5)`).
   - Caches static/repeated lookups (`24h` for `airport`/`hotel` presets and `getDetails`, `15m` for query/route results) so GET form renders do not exhaust API quota.
   - Strips email/phone patterns from queries before calling Google; never logs API keys.
   - Raises `place_id` validation `max:255` in `AirportPickupBookingController` and `AirportTransferBookingController`.
   - On API error/timeout/quota failure: `GoogleMapsPlaceSearchService` falls back to `NullPlaceSearchService`; `GoogleMapsRouteEstimateService` returns `null` (triggering transparent custom-quote / route-unavailable notice rather than Haversine approximation).
2. **Meta WhatsApp Cloud API (`MetaWhatsAppSender`)**:
   - Stays safely on `FakeWhatsAppSender` until `WHATSAPP_PROVIDER=meta_whatsapp` and `META_WHATSAPP_PHONE_NUMBER_ID` + `META_WHATSAPP_SYSTEM_USER_TOKEN` are set.
   - `ProviderReadinessService` checks required credentials and canonical template allowlist (`booking_submitted_v1`, `booking_confirmed_v1`, `booking_declined_v1`, `booking_cancelled_v1`).
3. **Postmark, PayHere, Exception Scrubbing & Backups**:
   - Postmark and PayHere stay in contract-tested `fake` mode (`Blocked` for live sandbox until credentials supplied).
   - `bootstrap/app.php` scrubs PII, guest/driver URL tokens, API keys, and coordinates from exception context/messages before logging or optional Sentry DSN reporting (`config('services.sentry.dsn')`).
   - `php artisan backup:verify` verifies DB dump capability safely (`proc_open` check, `0600` `--defaults-extra-file` cleaned in `finally`), generates SHA-256 manifest, and reports `Blocked` when `filesystems.disks.off_host_backup` (S3) is unconfigured.
4. **Consent-Aware Public-Only Analytics (Plausible)**:
   - Bundled inside Vite-compiled `resources/js/app.js` (no inline `<script>`).
   - `partials/analytics-consent.blade.php` renders ONLY when:
     - `config('services.analytics.provider') === 'plausible'` AND `filled(config('services.analytics.plausible_domain'))`
     - AND `$request->route() !== null`
     - AND `request()->routeIs('home', 'services.*', 'fleet.*', 'how-it-works', 'help', 'contact', 'legal.*')` (positive allowlist; strictly absent from booking/guest/driver/payment/admin routes and 404s!).
   - Non-modal bottom consent bar (`<15%` viewport, Accept / Decline buttons, plus `"Analytics settings"` control in `partials/footer.blade.php`). Loads Plausible script with `async defer fetchpriority="low"` only after explicit acceptance.

---

## 5. CLI, Filament & Staging Fixtures

- **Artisan Commands**:
  - `config:export-package {path} {--classification=staging_synthetic} {--notes=} {--actor=} {--force}`
  - `config:import-package {path} {--dry-run} {--apply} {--notes=} {--actor=} {--force}` (defaults safely to `--dry-run`)
  - `config:rollback-package {--snapshot=} {--reason=} {--actor=} {--force}`
  - `backup:verify {--output-dir=}`
  - `providers:readiness`
- **Filament Page**: `app/Filament/Pages/ConfigurationManagement.php` (`Permission::ConfigManage`, uses `RequiresReauthentication::configureReauthentication()` on rollback).
- **Staging Fixtures**: `database/seeders/StagingSyntheticFixturesSeeder.php` (not registered in `DatabaseSeeder.php`; refuses to run in `production`; creates 5 synthetic vehicles, synthetic non-PII drivers, extras, pricing rules for all 4 services, exchange rate basis, and active `RentPolicy`).

