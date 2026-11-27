# Spinellanka Phase 16: Client Content Reconciliation, Configuration Transfer & Safe Provider Activation
## Technical Design Draft (EGM Phase 16 Draft)

**Branch:** `phase/16-client-provider-activation`  
**Base:** `main` (`ff6d12e`)  
**Timezone:** `Asia/Colombo` for effective dates and operational windows; UTC for snapshot/audit/infrastructure timestamps.

---

### 1. Database Schema & Migrations (Expand/Contract Compatible)

#### Migration: `create_configuration_snapshots_table`
`database/migrations/2026_11_27_100000_create_configuration_snapshots_table.php`

```php
Schema::create('configuration_snapshots', function (Blueprint $table): void {
    $table->id();
    $table->unsignedInteger('version_number')->unique();
    $table->string('schema_version', 16); // e.g. "1.0"
    $table->string('package_classification', 32); // "production_approved" | "staging_synthetic"
    $table->string('source_revision', 64);
    $table->string('checksum_sha256', 64);
    $table->json('record_counts');
    $table->longText('payload_json');
    $table->string('status', 32)->default('staged'); // staged, active, superseded, rolled_back
    $table->foreignId('applied_by_user_id')->nullable()->constrained('users')->nullOnDelete();
    $table->dateTime('applied_at')->nullable();
    $table->foreignId('rolled_back_from_id')->nullable()->constrained('configuration_snapshots')->nullOnDelete();
    $table->string('rollback_reason', 255)->nullable();
    $table->text('notes')->nullable();
    $table->timestamps();

    $table->index(['status', 'version_number'], 'idx_config_snapshots_status_version');
    $table->index('package_classification', 'idx_config_snapshots_classification');
});
```

*Backward compatibility*: Purely additive table; prior release ignores it completely.

---

### 2. Configuration Transfer Module (`App\Domain\Configuration\`)

#### 2.1 Enums & DTOs
- `PackageClassification` (`production_approved`, `staging_synthetic`)
- `SnapshotStatus` (`staged`, `active`, `superseded`, `rolled_back`)
- `ConfigurationDriftReport` (`added`, `updated`, `unchanged`, `missing_from_package`, `warnings`, `checksum_valid`, `schema_valid`, `is_valid`)
- `ConfigurationImportResult` (`snapshot`, `driftReport`, `dryRun`, `appliedCounts`)

#### 2.2 Canonical Package Schema (`schema_version = "1.0"`)
```json
{
  "schema_version": "1.0",
  "package_classification": "staging_synthetic",
  "source_revision": "git-sha",
  "exported_at": "2026-11-27T10:00:00Z",
  "exported_by": "staff@example.com",
  "exclusions": [
    "users", "bookings", "booking_contacts", "payment_transactions",
    "incidents", "handovers", "audit_events", "secrets", "driver_operational_pii"
  ],
  "record_counts": {
    "vehicles": 5,
    "vehicle_media": 0,
    "extras": 2,
    "extra_compatibility": 2,
    "pricing_rules": 4,
    "pricing_rule_components": 8,
    "exchange_rate_bases": 1,
    "rent_policies": 1
  },
  "checksum_sha256": "<sha256 of canonical JSON of schema_version + package_classification + record_counts + payload>",
  "payload": {
    "vehicles": [],
    "vehicle_media": [],
    "extras": [],
    "extra_compatibility": [],
    "pricing_rules": [],
    "exchange_rate_bases": [],
    "rent_policies": [],
    "operational_policies": {}
  }
}
```

#### 2.3 Strict Exclusion & Validation Rules (`ConfigurationPackageValidator`)
1. **Schema Check**: `schema_version === '1.0'`, valid `PackageClassification`, required top-level keys (`schema_version`, `package_classification`, `source_revision`, `record_counts`, `checksum_sha256`, `payload`).
2. **Checksum Integrity**: Canonicalize nested associative arrays recursively (sort keys lexicographically, `JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE`) and verify `hash_equals($expected, $package['checksum_sha256'])`.
3. **Count Parity**: Verify `record_counts` exactly matches actual array lengths in `payload` (including nested `pricing_rule_components` and `vehicle_media`).
4. **Forbidden Secret & PII Key Scan**: Recursively scan all keys and string values for forbidden keys (`full_name`, `phone`, `whatsapp`, `email`, `license_number`, `license_expiry`, `vin`, `internal_notes`, `password`, `secret`, `token`, `api_key`, `dsn`, `app_key`, `merchant_secret`, `system_user_token`, `server_token`, `private_key`, `driver_operational_pii`) and reject with `ConfigurationSecurityException` if any forbidden key appears.
5. **Exact Five-Vehicle Rule**:
   - `count($payload['vehicles']) === 5`
   - Unique `internal_fleet_id` and unique `license_plate` across all 5 vehicles (case-insensitive check).
   - Valid `vehicle_type`, `transmission`, `status`, `passenger_capacity` (`1..50`), `luggage_capacity` (`0..50`), `turnaround_buffer_minutes` (`30..720`).
6. **Classification Gate (`production_approved` vs `staging_synthetic`)**:
   - If `package_classification === 'production_approved'`:
     - Reject any vehicle/rule/media containing `SYNTHETIC` or `TEST-` markers or missing `approval_metadata` (`owner`, `source`, `approved_by`, `approved_at`, `effective_date`).
     - Reject any `vehicle_media` with `is_approved === true` lacking non-empty `alt_text`, `photographer`, and `usage_rights`.
   - If `package_classification === 'staging_synthetic'`:
     - Permitted ONLY when `!app()->isProduction()`. Calling import with `staging_synthetic` in `production` environment throws `ConfigurationSecurityException`.
     - Require explicit `SYNTHETIC` marker or `is_synthetic_staging` tag in package metadata.

#### 2.4 `ConfigurationTransferService`
- **`export(PackageClassification $classification, ?User $actor = null, ?string $notes = null): array`**
  - Exports current non-secret, non-PII configuration via read queries (explicitly omitting `vin`, `internal_notes`, driver PII, users, bookings, payments, secrets).
  - Computes canonical `checksum_sha256`, records `AuditService::record('configuration.exported', ...)`.
- **`validateAndDryRun(array $package): ConfigurationDriftReport`**
  - Validates structure, checksum, counts, 5-vehicle uniqueness, forbidden keys, classification rules.
  - Compares incoming `vehicles` (by `internal_fleet_id`), `extras` (by `slug`), `pricing_rules` (by `external_key` / `(service_type, vehicle_internal_fleet_id, version)`), `rent_policies`, `exchange_rate_bases` against live database to produce a deterministic `ConfigurationDriftReport`.
- **`import(array $package, ?User $actor = null, bool $dryRun = false, ?string $notes = null): ConfigurationImportResult`**
  - Runs `validateAndDryRun($package)`. If `dryRun === true`, returns report without mutating DB and records `configuration.dry_run_validated`.
  - Otherwise, enters `DB::transaction()`:
    - Captures pre-import baseline snapshot if no active snapshot exists yet (`ConfigurationSnapshot` captured automatically before first mutation so rollback is ALWAYS available).
    - Upserts vehicles via `VehicleService::createVehicle` / `VehicleService::updateVehicle` (retiring/flagging missing vehicles rather than hard-deleting FK-referenced rows; enforcing active/valid fleet count = 5).
    - Upserts extras & compatibility via `ExtraService`.
    - Upserts pricing rules & components via `PricingRuleService` (updating/deactivating drifted rules without destroying historical `Estimate` FKs).
    - Records `ExchangeRateBasis` via `ExchangeRateService` if latest rate differs.
    - Updates active `RentPolicy` cleanly.
    - Marks previous `active` snapshot `superseded`, creates new `active` `ConfigurationSnapshot` with `version_number = max + 1`.
    - Records `AuditService::record('configuration.imported', ...)`.
- **`rollback(?int $targetSnapshotId = null, ?User $actor = null, string $reason = 'Manual rollback'): ConfigurationImportResult`**
  - Requires `Permission::ConfigManage` when actor present.
  - Resolves target snapshot (defaults to most recent `superseded` snapshot prior to current `active`).
  - Re-validates snapshot checksum & classification before applying.
  - Restores exact configuration state through domain services inside `DB::transaction()`.
  - Marks current active snapshot `rolled_back`, creates new `active` snapshot pointing `rolled_back_from_id` to reverted snapshot, records `AuditService::record('configuration.rolled_back', ...)`.

#### 2.5 Artisan Commands & Filament Management
- `php artisan config:export-package {path} --classification=staging_synthetic`
- `php artisan config:import-package {path} --dry-run` / `--apply`
- `php artisan config:rollback-package {--snapshot=} {--reason=}`
- Filament page `ConfigurationManagement` (gated by `Permission::ConfigManage`, MFA, and password re-authentication for apply/rollback) showing active configuration snapshot, launch-input readiness summary, provider sandbox readiness status, dry-run validation, and rollback action.

---

### 3. Provider Adapters, Error Reporting, Backups & Analytics

#### 3.1 Google Maps Platform (`App\Domain\Location\Services\`)
- `GoogleMapsPlaceSearchService implements PlaceSearchContract`:
  - Config: `services.maps.provider`, `services.maps.key`, `services.maps.allowed_origin`, `services.maps.timeout_seconds` (default 5), `services.maps.daily_quota_cap`.
  - Calls `https://places.googleapis.com/v1/places:searchText` (`X-Goog-Api-Key`, `X-Goog-FieldMask`, optional `Referer` from `allowed_origin`), restricted to `regionCode: 'LK'`.
  - On missing key, HTTP error, quota exceeded, or timeout: logs sanitized warning and falls back to `NullPlaceSearchService` (preserving manual text entry).
- `GoogleMapsRouteEstimateService implements RouteEstimateContract`:
  - Calls `https://routes.googleapis.com/directions/v2:computeRoutes` (`TRAVEL_MODE: DRIVE`, `routingPreference: TRAFFIC_UNAWARE` or `TRAFFIC_AWARE`).
  - On failure/missing key: falls back cleanly or returns `null` (triggering transparent custom-quote flow per `02-system-architecture.md`!).
  - **Zero PII / Zero Ride device location**: Never transmits user names, phones, or browser geolocation coordinates.

#### 3.2 Provider Readiness & Contract Verification (`ProviderReadinessService`)
- Evaluates each provider (`maps`, `meta_whatsapp`, `postmark`, `payhere`, `sentry`, `off_host_backup`, `analytics`) strictly from `config('services.*')` without exposing secret values:
  - Returns `configured: bool`, `mode: 'sandbox' | 'disabled_fallback' | 'production'`, `missing_env_keys: string[]`, `checks: array`.

#### 3.3 Error Reporting (`App\Domain\Operations\Contracts\ErrorReporter`)
- Interface `ErrorReporter`: `captureException(Throwable $e, array $context = []): void`, `isConfigured(): bool`.
- `NullErrorReporter`: no-op when `services.sentry.dsn` is unset.
- `SentryErrorReporter`: scrubs PII (`email`, `phone`, `whatsapp`, `token`, `secret`, `authorization`, `latitude`, `longitude`, `coordinates`) before forwarding when enabled.

#### 3.4 Off-Host Backups (`App\Domain\Operations\Contracts\OffHostBackupContract`)
- `OffHostBackupContract` + `NullOffHostBackupAdapter` (disabled until S3 destination/key configured).
- Artisan command `backup:verify` verifying local database dump capability (`mysqldump`/SQLite check), manifest SHA-256 generation, and recording explicit `Blocked` status when off-host S3 destination is unprovisioned.

#### 3.5 Consent-Aware Public-Only Analytics (Plausible)
- Blade partial `resources/views/partials/analytics-consent.blade.php` included ONLY in `layouts/app.blade.php` when:
  - `!request()->routeIs('booking.status*', 'booking.payment*', 'driver.*', 'staff.*', 'operations.*', 'filament.*')`
  - AND `config('services.analytics.provider') === 'plausible'`
  - AND `filled(config('services.analytics.plausible_domain'))`
- Uses compact non-blocking consent bar (`<15%` viewport, `role="region"`, `aria-label="Analytics consent"`, Accept / Decline buttons, footer "Analytics settings" button).
- Only after explicit `"accepted"` consent does JS create `<script async defer fetchpriority="low" data-domain="..." src="...">` via `document.createElement('script')` (no `innerHTML`).

---

### 4. Staging Synthetic Fixtures (`CHG-003`) & Four End-to-End Journeys
- Dedicated `StagingSyntheticFixturesSeeder` (refuses to run if `app()->isProduction()`!) creates:
  - Exactly 5 synthetic vehicles (`SYNTH-VEH-01`..`05`) covering Pickup, Transfer, Ride, and Rent eligibility, with explicit `"SYNTHETIC STAGING FIXTURE — NOT CLIENT APPROVED"` tagging.
  - Synthetic non-PII public driver profiles (`public_name` + `public_trust_facts` only; zero real driver PII).
  - Synthetic extras, active `RentPolicy`, manual `ExchangeRateBasis` (`308.5000`, marked synthetic), and effective-dated `PricingRule`s for all 4 services with deterministic pricing examples.
- All 4 end-to-end journeys (Airport Pickup, Airport Transfer with unsafe-time override, Ride with multi-stop/return, Rent with 24h periods + handover/return/incident) execute against synthetic travelers, bookings, payments, and incidents.

