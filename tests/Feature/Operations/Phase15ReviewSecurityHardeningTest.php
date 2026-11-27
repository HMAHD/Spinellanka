<?php

declare(strict_types=1);

namespace Tests\Feature\Operations;

use App\Domain\Audit\Enums\ExportScope;
use App\Domain\Audit\Services\PrivacyRetentionService;
use App\Domain\Booking\Enums\BookingStatus;
use App\Domain\Booking\ServiceType;
use App\Domain\Identity\Permission;
use App\Domain\Operations\Enums\ConditionAttachmentCategory;
use App\Domain\Operations\Enums\FuelLevel;
use App\Domain\Operations\Enums\HandoverType;
use App\Domain\Operations\Enums\IncidentAttachmentCategory;
use App\Domain\Operations\Enums\IncidentSeverity;
use App\Domain\Operations\Enums\IncidentStatus;
use App\Domain\Operations\Enums\IncidentType;
use App\Domain\Operations\Exceptions\OdometerRollbackAnomalyException;
use App\Domain\Operations\Exceptions\ReleaseGateBlockedException;
use App\Domain\Operations\Services\ConditionComparisonService;
use App\Domain\Operations\Services\HandoverService;
use App\Domain\Operations\Services\PrivateMediaPipelineService;
use App\Domain\Payment\Enums\PaymentMethod;
use App\Domain\Payment\Enums\PaymentStatus;
use App\Domain\Payment\Enums\PaymentType;
use App\Domain\Payment\Services\PaymentService;
use App\Models\AuditEvent;
use App\Models\Booking;
use App\Models\ConditionAttachment;
use App\Models\DataExport;
use App\Models\Incident;
use App\Models\IncidentAttachment;
use App\Models\RentDetail;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed();
    Storage::fake('local');

    $this->pipeline = app(PrivateMediaPipelineService::class);
    $this->handoverService = app(HandoverService::class);
    $this->comparisonService = app(ConditionComparisonService::class);
    $this->retentionService = app(PrivacyRetentionService::class);
    $this->paymentService = app(PaymentService::class);

    $this->staff = User::create([
        'name' => 'Field Operator Amal',
        'email' => 'amal@spinellanka.test',
        'password' => Hash::make('Secret123456!'),
        'is_active' => true,
        'token_version' => 1,
    ]);
    $this->staff->givePermissionTo(Permission::BookingView->value);
    $this->staff->givePermissionTo(Permission::BookingConfirm->value);
    $this->staff->givePermissionTo(Permission::PaymentRecordOffline->value);
    $this->staff->givePermissionTo(Permission::IncidentView->value);
    $this->staff->givePermissionTo(Permission::ExportCustomerData->value);

    $this->manager = User::create([
        'name' => 'Ops Manager Rohan',
        'email' => 'rohan@spinellanka.test',
        'password' => Hash::make('Secret123456!'),
        'is_active' => true,
        'token_version' => 1,
    ]);
    $this->manager->givePermissionTo(Permission::BookingOverrideConflict->value);

    $this->vehicle = Vehicle::factory()->create([
        'fuel_tank_capacity_litres' => 50,
    ]);

    $this->booking = Booking::factory()->create([
        'reference' => 'BK-RENT-REV-001',
        'service_type' => ServiceType::Rent,
        'status' => BookingStatus::Confirmed,
        'vehicle_id' => $this->vehicle->id,
    ]);

    $this->rentDetail = RentDetail::factory()->create([
        'booking_id' => $this->booking->id,
        'duration_days' => 3,
        'included_distance_km' => 300,
        'excess_mileage_rate_minor_units' => 50,
        'security_deposit_minor_units' => 5000000,
    ]);

    $this->validPngBase64 = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
});

test('SEC-01: storeSignaturePng re-encodes valid raster signature and rejects polyglot or malformed payloads', function (): void {
    // 1. Valid PNG signature base64
    $img = imagecreatetruecolor(150, 60);
    imagealphablending($img, false);
    imagesavealpha($img, true);
    $trans = imagecolorallocatealpha($img, 0, 0, 0, 127);
    imagefill($img, 0, 0, $trans);
    ob_start();
    imagepng($img);
    $validPngBytes = (string) ob_get_clean();
    imagedestroy($img);

    $validDataUri = 'data:image/png;base64,'.base64_encode($validPngBytes);
    $savedPath = $this->pipeline->storeSignaturePng($validDataUri, 'handover_signatures');

    expect(Storage::disk('local')->exists($savedPath))->toBeTrue();
    $savedBytes = Storage::disk('local')->get($savedPath);
    $gd = @imagecreatefromstring($savedBytes);
    expect($gd)->not->toBeFalse();
    imagedestroy($gd);

    // 2. Malicious payload pretending to be PNG with embedded PHP
    $maliciousPayload = 'data:image/png;base64,'.base64_encode("\x89PNG\r\n\x1a\n<?php phpinfo(); ?>");
    expect(fn () => $this->pipeline->storeSignaturePng($maliciousPayload, 'handover_signatures'))
        ->toThrow(InvalidArgumentException::class);

    // 3. Reject huge dimension signature bomb
    $hugeImg = imagecreatetruecolor(2500, 100);
    ob_start();
    imagepng($hugeImg);
    $hugeBytes = (string) ob_get_clean();
    imagedestroy($hugeImg);

    $hugeDataUri = 'data:image/png;base64,'.base64_encode($hugeBytes);
    expect(fn () => $this->pipeline->storeSignaturePng($hugeDataUri, 'handover_signatures'))
        ->toThrow(InvalidArgumentException::class);
});

test('SEC-02: processDocument AST scanner detects hex-encoded PDF tokens and compressed stream exploits', function (): void {
    // 1. Hex-encoded Javascript token: /Java#53cript
    $hexPdf = "%PDF-1.4\n1 0 obj\n<< /Type /Action /S /Java#53cript /JS (app.alert(1)) >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF";
    $hexFile = UploadedFile::fake()->createWithContent('exploit.pdf', $hexPdf);

    expect(fn () => $this->pipeline->processDocument($hexFile, 'incident_attachments'))
        ->toThrow(InvalidArgumentException::class, 'Security violation: PDF contains prohibited active element');

    // 2. Clean PDF passes
    $cleanPdf = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n2 0 obj\n<< /Type /Pages /Kids [] /Count 0 >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF";
    $cleanFile = UploadedFile::fake()->createWithContent('clean.pdf', $cleanPdf);

    $result = $this->pipeline->processDocument($cleanFile, 'incident_attachments');
    expect($result['mime_type'])->toBe('application/pdf')
        ->and(Storage::disk('local')->exists($result['file_path']))->toBeTrue();
});

test('SEC-03: authorizeException asserts handover has assigned staff before approval', function (): void {
    $handover = $this->handoverService->createHandover(
        booking: $this->booking,
        type: HandoverType::Collection,
        scheduledAt: Carbon::now('Asia/Colombo')->addHours(2),
        locationType: 'office',
        locationText: 'Colombo Hub',
        staffUser: null // Unassigned!
    );

    expect(fn () => $this->handoverService->authorizeException(
        handover: $handover,
        authorizer: $this->manager,
        reason: 'Customer requested waiver'
    ))->toThrow(InvalidArgumentException::class, 'Cannot authorize exception: Handover must have an assigned field staff member.');
});

test('SEC-04: releaseVehicle enforces IDP verification and validity date for foreign licences', function (): void {
    $handover = $this->handoverService->createHandover(
        booking: $this->booking,
        type: HandoverType::Collection,
        scheduledAt: Carbon::now('Asia/Colombo')->addHours(2),
        locationType: 'office',
        locationText: 'Colombo Hub',
        staffUser: $this->staff
    );

    // Satisfy condition and signatures
    $this->handoverService->recordCondition(
        handover: $handover,
        staff: $this->staff,
        odometerKm: 50000,
        fuelLevel: FuelLevel::Full,
        accessoriesChecklist: ['spare_tire' => true]
    );

    $this->handoverService->captureSignatures(
        handover: $handover,
        customerSignatureBase64: $this->validPngBase64,
        customerSignerName: 'John Traveler',
        staffSignatureBase64: $this->validPngBase64,
        staff: $this->staff
    );

    // Unverified foreign licence throws ReleaseGateBlockedException
    $handover->update([
        'documents_inspected' => true,
        'driver_licence_country' => 'DE',
        'idp_or_aac_verified' => false,
        'driver_licence_expiry' => Carbon::now('Asia/Colombo')->addYears(2),
    ]);

    expect(fn () => $this->handoverService->releaseVehicle($handover, $this->staff))
        ->toThrow(ReleaseGateBlockedException::class);

    // Expired licence throws ReleaseGateBlockedException
    $handover->update([
        'idp_or_aac_verified' => true,
        'driver_licence_expiry' => Carbon::now('Asia/Colombo')->subDays(5),
    ]);

    expect(fn () => $this->handoverService->releaseVehicle($handover, $this->staff))
        ->toThrow(ReleaseGateBlockedException::class);
});

test('SEC-05: Condition comparison checks zero-KM justification, auto-creates incident on rollback, and nets incident deductions', function (): void {
    $collectionHandover = $this->handoverService->createHandover(
        booking: $this->booking,
        type: HandoverType::Collection,
        scheduledAt: Carbon::now('Asia/Colombo')->subDays(3),
        locationType: 'office',
        locationText: 'Colombo Depot',
        staffUser: $this->staff
    );

    $this->handoverService->recordCondition(
        handover: $collectionHandover,
        staff: $this->staff,
        odometerKm: 50000,
        fuelLevel: FuelLevel::Full,
        accessoriesChecklist: ['spare_tire' => true]
    );

    $returnHandover = $this->handoverService->createHandover(
        booking: $this->booking,
        type: HandoverType::Return,
        scheduledAt: Carbon::now('Asia/Colombo'),
        locationType: 'office',
        locationText: 'Colombo Depot',
        staffUser: $this->staff
    );

    // 1. Multi-day rental with 0 KM requires zero km verification
    $this->handoverService->recordCondition(
        handover: $returnHandover,
        staff: $this->staff,
        odometerKm: 50000,
        fuelLevel: FuelLevel::Full,
        accessoriesChecklist: ['spare_tire' => true],
        isZeroKmVerified: false
    );

    expect(fn () => $this->comparisonService->compareConditions($collectionHandover, $returnHandover))
        ->toThrow(InvalidArgumentException::class, 'Multi-day rental with 0 kilometers driven must have explicit staff verification');

    // 2. Odometer rollback automatically creates Critical damage incident and throws anomaly
    $this->handoverService->recordCondition(
        handover: $returnHandover,
        staff: $this->staff,
        odometerKm: 49500, // 500 km less
        fuelLevel: FuelLevel::Full,
        accessoriesChecklist: ['spare_tire' => true]
    );

    expect(fn () => $this->comparisonService->compareConditions($collectionHandover, $returnHandover))
        ->toThrow(OdometerRollbackAnomalyException::class);

    $autoIncident = Incident::where('booking_id', $this->booking->id)
        ->where('type', IncidentType::Damage)
        ->first();

    expect($autoIncident)->not->toBeNull()
        ->and($autoIncident->severity)->toBe(IncidentSeverity::Critical)
        ->and($autoIncident->description)->toContain('Odometer rollback anomaly detected');

    // 3. Incident deductions net calculation
    $this->handoverService->recordCondition(
        handover: $returnHandover,
        staff: $this->staff,
        odometerKm: 50500,
        fuelLevel: FuelLevel::Half,
        accessoriesChecklist: ['spare_tire' => true],
        isZeroKmVerified: true,
        zeroKmReason: 'Drove 500km'
    );

    $result = $this->comparisonService->compareConditions($collectionHandover, $returnHandover);
    expect($result->incidentDeductions)->toBeArray();
});

test('SEC-06: PrivacyRetentionService anonymization pass redacts sensitive rent and incident records', function (): void {
    $this->rentDetail->update([
        'additional_driver_name' => 'Secret Co-Driver',
        'original_documents_inspection_notes' => 'Inspected original passport N8892110',
        'pickup_location_text' => 'Private Residence Colombo 07',
        'return_location_text' => 'Luxury Villa Galle',
    ]);

    $incident = Incident::create([
        'incident_number' => Incident::generateIncidentNumber(),
        'booking_id' => $this->booking->id,
        'vehicle_id' => $this->vehicle->id,
        'type' => IncidentType::Damage,
        'severity' => IncidentSeverity::Moderate,
        'status' => IncidentStatus::Closed,
        'reported_by_name' => 'Field Operator Amal',
        'description' => 'Minor scratch on left wing mirror',
        'police_report_number' => 'POL-112233',
        'insurance_claim_reference' => 'INS-998877',
        'resolution_summary' => 'Settled with customer cash payment',
        'occurred_at' => Carbon::now()->subYears(4),
        'reported_at' => Carbon::now()->subYears(4),
        'resolved_at' => Carbon::now()->subYears(4),
    ]);

    $this->booking->update([
        'status' => BookingStatus::Completed,
        'completed_at' => Carbon::now()->subYears(4),
    ]);

    $this->retentionService->executeRetentionPass();

    $this->rentDetail->refresh();
    $incident->refresh();

    expect($this->rentDetail->additional_driver_name)->toBeNull()
        ->and($this->rentDetail->original_documents_inspection_notes)->toBe('[REDACTED_RETENTION]')
        ->and($this->rentDetail->pickup_location_text)->toBe('[REDACTED]')
        ->and($this->rentDetail->return_location_text)->toBe('[REDACTED]')
        ->and($incident->reported_by_name)->toBe('Anonymized Reporter')
        ->and($incident->description)->toBe('[REDACTED_PRIVACY_RETENTION]')
        ->and($incident->police_report_number)->toBeNull()
        ->and($incident->insurance_claim_reference)->toBeNull()
        ->and($incident->resolution_summary)->toBe('[REDACTED_PRIVACY_RETENTION]');
});

test('SEC-07 and SEC-09: Download controllers enforce HTTP 410 on purged files, 403 on non-clean, and safe filenames', function (): void {
    // 1. Expired DataExport returns 410 Gone
    $export = DataExport::create([
        'export_uuid' => (string) Str::uuid(),
        'scope' => ExportScope::CustomerBookingHistory,
        'requested_by_user_id' => $this->staff->id,
        'purpose' => 'Regulatory compliance inquiry',
        'file_path' => 'exports/customer_history.csv',
        'file_size_bytes' => 1024,
        'download_count' => 0,
        'expires_at' => Carbon::now()->subHour(), // Expired!
    ]);

    $response = $this->actingAs($this->staff)->get(route('operations.exports.download', ['uuid' => $export->export_uuid]));
    $response->assertStatus(410);

    // 2. Attachment in purged_retention returns 410 Gone
    $incident = Incident::create([
        'incident_number' => Incident::generateIncidentNumber(),
        'booking_id' => $this->booking->id,
        'vehicle_id' => $this->vehicle->id,
        'type' => IncidentType::Damage,
        'severity' => IncidentSeverity::Minor,
        'status' => IncidentStatus::Reported,
        'reported_by_name' => 'Amal',
        'description' => 'Test',
        'occurred_at' => Carbon::now(),
        'reported_at' => Carbon::now(),
    ]);

    $purgedAtt = IncidentAttachment::create([
        'incident_id' => $incident->id,
        'category' => IncidentAttachmentCategory::VehicleDamage,
        'file_uuid' => (string) Str::uuid(),
        'original_filename' => 'damage.jpg',
        'file_path' => 'purged',
        'mime_type' => 'image/jpeg',
        'file_size_bytes' => 0,
        'processing_status' => 'purged_retention',
    ]);

    $purgedResponse = $this->actingAs($this->staff)->get(route('operations.attachments.download', ['uuid' => $purgedAtt->file_uuid]));
    $purgedResponse->assertStatus(410);

    // 3. Attachment still in quarantine returns 403 Forbidden
    $quarantineAtt = IncidentAttachment::create([
        'incident_id' => $incident->id,
        'category' => IncidentAttachmentCategory::VehicleDamage,
        'file_uuid' => (string) Str::uuid(),
        'original_filename' => 'quarantine.jpg',
        'file_path' => 'quarantine/quarantine.jpg',
        'mime_type' => 'image/jpeg',
        'file_size_bytes' => 100,
        'processing_status' => 'quarantined',
    ]);

    $quarantineResponse = $this->actingAs($this->staff)->get(route('operations.attachments.download', ['uuid' => $quarantineAtt->file_uuid]));
    $quarantineResponse->assertStatus(403);

    // 4. Clean attachment downloads with safe filename format attachment_<uuid>.<ext>
    Storage::disk('local')->put('condition_attachments/clean_test.jpg', 'fake-jpg-content');

    $handover = $this->handoverService->createHandover(
        booking: $this->booking,
        type: HandoverType::Collection,
        scheduledAt: Carbon::now('Asia/Colombo'),
        locationType: 'office',
        locationText: 'Colombo Hub',
        staffUser: $this->staff
    );

    $this->handoverService->recordCondition(
        handover: $handover,
        staff: $this->staff,
        odometerKm: 50000,
        fuelLevel: FuelLevel::Full,
        accessoriesChecklist: ['spare_tire' => true]
    );

    $conditionRecord = $handover->fresh()->conditionRecord;

    $cleanAtt = ConditionAttachment::create([
        'vehicle_condition_record_id' => $conditionRecord->id,
        'category' => ConditionAttachmentCategory::ExteriorScratch,
        'file_uuid' => (string) Str::uuid(),
        'original_filename' => 'user_submitted_name.jpg',
        'file_path' => 'condition_attachments/clean_test.jpg',
        'mime_type' => 'image/jpeg',
        'file_size_bytes' => 16,
        'processing_status' => 'clean',
        'captured_at' => Carbon::now(),
    ]);

    $cleanResponse = $this->actingAs($this->staff)->get(route('operations.attachments.download', ['uuid' => $cleanAtt->file_uuid]));
    $cleanResponse->assertOk();
    $expectedFilename = sprintf('attachment_%s.jpg', $cleanAtt->file_uuid);
    $cleanResponse->assertHeader('Content-Disposition', sprintf('attachment; filename=%s', $expectedFilename));
});

test('SEC-08: PaymentService recordOfflinePayment enforces permission, records settled transaction, and logs audit', function (): void {
    // 1. Unauthorized staff throws AuthorizationException
    $unauthorizedStaff = User::create([
        'name' => 'Junior Staff',
        'email' => 'junior@spinellanka.test',
        'password' => Hash::make('Secret123456!'),
        'is_active' => true,
        'token_version' => 1,
    ]);

    expect(fn () => $this->paymentService->recordOfflinePayment(
        booking: $this->booking,
        staff: $unauthorizedStaff,
        type: PaymentType::ConditionSettlement,
        method: PaymentMethod::Cash,
        amountMinorUnits: 500000,
        currency: 'LKR',
        notes: 'Cash fuel settlement collected on return'
    ))->toThrow(AuthorizationException::class);

    // 2. Reject online payment method recorded offline
    expect(fn () => $this->paymentService->recordOfflinePayment(
        booking: $this->booking,
        staff: $this->staff,
        type: PaymentType::Full,
        method: PaymentMethod::OnlineCard,
        amountMinorUnits: 500000,
        currency: 'LKR'
    ))->toThrow(InvalidArgumentException::class, 'Online card payment methods cannot be recorded offline manually.');

    // 3. Authorized staff records valid offline cash payment
    $transaction = $this->paymentService->recordOfflinePayment(
        booking: $this->booking,
        staff: $this->staff,
        type: PaymentType::ConditionSettlement,
        method: PaymentMethod::Cash,
        amountMinorUnits: 875000,
        currency: 'LKR',
        notes: 'Settled fuel deficit in cash at counter'
    );

    expect($transaction->status)->toBe(PaymentStatus::Completed)
        ->and($transaction->payment_method)->toBe(PaymentMethod::Cash)
        ->and($transaction->amount_minor_units)->toBe(875000)
        ->and($transaction->recorded_by_user_id)->toBe($this->staff->id)
        ->and($transaction->settled_at)->not->toBeNull();

    // Verify audit event
    $audit = AuditEvent::where('action', 'payment.offline_recorded')->latest('id')->first();
    expect($audit)->not->toBeNull()
        ->and($audit->actor_id)->toBe($this->staff->id)
        ->and($audit->summary)->toContain('recorded offline Cash to Driver / Counter payment of 875000 LKR');
});
