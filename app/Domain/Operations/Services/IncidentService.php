<?php

declare(strict_types=1);

namespace App\Domain\Operations\Services;

use App\Domain\Audit\Services\AuditService;
use App\Domain\Identity\Permission;
use App\Domain\Operations\Enums\IncidentAttachmentCategory;
use App\Domain\Operations\Enums\IncidentFinancialEntryType;
use App\Domain\Operations\Enums\IncidentFinancialStatus;
use App\Domain\Operations\Enums\IncidentSeverity;
use App\Domain\Operations\Enums\IncidentStatus;
use App\Domain\Operations\Enums\IncidentType;
use App\Models\Incident;
use App\Models\IncidentAction;
use App\Models\IncidentAttachment;
use App\Models\IncidentFinancialEntry;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class IncidentService
{
    public function __construct(
        protected PrivateMediaPipelineService $mediaPipeline,
        protected AuditService $auditService,
    ) {}

    /**
     * Create a new incident report.
     *
     * @param  array{
     *     booking_id?: int|null,
     *     vehicle_id?: int|null,
     *     driver_id?: int|null,
     *     type: IncidentType,
     *     severity: IncidentSeverity,
     *     location_text?: string|null,
     *     occurred_at: Carbon,
     *     reported_by_name?: string|null,
     *     reported_by_role?: string|null,
     *     description: string,
     *     police_report_filed?: bool,
     *     police_station?: string|null,
     *     police_report_number?: string|null,
     *     insurance_claim_filed?: bool,
     *     insurance_claim_reference?: string|null,
     *     assigned_to_user_id?: int|null
     * }  $data
     */
    public function createIncident(User $staff, array $data): Incident
    {
        if (! $staff->can(Permission::IncidentManage->value) && ! $staff->hasPermissionTo(Permission::IncidentManage->value)) {
            throw new AuthorizationException('Actor does not have incident.manage permission.');
        }

        if (trim($data['description']) === '') {
            throw new InvalidArgumentException('Incident description cannot be empty.');
        }

        return DB::transaction(function () use ($staff, $data) {
            $incidentNumber = Incident::generateIncidentNumber();
            $now = Carbon::now('Asia/Colombo');

            /** @var Incident $incident */
            $incident = Incident::create([
                'incident_number' => $incidentNumber,
                'booking_id' => $data['booking_id'] ?? null,
                'vehicle_id' => $data['vehicle_id'] ?? null,
                'driver_id' => $data['driver_id'] ?? null,
                'type' => $data['type'] ?? $data['incident_type'] ?? IncidentType::Damage,
                'severity' => $data['severity'] ?? IncidentSeverity::Minor,
                'status' => IncidentStatus::Reported,
                'location_text' => $data['location_text'] ?? null,
                'occurred_at' => $data['occurred_at'],
                'reported_at' => $now,
                'reported_by_name' => $data['reported_by_name'] ?? $staff->name,
                'reported_by_role' => $data['reported_by_role'] ?? 'staff',
                'description' => $data['description'],
                'police_report_filed' => $data['police_report_filed'] ?? false,
                'police_station' => $data['police_station'] ?? null,
                'police_report_number' => $data['police_report_number'] ?? null,
                'insurance_claim_filed' => $data['insurance_claim_filed'] ?? false,
                'insurance_claim_reference' => $data['insurance_claim_reference'] ?? null,
                'assigned_to_user_id' => $data['assigned_to_user_id'] ?? $staff->id,
            ]);

            IncidentAction::create([
                'incident_id' => $incident->id,
                'action_type' => 'incident_created',
                'description' => sprintf('Incident reported by %s: %s', $staff->name, $data['description']),
                'performed_by_user_id' => $staff->id,
                'performed_at' => $now,
            ]);

            $this->auditService->record(
                action: 'incident.created',
                actor: $staff,
                summary: "Staff {$staff->name} logged incident {$incident->incident_number} ({$incident->type->value}, {$incident->severity->value})",
                metadata: [
                    'incident_id' => $incident->id,
                    'incident_number' => $incident->incident_number,
                    'type' => $incident->type->value,
                    'severity' => $incident->severity->value,
                    'booking_id' => $incident->booking_id,
                    'vehicle_id' => $incident->vehicle_id,
                ],
                auditable: $incident
            );

            return $incident;
        });
    }

    /**
     * Transition an incident's status.
     */
    public function updateStatus(Incident $incident, User $staff, IncidentStatus $newStatus, string $reason): Incident
    {
        if (! $staff->can(Permission::IncidentManage->value) && ! $staff->hasPermissionTo(Permission::IncidentManage->value)) {
            throw new AuthorizationException('Actor does not have incident.manage permission.');
        }

        if ($incident->status === $newStatus) {
            return $incident;
        }

        return DB::transaction(function () use ($incident, $staff, $newStatus, $reason) {
            $oldStatus = $incident->status;
            $now = Carbon::now('Asia/Colombo');

            $updates = [
                'status' => $newStatus,
            ];

            if ($newStatus === IncidentStatus::Resolved || $newStatus === IncidentStatus::Closed) {
                $updates['resolved_at'] = $now;
                $updates['resolution_summary'] = $reason;
            }

            $incident->update($updates);

            IncidentAction::create([
                'incident_id' => $incident->id,
                'action_type' => 'status_changed',
                'description' => sprintf('Status changed from %s to %s by %s. Reason: %s', $oldStatus->value, $newStatus->value, $staff->name, $reason),
                'performed_by_user_id' => $staff->id,
                'performed_at' => $now,
            ]);

            $this->auditService->record(
                action: 'incident.status_changed',
                actor: $staff,
                summary: "Incident {$incident->incident_number} status changed to {$newStatus->value} by {$staff->name}",
                metadata: [
                    'incident_id' => $incident->id,
                    'previous_status' => $oldStatus->value,
                    'new_status' => $newStatus->value,
                    'reason' => $reason,
                ],
                auditable: $incident
            );

            return $incident->fresh();
        });
    }

    /**
     * Append an action entry to the incident timeline.
     */
    public function recordAction(Incident $incident, User $staff, string $actionType, string $description): IncidentAction
    {
        if (! $staff->can(Permission::IncidentManage->value) && ! $staff->hasPermissionTo(Permission::IncidentManage->value)) {
            throw new AuthorizationException('Actor does not have incident.manage permission.');
        }

        return IncidentAction::create([
            'incident_id' => $incident->id,
            'action_type' => $actionType,
            'description' => $description,
            'performed_by_user_id' => $staff->id,
            'performed_at' => Carbon::now('Asia/Colombo'),
        ]);
    }

    /**
     * Record a financial entry associated with the incident.
     */
    public function recordFinancialEntry(
        Incident $incident,
        User $staff,
        IncidentFinancialEntryType $type,
        string $description,
        int $amountMinorUnits,
        string $currency = 'USD',
        ?string $originalCurrency = null,
        ?int $originalAmountMinorUnits = null,
        ?float $exchangeRate = null
    ): IncidentFinancialEntry {
        if (! $staff->can(Permission::IncidentManage->value) && ! $staff->hasPermissionTo(Permission::IncidentManage->value)) {
            throw new AuthorizationException('Actor does not have incident.manage permission.');
        }

        if ($amountMinorUnits <= 0) {
            throw new InvalidArgumentException('Financial entry amount must be greater than zero.');
        }

        return DB::transaction(function () use (
            $incident, $staff, $type, $description, $amountMinorUnits,
            $currency, $originalCurrency, $originalAmountMinorUnits, $exchangeRate
        ) {
            /** @var IncidentFinancialEntry $entry */
            $entry = IncidentFinancialEntry::create([
                'incident_id' => $incident->id,
                'payment_transaction_id' => null,
                'entry_type' => $type,
                'description' => $description,
                'amount_minor_units' => $amountMinorUnits,
                'currency' => $currency,
                'original_currency' => $originalCurrency,
                'original_amount_minor_units' => $originalAmountMinorUnits,
                'exchange_rate' => $exchangeRate,
                'status' => IncidentFinancialStatus::Recorded,
                'created_by_user_id' => $staff->id,
            ]);

            // Update total financial impact on incident
            $incident->increment('total_financial_impact_minor_units', $amountMinorUnits);

            $this->recordAction(
                incident: $incident,
                staff: $staff,
                actionType: 'financial_entry_recorded',
                description: sprintf('Financial entry recorded (%s): %s %s (%s)', $type->value, $currency, number_format($amountMinorUnits / 100, 2), $description)
            );

            $this->auditService->record(
                action: 'incident.financial_entry_added',
                actor: $staff,
                summary: sprintf('Staff %s recorded financial entry (%s): %s %s on incident %s', $staff->name, $type->value, $currency, number_format($amountMinorUnits / 100, 2), $incident->incident_number),
                metadata: [
                    'incident_id' => $incident->id,
                    'financial_entry_id' => $entry->id,
                    'entry_type' => $type->value,
                    'amount_minor_units' => $amountMinorUnits,
                    'currency' => $currency,
                ],
                auditable: $incident
            );

            return $entry;
        });
    }

    /**
     * Attach private media to an incident (Raster image or PDF document).
     */
    public function attachMedia(
        Incident $incident,
        User $staff,
        UploadedFile $file,
        IncidentAttachmentCategory $category,
        ?string $caption = null
    ): IncidentAttachment {
        if (! $staff->can(Permission::IncidentManage->value) && ! $staff->hasPermissionTo(Permission::IncidentManage->value)) {
            throw new AuthorizationException('Actor does not have incident.manage permission.');
        }

        $isPdf = in_array($file->getMimeType(), ['application/pdf', 'application/x-pdf'], true)
            || str_ends_with(strtolower($file->getClientOriginalName()), '.pdf');

        $processed = $isPdf
            ? $this->mediaPipeline->processDocument($file, 'incident_attachments')
            : $this->mediaPipeline->processRasterImage($file, 'incident_attachments');

        /** @var IncidentAttachment $attachment */
        $attachment = IncidentAttachment::create([
            'incident_id' => $incident->id,
            'category' => $category,
            'file_uuid' => $processed['file_uuid'],
            'file_path' => $processed['file_path'],
            'original_filename' => $processed['original_filename'],
            'mime_type' => $processed['mime_type'],
            'file_size_bytes' => $processed['file_size_bytes'],
            'caption' => $caption,
            'processing_status' => 'clean',
        ]);

        $this->recordAction(
            incident: $incident,
            staff: $staff,
            actionType: 'attachment_added',
            description: sprintf('Attached evidence file (%s): %s', $category->value, $processed['original_filename'])
        );

        $this->auditService->record(
            action: 'incident.media_attached',
            actor: $staff,
            summary: sprintf('Staff %s attached %s evidence to incident %s', $staff->name, $category->value, $incident->incident_number),
            metadata: [
                'incident_id' => $incident->id,
                'category' => $category->value,
                'file_uuid' => $processed['file_uuid'],
            ],
            auditable: $incident
        );

        return $attachment;
    }
}
