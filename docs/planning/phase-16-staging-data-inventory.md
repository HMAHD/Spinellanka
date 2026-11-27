# Phase 16 — Staging-Data Inventory & Classification

**Last updated:** 2026-11-27  
**Branch:** `phase/16-client-provider-activation`  
**Authority:** `roadmap.md` Phase 16 & `docs/ux/08-acceptance-and-signoff.md` (`CHG-003`)

---

## 1. Classification Policy

Every record entering or exported from the staging environment belongs to exactly one class:

| Class | Label | Allowed in Staging? | Allowed in `staging_synthetic` Package? | Allowed in `production_approved` Package? | Notes |
|---|---|---|---|---|---|
| **Class A** | Approved Business Configuration | Yes | Yes | Yes (once real fleet/rates also approved) | `RentPolicy` (§8), `config/booking.php` (§6–7), USD primary currency (§9.1) |
| **Class B** | Synthetic Staging Fixtures (`CHG-003`) | Yes (`!app()->isProduction()`) | Yes | **NO — Blocked** | Tagged `SYNTH-`; includes 5 synthetic vehicles, synthetic extras, pricing rules, rate basis, public-safe synthetic drivers, travelers, bookings, payments, incidents |
| **Class C** | Excluded Operational PII & Secrets | Excluded / Prohibited | **NO — Rejected on Leakage** | **NO — Rejected on Leakage** | Real driver operational PII (`full_name`, `phone`, `whatsapp`, `license_number`, `license_expiry`, `internal_notes`), vehicle `vin`/`registration_expiry`/`insurance_expiry`/`internal_notes`, staff credentials/MFA, traveler PII, raw card/ID uploads, API secrets |

---

## 2. Inventory by Entity / Table

| Table / Domain | Class | Owner & Source | Record Count (Staging Rehearsal) | PII / Secret Handling | Production Status |
|---|---|---|---|---|---|
| `rent_policies` | Class A | Project Lead (`07-client-input-checklist.md` §8, 2026-11-25) | 1 active record | Non-PII policy bounds (`min_driver_age=23`, `max_driver_age=70`, etc.) | Approved |
| `vehicles` | Class B (`CHG-003`) | Project Lead (`StagingSyntheticFixturesSeeder`) | **Exact 5** (`SYNTH-VEH-01`..`05`) | `vin`, `registration_expiry`, `insurance_expiry`, `internal_notes` excluded from export; `SYNTH-` prefix blocks production export | Blocked pending client §2 fleet pack |
| `vehicle_media` | Class B (`CHG-003`) | Project Lead (`StagingSyntheticFixturesSeeder`) | 0 approved public photos (truthful pending state preserved) | No unconsented stock/AI photos used; `production_approved` enforces `alt_text`, `photographer`, `usage_rights`, and disk existence | Blocked pending client §2 photography |
| `drivers` | Class B (Public-safe only) / Class C (Private PII excluded) | Project Lead (`StagingSyntheticFixturesSeeder`) | 4 synthetic chauffeur profiles (`SYNTH-DRIVER-01`..`04`) | DB NOT NULL `full_name` & `phone` populated with non-PII `SYNTH-DRIVER-01` / `+94000000001`; `drivers` operational PII **strictly excluded** from configuration packages | Private PII excluded until 6 named owner controls are approved |
| `vehicle_driver_assignments` | Class B (`CHG-003`) | Project Lead | 4 permanent ties (`SYNTH-VEH-01`..`04`; `SYNTH-VEH-05` Rent-only) | Excluded from config export; operational runtime state | Blocked pending client §3 driver ties |
| `extras` & `extra_compatibility` | Class B (`CHG-003`) | Project Lead (`StagingSyntheticFixturesSeeder`) | 2 extras, 2 compatibility rules | Synthetic USD minor-unit amounts (`synth-` slug prefix) | Blocked pending client §12 |
| `pricing_rules` & `pricing_rule_components` | Class B (`CHG-003`) | Project Lead (`StagingSyntheticFixturesSeeder`) | 4 service rules (`SYNTH-RULE-*`), 11 components | Integer minor units (`USD`); keyed by `metadata['config_key']`; immutable against `Estimate` FKs | Blocked pending client §9 rates |
| `exchange_rate_bases` | Class B (`CHG-003`) | Project Lead (`StagingSyntheticFixturesSeeder`) | 1 active synthetic basis (`308.5000`) | Source note explicitly labeled `SYNTHETIC STAGING BASIS` | Blocked pending client §9.1 rate source |
| `bookings`, `booking_contacts`, service details, `estimates`, `proposals`, `payment_transactions`, `handovers`, `incidents` | Class B (Runtime Synthetic Only) | Automated E2E Journey Tests | Synthetic test-scoped records only | **Strictly excluded** from all configuration packages (`ConfigurationPackageValidator` rejects any leakage) | Synthetic only; never real travelers/payments/incidents |
| `users`, `audit_events`, `guest_access_tokens`, `driver_access_tokens`, `.env` secrets | Class C | System / Security boundary | — | **Strictly excluded** from configuration packages; passwords/tokens redacted in `AuditService` & `bootstrap/app.php` | Never exported |

---

## 3. Excluded Driver Operational PII Checklist

Private driver operational PII (`full_name`, personal `phone`, personal `whatsapp`, `license_number`, `license_expiry`, `internal_notes`) remains **blocked and excluded** until all six named controls are approved and recorded:

| Control | Required Owner | Status |
|---|---|---|
| Access control & least-privilege policy | Security / Operations owner | Pending named approval |
| Purpose & documented driver consent | Privacy / HR owner | Pending named approval |
| Encryption at rest & transport boundary | Technical owner | Pending named approval |
| Retention schedule | Legal / Privacy owner | Pending named approval |
| Encrypted off-host backup coverage | Hosting / Technical owner | Blocked (Phase 01 Gate 10) |
| Erasure / deletion workflow | Privacy / Operations owner | Pending named approval |

