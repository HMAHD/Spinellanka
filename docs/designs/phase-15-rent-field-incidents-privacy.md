# Spinellanka Phase 15: Rent Field Operations, Incidents, Private Attachments & Privacy Governance
## Final Resolved Technical Specification

**Branch**: `phase/15-rent-field-incidents-privacy`  
**Base**: `main`  
**Review Status**: APPROVED via EGM Goldfish Synthesis (`docs/designs/egm-phase-15/goldfish_review_report.md`)  
**Timezone Reference**: All operational schedules, handover appointments, and timestamps anchored in `Asia/Colombo`. Audit and security event timestamps stored in UTC.

---

## 1. Domain Models & Database Schema

### A. Database Migrations (Sequenced to Prevent Circular Deadlocks)

#### Migration 1: Create Handover & Vehicle Condition Tables
`database/migrations/2026_05_01_000001_create_handover_and_condition_tables.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('handovers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->restrictOnDelete();
            $table->foreignId('rent_detail_id')->constrained('rent_details')->restrictOnDelete();
            $table->foreignId('vehicle_id')->constrained('vehicles')->restrictOnDelete();
            $table->string('type', 32); // collection, return
            $table->string('status', 32)->default('scheduled'); // scheduled, documents_verified, condition_captured, signatures_captured, released, exception_approved, completed, cancelled
            $table->dateTime('scheduled_at');
            $table->dateTime('completed_at')->nullable();
            $table->foreignId('staff_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('location_type', 32); // office, airport, hotel, custom
            $table->string('location_text', 255);

            // Physical original-document inspection result (STRICTLY NO UPLOADS)
            $table->boolean('documents_inspected')->default(false);
            $table->string('inspected_doc_types', 128)->nullable(); // e.g. "passport,national_licence,idp"
            $table->string('driver_licence_country', 64)->nullable();
            $table->dateTime('driver_licence_expiry')->nullable();
            $table->boolean('idp_or_aac_verified')->default(false);
            $table->text('document_inspection_notes')->nullable();

            // Signatures (Stored as clean PNG files in private storage, never raw Base64 data in SQL)
            $table->string('customer_signature_path', 255)->nullable();
            $table->string('customer_signer_name', 128)->nullable();
            $table->dateTime('customer_signed_at')->nullable();
            $table->string('staff_signature_path', 255)->nullable();
            $table->dateTime('staff_signed_at')->nullable();

            // Release Gate & Exception
            $table->boolean('is_released')->default(false);
            $table->boolean('has_exception')->default(false);
            $table->text('exception_reason')->nullable();
            $table->foreignId('exception_authorized_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('exception_authorized_at')->nullable();

            $table->text('operational_notes')->nullable();
            $table->timestamps();

            $table->index(['booking_id', 'type'], 'idx_handovers_booking_type');
            $table->index(['vehicle_id', 'scheduled_at'], 'idx_handovers_vehicle_date');
            $table->index(['status', 'scheduled_at'], 'idx_handovers_status_scheduled');
        });

        Schema::create('vehicle_condition_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('handover_id')->constrained('handovers')->cascadeOnDelete();
            $table->string('record_type', 32); // collection, return
            $table->integer('odometer_km');
            $table->string('fuel_level', 32); // empty, quarter, half, three_quarters, full
            $table->json('accessories_checklist')->nullable(); // spare_tire, jack, tool_kit, first_aid, dash_cam
            $table->boolean('is_clean')->default(true);
            $table->boolean('smoking_detected')->default(false);
            $table->boolean('is_zero_km_verified')->default(false);
            $table->text('zero_km_reason')->nullable();
            $table->text('exterior_condition_notes')->nullable();
            $table->text('interior_condition_notes')->nullable();
            $table->dateTime('recorded_at');
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('handover_id', 'idx_cond_handover');
        });

        Schema::create('condition_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_condition_record_id')->constrained('vehicle_condition_records')->cascadeOnDelete();
            $table->string('category', 64); // odometer, fuel_gauge, exterior_scratch, exterior_dent, interior_stain, windscreen, spare_wheel, tools, other_condition
            $table->string('file_uuid', 64)->unique();
            $table->string('file_path', 255); // relative path on local disk e.g. condition_attachments/{uuid}.jpg
            $table->string('original_filename', 255);
            $table->string('mime_type', 64);
            $table->unsignedInteger('file_size_bytes');
            $table->string('caption', 128)->nullable();
            $table->string('processing_status', 32)->default('clean'); // quarantined, clean, failed, purged_retention
            $table->dateTime('captured_at');
            $table->timestamps();

            $table->index('vehicle_condition_record_id', 'idx_cond_attach_record');
            $table->index('file_uuid', 'idx_cond_attach_uuid');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('condition_attachments');
        Schema::dropIfExists('vehicle_condition_records');
        Schema::dropIfExists('handovers');
    }
};
```

---

#### Migration 2: Create Incidents, Actions & Financial Ledger Tables
`database/migrations/2026_05_01_000002_create_incidents_and_actions_tables.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incidents', function (Blueprint $table) {
            $table->id();
            $table->string('incident_number', 64)->unique(); // INC-20260501-XXXX
            $table->foreignId('booking_id')->nullable()->constrained('bookings')->nullOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained('drivers')->nullOnDelete();
            $table->string('type', 32); // accident, breakdown, damage, theft, traffic_violation, customer_dispute, service_delay
            $table->string('severity', 32); // minor, moderate, severe, critical
            $table->string('status', 32)->default('reported'); // reported, investigating, action_required, resolved, closed
            $table->string('location_text', 255)->nullable();
            $table->dateTime('occurred_at');
            $table->dateTime('reported_at');
            $table->string('reported_by_name', 128)->nullable();
            $table->string('reported_by_role', 64)->nullable(); // customer, driver, staff, third_party
            $table->text('description');
            $table->boolean('police_report_filed')->default(false);
            $table->string('police_station', 128)->nullable();
            $table->string('police_report_number', 128)->nullable();
            $table->boolean('insurance_claim_filed')->default(false);
            $table->string('insurance_claim_reference', 128)->nullable();

            $table->integer('total_financial_impact_minor_units')->default(0);
            $table->string('currency', 3)->default('USD');

            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution_summary')->nullable();
            $table->dateTime('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['booking_id', 'status'], 'idx_incidents_booking_status');
            $table->index(['vehicle_id', 'occurred_at'], 'idx_incidents_vehicle_date');
            $table->index(['status', 'severity'], 'idx_incidents_status_severity');
        });

        Schema::create('incident_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incident_id')->constrained('incidents')->cascadeOnDelete();
            $table->string('action_type', 64); // status_change, note_added, communication, financial_charge, police_followup, resolved
            $table->text('description');
            $table->foreignId('performed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('performed_at');
            $table->timestamps();

            $table->index('incident_id', 'idx_incident_actions_incident');
        });

        Schema::create('incident_financial_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incident_id')->constrained('incidents')->cascadeOnDelete();
            $table->foreignId('payment_transaction_id')->nullable()->constrained('payment_transactions')->nullOnDelete();
            $table->string('entry_type', 32); // customer_deduction, customer_charge, third_party_recovery, insurance_payout, repair_cost
            $table->string('description', 255);
            $table->integer('amount_minor_units');
            $table->string('currency', 3)->default('USD');
            $table->string('original_currency', 3)->nullable();
            $table->integer('original_amount_minor_units')->nullable();
            $table->decimal('exchange_rate', 12, 6)->nullable();
            $table->string('status', 32)->default('pending'); // pending, recorded, settled, waived
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('incident_id', 'idx_inc_fin_incident');
            $table->index('payment_transaction_id', 'idx_inc_fin_payment');
        });

        Schema::create('incident_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incident_id')->constrained('incidents')->cascadeOnDelete();
            $table->string('category', 64); // vehicle_damage, scene_photo, third_party_vehicle, repair_estimate, police_report_pdf, insurance_claim_pdf
            $table->string('file_uuid', 64)->unique();
            $table->string('file_path', 255); // relative path on local disk e.g. incident_attachments/{uuid}.[jpg|pdf]
            $table->string('original_filename', 255);
            $table->string('mime_type', 64);
            $table->unsignedInteger('file_size_bytes');
            $table->string('caption', 128)->nullable();
            $table->string('processing_status', 32)->default('clean'); // quarantined, clean, failed, purged_retention
            $table->timestamps();

            $table->index('incident_id', 'idx_incident_attach_incident');
            $table->index('file_uuid', 'idx_incident_attach_uuid');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_attachments');
        Schema::dropIfExists('incident_financial_entries');
        Schema::dropIfExists('incident_actions');
        Schema::dropIfExists('incidents');
    }
};
```

---

#### Migration 3: Create Privacy, Legal Holds & Data Export Tables
`database/migrations/2026_05_01_000003_create_privacy_and_retention_tables.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal_holds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->restrictOnDelete();
            $table->string('reason', 255);
            $table->text('details')->nullable();
            $table->foreignId('applied_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('applied_at');
            $table->boolean('is_active')->default(true);
            $table->foreignId('released_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('released_at')->nullable();
            $table->text('release_reason')->nullable();
            $table->timestamps();

            $table->index(['booking_id', 'is_active'], 'idx_legal_holds_booking_active');
        });

        Schema::create('data_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_reference', 64)->unique(); // REQ-20260501-XXXX
            $table->string('request_type', 32); // access_export, correction, erasure_deletion, restriction
            $table->string('subject_email', 128)->index();
            $table->string('subject_phone', 32)->nullable();
            $table->string('subject_name', 128)->nullable();
            $table->string('verification_token_hash', 64)->nullable();
            $table->dateTime('verification_expires_at')->nullable();
            $table->string('status', 32)->default('pending_verification'); // pending_verification, verified, in_progress, completed, rejected
            $table->dateTime('verified_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('processed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'created_at'], 'idx_data_requests_status');
        });

        Schema::create('data_exports', function (Blueprint $table) {
            $table->id();
            $table->string('export_uuid', 64)->unique();
            $table->foreignId('requested_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('purpose', 255);
            $table->string('scope', 64); // customer_booking_history, financial_records, operations_audit
            $table->string('file_path', 255); // relative path on local disk e.g. exports/{uuid}.csv
            $table->unsignedInteger('file_size_bytes');
            $table->dateTime('expires_at');
            $table->dateTime('downloaded_at')->nullable();
            $table->unsignedInteger('download_count')->default(0);
            $table->timestamps();

            $table->index(['requested_by_user_id', 'expires_at'], 'idx_exports_user_expires');
            $table->index('export_uuid', 'idx_exports_uuid');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->dateTime('anonymized_at')->nullable()->after('completed_at')->index();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('anonymized_at');
        });

        Schema::dropIfExists('data_exports');
        Schema::dropIfExists('data_requests');
        Schema::dropIfExists('legal_holds');
    }
};
```

---

## 2. Enums & Value Objects

1. **`HandoverType`** (`app/Domain/Operations/Enums/HandoverType.php`):
   - `Collection = 'collection'`
   - `Return = 'return'`

2. **`HandoverStatus`** (`app/Domain/Operations/Enums/HandoverStatus.php`):
   - `Scheduled = 'scheduled'`
   - `DocumentsVerified = 'documents_verified'`
   - `ConditionCaptured = 'condition_captured'`
   - `SignaturesCaptured = 'signatures_captured'`
   - `Released = 'released'`
   - `ExceptionApproved = 'exception_approved'`
   - `Completed = 'completed'`
   - `Cancelled = 'cancelled'`

3. **`FuelLevel`** (`app/Domain/Operations/Enums/FuelLevel.php`):
   - `Empty = 'empty'` (0.00)
   - `Quarter = 'quarter'` (0.25)
   - `Half = 'half'` (0.50)
   - `ThreeQuarters = 'three_quarters'` (0.75)
   - `Full = 'full'` (1.00)

4. **`ConditionAttachmentCategory`** (`app/Domain/Operations/Enums/ConditionAttachmentCategory.php`):
   - `Odometer = 'odometer'`
   - `FuelGauge = 'fuel_gauge'`
   - `ExteriorScratch = 'exterior_scratch'`
   - `ExteriorDent = 'exterior_dent'`
   - `InteriorStain = 'interior_stain'`
   - `Windscreen = 'windscreen'`
   - `SpareWheel = 'spare_wheel'`
   - `Tools = 'tools'`
   - `OtherCondition = 'other_condition'`

5. **`IncidentType`** (`app/Domain/Operations/Enums/IncidentType.php`):
   - `Accident = 'accident'`
   - `Breakdown = 'breakdown'`
   - `Damage = 'damage'`
   - `Theft = 'theft'`
   - `TrafficViolation = 'traffic_violation'`
   - `CustomerDispute = 'customer_dispute'`
   - `ServiceDelay = 'service_delay'`

6. **`IncidentSeverity`** (`app/Domain/Operations/Enums/IncidentSeverity.php`):
   - `Minor = 'minor'`
   - `Moderate = 'moderate'`
   - `Severe = 'severe'`
   - `Critical = 'critical'`

7. **`IncidentStatus`** (`app/Domain/Operations/Enums/IncidentStatus.php`):
   - `Reported = 'reported'`
   - `Investigating = 'investigating'`
   - `ActionRequired = 'action_required'`
   - `Resolved = 'resolved'`
   - `Closed = 'closed'`

8. **`IncidentAttachmentCategory`** (`app/Domain/Operations/Enums/IncidentAttachmentCategory.php`):
   - `VehicleDamage = 'vehicle_damage'`
   - `ScenePhoto = 'scene_photo'`
   - `ThirdPartyVehicle = 'third_party_vehicle'`
   - `RepairEstimate = 'repair_estimate'`
   - `PoliceReportPdf = 'police_report_pdf'`
   - `InsuranceClaimPdf = 'insurance_claim_pdf'`

9. **`DataRequestType`** (`app/Domain/Audit/Enums/DataRequestType.php`):
   - `AccessExport = 'access_export'`
   - `Correction = 'correction'`
   - `ErasureDeletion = 'erasure_deletion'`
   - `Restriction = 'restriction'`

10. **`DataRequestStatus`** (`app/Domain/Audit/Enums/DataRequestStatus.php`):
    - `PendingVerification = 'pending_verification'`
    - `Verified = 'verified'`
    - `InProgress = 'in_progress'`
    - `Completed = 'completed'`
    - `Rejected = 'rejected'`

11. **`ExportScope`** (`app/Domain/Audit/Enums/ExportScope.php`):
    - `CustomerBookingHistory = 'customer_booking_history'`
    - `FinancialRecords = 'financial_records'`
    - `OperationsAudit = 'operations_audit'`

---

## 3. Core Domain Services

### 3.1 `HandoverService` (`app/Domain/Operations/Services/HandoverService.php`)
- **Lifecycle & State Machine**:
  - `Scheduled -> DocumentsVerified -> ConditionCaptured -> SignaturesCaptured -> Released`
- **Release Gate Validation**:
  - A collection handover cannot transition to `Released` unless:
    1. `documents_inspected === true`.
    2. If `driver_licence_country !== 'LK'`, `idp_or_aac_verified === true` and `driver_licence_expiry > now()`.
    3. `vehicle_condition_records` exists with valid odometer and fuel level.
    4. Both `customer_signature_path` and `staff_signature_path` are non-null and valid PNG files.
- **Four-Eyes Exception Authorization**:
  - If any release requirement fails, dispatch is strictly BLOCKED unless an authorized exception is recorded.
  - Requires:
    - User possessing `Permission::BookingOverrideConflict` (`booking.override_conflict`).
    - Authorizer `exception_authorized_by_user_id` MUST NOT match `staff_user_id`.
    - Non-empty `exception_reason`.
    - Logs structured `AuditService::record('handover.exception_authorized', ...)`.
- **`RentDetail` Synchronization**:
  - On collection completion, atomically updates linked `RentDetail`:
    - `original_documents_inspected = true`
    - `original_documents_inspected_at = now()`
    - `original_documents_inspected_by = auth()->id()`
    - `original_documents_inspection_notes = $notes`
- **Signature Storage**:
  - Accepts PNG Base64 data from mobile canvas.
  - Validates PNG header (`\x89PNG\r\n\x1a\n`) and dimensions (min 200x80, max 2000x800).
  - Writes binary file to `storage/app/private/signatures/{uuid}.png`.
  - Sets relative path on `handovers`.

---

### 3.2 `ConditionComparisonService` (`app/Domain/Operations/Services/ConditionComparisonService.php`)
- **Return Condition Comparison**:
  - Compares collection condition record with return condition record.
  - **Negative Odometer Protection**:
    - If `$returnKm < $collectionKm`, immediately throws `OdometerRollbackAnomalyException`.
    - Automatically creates a high-severity incident (`type: 'damage'`, description: "Odometer rollback or cluster tampering detected").
  - **0-KM Return Verification**:
    - If rental duration is >= 1 day and `$returnKm === $collectionKm`, asserts `$record->is_zero_km_verified === true` with valid `zero_km_reason`.
  - **Excess Mileage Calculation**:
    - `$excessKm = max(0, ($returnKm - $collectionKm) - $rentDetail->included_distance_km)`
    - `$excessChargeMinorUnits = $excessKm * $rentDetail->excess_mileage_rate_minor_units`
  - **Fuel Shortfall Calculation**:
    - Resolves numeric fractions from `FuelLevel`:
      - `Empty = 0.0, Quarter = 0.25, Half = 0.50, ThreeQuarters = 0.75, Full = 1.0`
    - `$shortfallFraction = max(0.0, $collectionFraction - $returnFraction)`
    - `$shortfallLitres = $shortfallFraction * ($vehicle->fuel_tank_capacity_litres ?? 50)`
    - `$fuelRatePerLitreMinorUnits = config('booking.rent.market_fuel_rate_minor_units', 350)` // e.g. $3.50/L
    - `$fuelCostMinorUnits = (int) round($shortfallLitres * $fuelRatePerLitreMinorUnits)`
  - **Smoking / Deep Cleaning Penalty**:
    - If `$returnCondition->smoking_detected === true`, appends fixed $150 USD cleaning penalty (`config('booking.rent.smoking_penalty_minor_units', 15000)`).
  - **Incident Deduplication Bridge**:
    - Queries `incident_financial_entries` linked to the booking. Any damages already recorded as an incident charge are flagged as `covered_by_incident_{id}` and excluded from the return condition invoice.

---

### 3.3 `PrivateMediaPipelineService` (`app/Domain/Operations/Services/PrivateMediaPipelineService.php`)
- **Dual Pipeline Architecture**:
  1. **RasterImagePipeline**:
     - Allowed MIME types: `image/jpeg`, `image/png`, `image/webp`.
     - Reject SVG, HTML, or polyglot files outright.
     - Size limit: 5 MB max.
     - Pre-allocation dimension validation via `getimagesize()`:
       `width <= 4096 && height <= 4096 && (width * height) <= 16_000_000` (prevents decompression memory bombs).
     - Read EXIF orientation with `exif_read_data()`, auto-rotate via `imagerotate()`.
     - Strict GD re-encode: re-draw image into clean truecolor canvas and output fresh JPEG/WebP buffer, stripping all EXIF and malicious script tails.
     - Store on `local` private disk at `condition_attachments/{uuid}.jpg` or `incident_attachments/{uuid}.jpg`.
  2. **DocumentPipeline** (Incident Police/Insurance Reports):
     - Allowed MIME: `application/pdf`.
     - Size limit: 10 MB max.
     - Validate `%PDF-` magic header bytes.
     - Scan raw stream for prohibited executable PDF tokens: `/JavaScript`, `/JS`, `/Launch`, `/Action`, `/EmbeddedFiles`.
     - Store on `local` private disk at `incident_attachments/{uuid}.pdf`.
- **Safe Streaming Download**:
  - Gated by `booking.view` or `incident.view`.
  - Sets safe headers:
    ```http
    Content-Type: application/octet-stream
    Content-Disposition: attachment; filename="attachment_<uuid>.<ext>"
    Content-Security-Policy: default-src 'none'; sandbox
    X-Content-Type-Options: nosniff
    Cache-Control: private, no-cache, no-store, must-revalidate
    Referrer-Policy: no-referrer
    ```
  - Logs `AuditService::record('media.private_file_downloaded', ...)`.

---

### 3.4 `IncidentService` (`app/Domain/Operations/Services/IncidentService.php`)
- Creates and updates incidents with sequence identifier `INC-YYYYMMDD-XXXX`.
- Authorization:
  - Viewing incidents requires `Permission::IncidentView` (`incident.view`).
  - Creating, updating, or closing incidents requires `Permission::IncidentManage` (`incident.manage`).
- Records chronological `incident_actions`.
- Multi-Entry Financial Movement:
  - Appends `incident_financial_entries` for each movement (`customer_deduction`, `customer_charge`, `repair_cost`, etc.).
  - Generates append-only `PaymentTransaction` records via `PaymentService::recordOfflinePayment()` or online checkout; never mutates settled records.
- Logs status transitions to central `AuditService::record('incident.status_changed', ...)`.

---

### 3.5 `PrivacyRetentionService` (`app/Domain/Audit/Services/PrivacyRetentionService.php`)
- **Pessimistic Row-Locking & Legal Hold Invariant**:
  - Anonymization and deletion operations execute inside atomic database transactions using `Booking::where('id', ...)->lockForUpdate()`.
  - Asserts `LegalHold::where('booking_id', $bookingId)->where('is_active', true)->doesntExist()`. If an active hold exists, skips processing completely.
- **Chunked Processing (`chunkById(100)`)**:
  - Queries `Booking::whereNull('anonymized_at')->whereIn('status', ['completed', 'cancelled'])->where(...)`.
  - Calculates 3-year retention threshold from `completed_at` or `cancelled_at` (never from `created_at`).
  - Cascades PII anonymization:
    - `booking_contacts`: Anonymizes full name (`"Anonymized Traveler"`), email (`"anonymized_{id}@redacted.invalid"`), phone (`"[REDACTED]"`).
    - `handovers`: Nullifies `customer_signature_path`, `customer_signer_name`, `staff_signature_path`, `document_inspection_notes`.
    - Physical attachments: Unlinks physical files from disk, marks `processing_status = 'purged_retention'`.
    - Marks `anonymized_at = now()`.
- **Ephemeral Cleanup**:
  - Purges guest and driver tokens expired > 30 days.
  - Purges expired temporary exports (> 24 hours): unlinks physical CSV files from private storage and updates record.
  - `legal_holds` are NEVER automatically purged; they remain permanent audit records until manually released.

---

### 3.6 `DataExportService` (`app/Domain/Audit/Services/DataExportService.php`)
- Generates scoped CSV export files in private disk storage (`exports/{uuid}.csv`).
- Scope & Permission Enforcement:
  - `CustomerBookingHistory`: requires `Permission::ExportCustomerData` (`export.customer_data`).
  - `FinancialRecords`: requires `Permission::ExportFinancialData` (`export.financial_data`).
  - `OperationsAudit`: requires `Permission::AuditExport` (`audit.export`).
- Sets `expires_at = now()->addHours(24)`.
- Download route verifies permissions, checks expiry, increments `download_count`, and streams via safe download headers.
- Logs every export generation and download in `AuditService`.

---

### 3.7 `PaymentService::recordOfflinePayment()` (`app/Domain/Payment/Services/PaymentService.php`)
- Extends `PaymentService` to record offline transactions cleanly:
  ```php
  public function recordOfflinePayment(
      Booking $booking,
      int $amountMinorUnits,
      PaymentMethod $method, // Cash, CardTerminal, BankTransfer
      PaymentType $type,     // Balance, Deposit, IncidentCharge, ConditionSettlement
      User $recordedBy,
      string $notes,
      ?array $metadata = null,
  ): PaymentTransaction
  ```
- Enforces `Permission::PaymentRecordOffline` (`payment.record_offline`).
- Generates unique reference `TXN-OFF-...` and idempotency key.
- Creates append-only `PaymentTransaction` with status `Completed`.
- Logs structured `AuditService::record('payment.offline_recorded', ...)`.

