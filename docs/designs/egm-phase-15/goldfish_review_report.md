# Phase 15: Rent Field Operations, Incidents, Private Attachments & Privacy Governance
## EGM Goldfish Review Synthesis & Action Plan

**Date**: 2026-11-26  
**Evaluators**: 
- Hostile Design Critic (`f90fa493`)
- Pragmatic Tech Lead (`ee3965c9`)
- Technical Quality Auditor (`aa6b970c`)  
**Status**: APPROVED WITH RESOLUTION ACTION PLAN

---

### Executive Summary & Consolidated Findings

All three reviewer personas completed thorough reviews of [`docs/designs/egm-phase-15/design_draft.md`](file:///Users/akash/Documents/Spinellanka/docs/designs/egm-phase-15/design_draft.md). The design is architecturally sound in its intent (zero identity uploads, private quarantine pipeline, append-only payments, legal hold protections), but identified critical implementation, concurrency, and security vulnerabilities that must be resolved in the final design.

---

### 1. High-Priority Defect Resolutions

| ID | Domain | Reviewer Finding | Resolution in Final Specification |
|:---|:---|:---|:---|
| **RES-01** | **Release Gates** | Permissive boolean logic & self-authorization bypass in `HandoverService`. | Implement an explicit Handover State Machine: `Scheduled -> DocumentsVerified -> ConditionCaptured -> SignaturesCaptured -> Released` (or `ExceptionApproved`). Enforce Four-Eyes Principle: `exception_authorized_by_user_id` MUST possess `Permission::BookingOverrideConflict` (`booking.override_conflict`) AND MUST NOT match `staff_user_id`. Mandatory foreign licence checks: if `country !== 'LK'`, `idp_or_aac_verified === true` and `expiry > now()`. |
| **RES-02** | **Condition Comparison** | Negative odometer delta (meter rollback/swap) and fuel refill miscalculations. | Throw `OdometerRollbackAnomalyException` when `$returnKm < $collectionKm`, halt completion, and automatically log a high-severity incident (`type: 'damage'`). Implement 0-km audit verification for multi-day rentals. Calculate fuel shortfall using vehicle fuel tank capacity and current market rate, clamped at 0. |
| **RES-03** | **Upload Security** | Memory exhaustion via GD decompression bombs (e.g. 25k x 25k PNG), lack of PDF support for incident reports, and EXIF orientation loss. | Add pre-allocation dimension validation via `getimagesize()` (`width <= 4096 && height <= 4096 && width * height <= 16_000_000`). Create dual pipeline: `RasterImagePipeline` (auto-orient EXIF, re-encode via GD to JPEG/WebP, strip metadata, disallow SVG) and `DocumentPipeline` (incident police/claim PDFs: validate `%PDF-`, reject `/JavaScript` / `/Launch` tokens). Download responses strictly enforced with `Content-Disposition: attachment; filename="attachment_<uuid>.<ext>"`, `nosniff`, `sandbox`, and `no-referrer`. |
| **RES-04** | **Signature Storage** | Storing Base64 data URIs in MySQL `TEXT` columns risks 64KB truncation, model bloat, and stored XSS. | Store signatures as clean PNG binary files in private disk storage: `$table->string('customer_signature_path', 255)->nullable();` and `$table->string('staff_signature_path', 255)->nullable();`. Validate PNG magic bytes, decode to private storage, and serve via private authorized stream. |
| **RES-05** | **Privacy & Retention** | TOCTOU race between anonymization and legal holds; confusion over "stale holds"; missing `anonymized_at` tracking; incomplete cascading PII scrubbing. | Anonymization executes inside atomic transaction with pessimistic row lock (`Booking::lockForUpdate()`) and verifies `whereDoesntHave('legalHolds', fn ($q) => $q->where('is_active', true))`. Clarify that `legal_holds` NEVER expire automatically (differentiating from `booking_holds`). Add `anonymized_at` timestamp column to `bookings`. Cascade PII scrub to nullify customer signature paths, names, and inspection notes, and physically unlink attachments from disk. |
| **RES-06** | **Financial Ledger** | 1:1 cardinality on incident transaction link; `PaymentService` lacks `recordOfflinePayment()`; danger of double-billing damage in return settlements. | Create `incident_financial_entries` junction table to track multiple financial movements per incident. Implement `PaymentService::recordOfflinePayment()` enforcing `payment.record_offline` permission and logging to `AuditService`. Implement a deduplication bridge in `ConditionComparisonService` to check existing incident financial charges and exclude already-billed damages from return condition settlement. |
| **RES-07** | **Data Normalization & RentDetail Sync** | Redundant `booking_id` and `vehicle_id` on `vehicle_condition_records`; duplicate inspection columns on `handovers` without sync to `rent_details`. | Remove `booking_id` and `vehicle_id` from `vehicle_condition_records` (accessible through `handover`). In `HandoverService`, when collection inspection completes, atomically update `rent_details` (`original_documents_inspected`, `inspected_at`, `inspected_by`, `inspection_notes`). |
| **RES-08** | **Zero Identity Documents** | Need strict negative guardrails and category whitelisting to prevent identity document photo uploads. | Explicitly forbid identity document inputs across all forms and routes. Enforce strict category whitelists on `condition_attachments` (`odometer`, `fuel_gauge`, `exterior_scratch`, `interior_stain`, etc.) and `incident_attachments` (`vehicle_damage`, `scene_photo`, `third_party_vehicle`, `repair_estimate`). |
| **RES-09** | **Audit Logging** | Central `AuditService` must log all critical Phase 15 events. | Implement synchronous `AuditService::record()` for: `handover.exception_authorized`, `incident.status_changed`, `media.private_file_downloaded`, `data_export.generated`, `data_export.downloaded`, and `retention.pass_completed`. |
| **RES-10** | **Migration Rollbacks & Indexes** | Missing reverse-order `down()` drops; missing composite indexes. | Add explicit reverse-order drop methods for all three migrations. Add composite indexes: `handovers(['status', 'scheduled_at'])` and `incidents(['status', 'severity'])`. |

---

### 2. Implementation Readiness Verdict

**VERDICT: APPROVED WITH REVISIONS**  
The draft design will be compiled into the final resolved specification `docs/designs/phase-15-rent-field-incidents-privacy.md` incorporating all ten resolutions. Implementation may proceed immediately upon sign-off of the resolved design.

