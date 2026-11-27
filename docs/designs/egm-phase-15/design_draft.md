# Spinellanka Phase 15: Rent Field Operations, Incidents, Private Attachments & Privacy Governance
## Technical Design Draft (EGM Phase 15 Draft)

**Branch**: `phase/15-rent-field-incidents-privacy`  
**Base**: `main`  
**Timezone**: `Asia/Colombo` for business operational schedules; UTC for infrastructure/audit events.

---

## 1. Database Schema & Migrations

### Migration 1: Create Handover & Vehicle Condition Tables
`database/migrations/2026_05_01_000001_create_handover_and_condition_tables.php`

```php
Schema::create('handovers', function (Blueprint $table) {
    $table->id();
    $table->foreignId('booking_id')->constrained('bookings')->restrictOnDelete();
    $table->foreignId('rent_detail_id')->constrained('rent_details')->restrictOnDelete();
    $table->foreignId('vehicle_id')->constrained('vehicles')->restrictOnDelete();
    $table->string('type', 32); // collection, return
    $table->string('status', 32)->default('scheduled'); // scheduled, completed, cancelled, exception_approved
    $table->dateTime('scheduled_at');
    $table->dateTime('completed_at')->nullable();
    $table->foreignId('staff_user_id')->nullable()->constrained('users')->nullOnDelete();
    $table->string('location_type', 32); // office, airport, hotel, custom
    $table->string('location_text', 255);
    
    // Original-document physical inspection result (NO UPLOADS)
    $table->boolean('documents_inspected')->default(false);
    $table->string('inspected_doc_types', 128)->nullable(); // e.g. "passport,national_licence,idp"
    $table->string('driver_licence_country', 64)->nullable();
    $table->dateTime('driver_licence_expiry')->nullable();
    $table->boolean('idp_or_aac_verified')->default(false);
    $table->text('document_inspection_notes')->nullable();
    
    // Signatures & Consent
    $table->text('customer_signature_data')->nullable(); // Base64 PNG/data URI
    $table->string('customer_signer_name', 128)->nullable();
    $table->dateTime('customer_signed_at')->nullable();
    $table->text('staff_signature_data')->nullable();
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
});

Schema::create('vehicle_condition_records', function (Blueprint $table) {
    $table->id();
    $table->foreignId('handover_id')->constrained('handovers')->cascadeOnDelete();
    $table->foreignId('booking_id')->constrained('bookings')->restrictOnDelete();
    $table->foreignId('vehicle_id')->constrained('vehicles')->restrictOnDelete();
    $table->string('record_type', 32); // collection, return
    $table->integer('odometer_km');
    $table->string('fuel_level', 32); // empty, quarter, half, three_quarters, full
    $table->json('accessories_checklist')->nullable(); // spare_tire, jack, tool_kit, first_aid, dash_cam
    $table->boolean('is_clean')->default(true);
    $table->boolean('smoking_detected')->default(false);
    $table->text('exterior_condition_notes')->nullable();
    $table->text('interior_condition_notes')->nullable();
    $table->dateTime('recorded_at');
    $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();

    $table->index('handover_id', 'idx_cond_handover');
    $table->index('booking_id', 'idx_cond_booking');
});

Schema::create('condition_attachments', function (Blueprint $table) {
    $table->id();
    $table->foreignId('vehicle_condition_record_id')->constrained('vehicle_condition_records')->cascadeOnDelete();
    $table->string('file_uuid', 64)->unique();
    $table->string('file_path', 255); // stored in storage/app/private/condition_attachments/
    $table->string('original_filename', 255);
    $table->string('mime_type', 64);
    $table->unsignedInteger('file_size_bytes');
    $table->string('caption', 128)->nullable();
    $table->string('processing_status', 32)->default('clean'); // quarantined, clean, failed
    $table->dateTime('captured_at');
    $table->timestamps();

    $table->index('vehicle_condition_record_id', 'idx_cond_attach_record');
});
```

---

### Migration 2: Create Incidents & Actions Tables
`database/migrations/2026_05_01_000002_create_incidents_and_actions_tables.php`

```php
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
    
    // Financial Impact
    $table->integer('financial_impact_minor_units')->default(0);
    $table->string('currency', 3)->default('USD');
    $table->foreignId('linked_payment_transaction_id')->nullable()->constrained('payment_transactions')->nullOnDelete();
    
    $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
    $table->text('resolution_summary')->nullable();
    $table->dateTime('resolved_at')->nullable();
    $table->timestamps();

    $table->index(['booking_id', 'status'], 'idx_incidents_booking_status');
    $table->index(['vehicle_id', 'occurred_at'], 'idx_incidents_vehicle_date');
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

Schema::create('incident_attachments', function (Blueprint $table) {
    $table->id();
    $table->foreignId('incident_id')->constrained('incidents')->cascadeOnDelete();
    $table->string('file_uuid', 64)->unique();
    $table->string('file_path', 255); // stored in storage/app/private/incident_attachments/
    $table->string('original_filename', 255);
    $table->string('mime_type', 64);
    $table->unsignedInteger('file_size_bytes');
    $table->string('caption', 128)->nullable();
    $table->string('processing_status', 32)->default('clean'); // quarantined, clean, failed
    $table->timestamps();

    $table->index('incident_id', 'idx_incident_attach_incident');
});
```

---

### Migration 3: Create Privacy, Legal Holds & Data Export Tables
`database/migrations/2026_05_01_000003_create_privacy_and_retention_tables.php`

```php
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
    $table->string('file_path', 255); // storage/app/private/exports/
    $table->unsignedInteger('file_size_bytes');
    $table->dateTime('expires_at');
    $table->dateTime('downloaded_at')->nullable();
    $table->unsignedInteger('download_count')->default(0);
    $table->timestamps();

    $table->index(['requested_by_user_id', 'expires_at'], 'idx_exports_user_expires');
});
```

---

## 2. Core Domain Services

1. **`HandoverService`** (`app/Domain/Operations/Services/HandoverService.php`):
   - Manages collection and return records.
   - Enforces Release Gates: blocks vehicle dispatch until original documents pass inspection, collection condition is signed, or an audited exception is logged with user authorization.
   - Records customer & staff signatures securely.

2. **`ConditionComparisonService`** (`app/Domain/Operations/Services/ConditionComparisonService.php`):
   - Compares return condition with collection condition.
   - Computes:
     - Excess mileage: `max(0, ($returnKm - $collectionKm) - $includedKm) * $rateMinorUnits`.
     - Fuel shortfall: Compares discrete tank fractions and calculates refill cost.
     - Smoking/Cleaning penalty: Enforces fixed $150 USD cleaning penalty if flagged.
   - Proposes settlement itemization without modifying settled ledger.

3. **`PrivateMediaPipelineService`** (`app/Domain/Operations/Services/PrivateMediaPipelineService.php`):
   - Validates MIME type, extension, size limit (<= 5MB).
   - Re-encodes images strictly using GD (`imagecreatefromjpeg`/`imagecreatefrompng`/`imagecreatefromwebp` + `imagejpeg`) to strip all malicious code, polyglots, and EXIF metadata.
   - Writes clean image to `storage/app/private/{directory}/{uuid}.jpg`.
   - Generates download responses with `nosniff`, `no-referrer`, `attachment` disposition, and audits downloads.

4. **`IncidentService`** (`app/Domain/Operations/Services/IncidentService.php`):
   - Creates and updates incident reports with unique reference `INC-...`.
   - Enforces permissions `incident.view` and `incident.manage`.
   - Records structured timeline actions and links financial charges through `PaymentService`.

5. **`PrivacyRetentionService`** (`app/Domain/Audit/Services/PrivacyRetentionService.php`):
   - Purges expired tokens (> 30 days expired).
   - Purges stale holds (> 7 days expired).
   - Unlinks expired export files (> 24 hours).
   - Anonymizes booking contact details on completed/cancelled bookings older than 3 years **only if no active legal hold exists** (`LegalHold::where('booking_id', ...)->where('is_active', true)`).
   - Dispatches transactional outbox audit events for all retention passes.

6. **`DataExportService`** (`app/Domain/Audit/Services/DataExportService.php`):
   - Exports scoped CSVs (`CustomerBookingHistory`, `FinancialRecords`, `OperationsAudit`).
   - Requires explicit purpose declaration and permissions (`export.customer_data`, `export.financial_data`).
   - Sets 24-hour expiration on exported files and logs every download event in `audit_events`.

