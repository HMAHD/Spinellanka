<?php

declare(strict_types=1);

namespace Tests\Feature\Operations;

use App\Domain\Booking\Enums\BookingStatus;
use App\Domain\Booking\ServiceType;
use App\Domain\Identity\Permission;
use App\Domain\Operations\Enums\IncidentAttachmentCategory;
use App\Domain\Operations\Enums\IncidentFinancialEntryType;
use App\Domain\Operations\Enums\IncidentFinancialStatus;
use App\Domain\Operations\Enums\IncidentSeverity;
use App\Domain\Operations\Enums\IncidentStatus;
use App\Domain\Operations\Enums\IncidentType;
use App\Domain\Operations\Services\IncidentService;
use App\Models\AuditEvent;
use App\Models\Booking;
use App\Models\Incident;
use App\Models\IncidentAction;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed();
    $this->incidentService = app(IncidentService::class);
    Storage::fake('local');

    $this->manager = User::create([
        'name' => 'Safety Officer Bandara',
        'email' => 'safety.bandara@spinellanka.test',
        'password' => Hash::make('Secret123456!'),
        'is_active' => true,
        'token_version' => 1,
    ]);
    $this->manager->givePermissionTo(Permission::IncidentView->value);
    $this->manager->givePermissionTo(Permission::IncidentManage->value);

    $this->viewerOnly = User::create([
        'name' => 'Readonly Auditor',
        'email' => 'auditor@spinellanka.test',
        'password' => Hash::make('Secret123456!'),
        'is_active' => true,
        'token_version' => 1,
    ]);
    $this->viewerOnly->givePermissionTo(Permission::IncidentView->value);

    $this->vehicle = Vehicle::factory()->create();
    $this->booking = Booking::factory()->create([
        'reference' => 'BK-INC-TEST-001',
        'service_type' => ServiceType::Rent,
        'status' => BookingStatus::Confirmed,
        'vehicle_id' => $this->vehicle->id,
    ]);
});

test('createIncident enforces incident.manage permission and initializes audit and action trails', function (): void {
    // Staff without incident.manage cannot create incident
    expect(fn () => $this->incidentService->createIncident($this->viewerOnly, [
        'vehicle_id' => $this->vehicle->id,
        'incident_type' => IncidentType::Breakdown,
        'severity' => IncidentSeverity::Moderate,
        'occurred_at' => Carbon::now('Asia/Colombo')->subHours(2),
        'location_text' => 'Kandy Highway',
        'title' => 'Alternator failure',
        'description' => 'Vehicle lost power near Kadawatha',
    ]))->toThrow(AuthorizationException::class, 'Actor does not have incident.manage permission');

    // Authorized staff creates incident successfully
    $incident = $this->incidentService->createIncident($this->manager, [
        'booking_id' => $this->booking->id,
        'vehicle_id' => $this->vehicle->id,
        'incident_type' => IncidentType::Damage,
        'severity' => IncidentSeverity::Severe,
        'occurred_at' => Carbon::now('Asia/Colombo')->subHours(3),
        'location_text' => 'Colombo 03',
        'title' => 'Front bumper collision',
        'description' => 'Minor collision with bollard in parking bay',
    ]);

    expect($incident->id)->not->toBeNull()
        ->and($incident->reference)->toStartWith('INC-')
        ->and($incident->status)->toBe(IncidentStatus::Reported)
        ->and($incident->severity)->toBe(IncidentSeverity::Severe);

    // Initial timeline action created
    expect(IncidentAction::where('incident_id', $incident->id)->where('action_type', 'incident_created')->exists())->toBeTrue();

    // Audit event created
    expect(AuditEvent::where('action', 'incident.created')->where('auditable_id', $incident->id)->exists())->toBeTrue();
});

test('updateStatus transitions incident lifecycle with reason and timeline logging', function (): void {
    $incident = $this->incidentService->createIncident($this->manager, [
        'booking_id' => $this->booking->id,
        'vehicle_id' => $this->vehicle->id,
        'incident_type' => IncidentType::Damage,
        'severity' => IncidentSeverity::Moderate,
        'occurred_at' => Carbon::now('Asia/Colombo')->subHours(2),
        'location_text' => 'Galle Road',
        'title' => 'Scratched door',
        'description' => 'Key scratch on passenger door',
    ]);

    $updatedIncident = $this->incidentService->updateStatus(
        incident: $incident,
        staff: $this->manager,
        newStatus: IncidentStatus::Investigating,
        reason: 'Contacting authorized workshop for repair quotation'
    );

    expect($updatedIncident->status)->toBe(IncidentStatus::Investigating);

    // Verify action recorded
    $action = IncidentAction::where('incident_id', $incident->id)->where('action_type', 'status_changed')->latest('id')->first();
    expect($action)->not->toBeNull()
        ->and(strtolower($action->description))->toContain('investigating')
        ->and($action->description)->toContain('Contacting authorized workshop');
});

test('recordFinancialEntry creates multi-entry movements on incident ledger', function (): void {
    $incident = $this->incidentService->createIncident($this->manager, [
        'booking_id' => $this->booking->id,
        'vehicle_id' => $this->vehicle->id,
        'incident_type' => IncidentType::Damage,
        'severity' => IncidentSeverity::Severe,
        'occurred_at' => Carbon::now('Asia/Colombo')->subHours(2),
        'location_text' => 'Negombo',
        'title' => 'Windscreen cracked',
        'description' => 'Rock chip cracked windshield',
    ]);

    $entry = $this->incidentService->recordFinancialEntry(
        incident: $incident,
        staff: $this->manager,
        type: IncidentFinancialEntryType::RepairCost,
        description: 'Toyota Lanka genuine OEM windshield replacement quotation',
        amountMinorUnits: 25000,
        currency: 'USD'
    );

    expect($entry->id)->not->toBeNull()
        ->and($entry->entry_type)->toBe(IncidentFinancialEntryType::RepairCost)
        ->and($entry->amount_minor_units)->toBe(25000)
        ->and($entry->status)->toBe(IncidentFinancialStatus::Recorded);

    expect(AuditEvent::where('action', 'incident.financial_entry_added')->exists())->toBeTrue();
});

test('attachMedia securely processes and links private evidence to incident', function (): void {
    $incident = $this->incidentService->createIncident($this->manager, [
        'booking_id' => $this->booking->id,
        'vehicle_id' => $this->vehicle->id,
        'incident_type' => IncidentType::Accident,
        'severity' => IncidentSeverity::Critical,
        'occurred_at' => Carbon::now('Asia/Colombo')->subHours(5),
        'location_text' => 'Southern Expressway',
        'title' => 'Rear-end bump',
        'description' => 'Third-party vehicle brushed rear bumper',
    ]);

    $cleanPdf = "%PDF-1.4\nPolice accident entry 458/2026\n%%EOF";
    $file = UploadedFile::fake()->createWithContent('police_statement.pdf', $cleanPdf);

    $attachment = $this->incidentService->attachMedia(
        incident: $incident,
        staff: $this->manager,
        file: $file,
        category: IncidentAttachmentCategory::PoliceReportPdf,
        caption: 'Certified police station excerpt'
    );

    expect($attachment->id)->not->toBeNull()
        ->and($attachment->category)->toBe(IncidentAttachmentCategory::PoliceReportPdf)
        ->and($attachment->original_filename)->toBe('police_statement.pdf')
        ->and(Storage::disk('local')->exists($attachment->file_path))->toBeTrue();

    expect(AuditEvent::where('action', 'incident.media_attached')->exists())->toBeTrue();
});
