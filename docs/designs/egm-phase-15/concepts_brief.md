# Phase 15: Rent Field Operations, Incidents, Private Attachments & Privacy Governance
## Concepts Brief (EGM Phase 15 Brainstorm)

**Date**: 2026-11-26  
**Author**: Project Lead & System Architect  
**Branch**: `phase/15-rent-field-incidents-privacy`  
**Scope**: Rent handovers (collection & return), physical original-document inspection logging, condition comparison, strict private upload/decode pipeline, digital signatures, release gates & audited exceptions, incidents management, financial payment reconciliation, audited expiring exports, data subject privacy requests, legal holds, and idempotent retention execution.

---

### 1. Architectural & Privacy Invariants

1. **Physical Inspection Only (No Identity Uploads)**:
   - Spinellanka strictly prohibits uploading, photographing, or retaining copies/images of identity documents (passports, national identity cards, international driving permits, or local AAC endorsements).
   - Staff physically inspects the original documents in the field and logs structured verification booleans, document type, country of issue, expiry, and inspector identity.

2. **Private File Storage & Strict Decode/Re-Encode Pipeline**:
   - Condition photos and incident evidence are uploaded to private disk storage (`storage/app/private`), completely inaccessible from the web root.
   - Every file undergoes strict validation: allowlisted MIME types (`image/jpeg`, `image/png`, `image/webp`), size cap (max 5 MB), and randomized UUID filenames.
   - To neutralize polyglots, embedded malicious scripts, and EXIF/metadata leakage, the pipeline applies strict image decode and re-encode: re-rendering into a fresh pixel canvas via PHP GD/Imagick before persisting only the clean derivative. If decode fails, the file fails closed and is immediately unlinked.
   - Served strictly via authenticated, permissioned controller routes with safe headers:
     `Content-Type: image/jpeg`, `X-Content-Type-Options: nosniff`, `Content-Disposition: attachment; filename="condition-photo.jpg"`, `Cache-Control: private, no-cache, no-store, must-revalidate`.

3. **Handover Release Gates & Permissioned Exceptions**:
   - A vehicle collection is strictly blocked from release until:
     a) Original document inspection passed.
     b) Starting condition record (odometer, fuel level, accessories checklist, initial damage photos) recorded.
     c) Traveler and staff digital signatures captured.
   - If an operational impediment occurs, release requires an audited override by authorized staff (`booking.override_conflict` or Owner/Admin) with mandatory structured justification.

4. **Return Condition Comparison & Financial Ledger Traceability**:
   - At return, staff records return odometer, return fuel level, and scans for new damage.
   - The system automatically compares return vs collection:
     - Odometer delta vs included mileage allowance (100 km/day cumulative) -> calculates excess km and billable minor units at policy rate ($0.30/km).
     - Fuel level delta -> computes same-to-same fuel shortfall penalty if returned less than collection.
     - Damage/cleaning penalty -> if new damage or smoking contamination ($150) detected.
   - All charges, deposit deductions, or deposit refunds flow through the existing `PaymentService` as append-oriented transactions; settled ledger records are never directly altered.

5. **Incidents & Action Timelines**:
   - Access-controlled (`incident.view`, `incident.manage`).
   - Tracks severity (`minor`, `moderate`, `severe`, `critical`), state machine (`reported`, `investigating`, `action_required`, `resolved`, `closed`), chronological action history, private attachments, and linked financial impacts.

6. **Privacy Rights, Legal Holds & Idempotent Retention**:
   - Data Subject Requests: Track requests for export, correction, or erasure with cryptographic verification tokens.
   - Legal Holds: Entity-specific flag preventing automated anonymization or deletion while active investigations, disputes, or insurance claims are pending.
   - Expiring Exports: Permissioned exports (`export.customer_data`, `export.financial_data`) generate encrypted/private files with 24-hour TTL and audited single/controlled downloads.
   - Retention Engine: Command `retention:execute` purges expired tokens (>30d), expired export files (>24h), stale holds (>7d), and anonymizes PII on aged completed bookings (>3 years) while respecting legal holds.

