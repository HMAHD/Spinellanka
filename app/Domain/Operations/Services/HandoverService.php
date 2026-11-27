<?php

declare(strict_types=1);

namespace App\Domain\Operations\Services;

use App\Domain\Audit\Services\AuditService;
use App\Domain\Identity\Permission;
use App\Domain\Operations\Enums\FuelLevel;
use App\Domain\Operations\Enums\HandoverStatus;
use App\Domain\Operations\Enums\HandoverType;
use App\Domain\Operations\Exceptions\ReleaseGateBlockedException;
use App\Models\Booking;
use App\Models\Handover;
use App\Models\RentDetail;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleConditionRecord;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class HandoverService
{
    public function __construct(
        protected PrivateMediaPipelineService $mediaPipeline,
        protected AuditService $auditService,
    ) {}

    /**
     * Schedule a collection or return handover.
     */
    public function createHandover(
        Booking $booking,
        HandoverType $type,
        Carbon $scheduledAt,
        string $locationType,
        string $locationText,
        ?User $staffUser = null,
        ?string $notes = null
    ): Handover {
        /** @var RentDetail $rentDetail */
        $rentDetail = $booking->rentDetail ?? throw new InvalidArgumentException("Booking {$booking->reference} is not a self-drive rental.");

        return Handover::create([
            'booking_id' => $booking->id,
            'rent_detail_id' => $rentDetail->id,
            'vehicle_id' => $booking->vehicle_id,
            'type' => $type,
            'status' => HandoverStatus::Scheduled,
            'scheduled_at' => $scheduledAt,
            'staff_user_id' => $staffUser?->id,
            'location_type' => $locationType,
            'location_text' => $locationText,
            'operational_notes' => $notes,
        ]);
    }

    /**
     * Record physical document inspection result (Strictly physical verification; NO uploads).
     *
     * @param  list<string>  $docTypes
     */
    public function recordDocumentInspection(
        Handover $handover,
        User $staff,
        array $docTypes,
        string $licenceCountry,
        ?Carbon $licenceExpiry,
        bool $idpOrAacVerified,
        ?string $notes = null
    ): Handover {
        if ($handover->type !== HandoverType::Collection) {
            throw new InvalidArgumentException('Document inspection only applies to vehicle collection handovers.');
        }

        // Sri Lanka Motor Traffic Act statutory rule: foreign licenses require IDP or AAC endorsement
        $isForeignLicence = strtoupper(trim($licenceCountry)) !== 'LK' && strtoupper(trim($licenceCountry)) !== 'SRI LANKA';
        if ($isForeignLicence && ! $idpOrAacVerified) {
            throw new InvalidArgumentException('Statutory violation: Foreign driver licence requires a valid International Driving Permit (IDP) or AAC endorsement.');
        }

        if ($licenceExpiry && $licenceExpiry->isPast()) {
            throw new InvalidArgumentException('Statutory violation: Driver licence has expired.');
        }

        return DB::transaction(function () use ($handover, $staff, $docTypes, $licenceCountry, $licenceExpiry, $idpOrAacVerified, $notes) {
            $handover->update([
                'documents_inspected' => true,
                'inspected_doc_types' => implode(',', $docTypes),
                'driver_licence_country' => $licenceCountry,
                'driver_licence_expiry' => $licenceExpiry,
                'idp_or_aac_verified' => $idpOrAacVerified,
                'document_inspection_notes' => $notes,
                'staff_user_id' => $staff->id,
                'status' => HandoverStatus::DocumentsVerified,
            ]);

            // Synchronize with existing RentDetail model
            $handover->rentDetail->update([
                'original_documents_inspected' => true,
                'original_documents_inspected_at' => Carbon::now('Asia/Colombo'),
                'original_documents_inspected_by' => $staff->id,
                'original_documents_inspection_notes' => $notes,
                'driver_licence_country' => $licenceCountry,
                'has_idp' => $idpOrAacVerified,
            ]);

            $this->auditService->record(
                action: 'handover.documents_inspected',
                actor: $staff,
                summary: "Staff {$staff->name} verified physical original documents for booking {$handover->booking->reference}",
                metadata: [
                    'handover_id' => $handover->id,
                    'booking_id' => $handover->booking_id,
                    'doc_types' => $docTypes,
                    'licence_country' => $licenceCountry,
                    'idp_verified' => $idpOrAacVerified,
                ],
                auditable: $handover
            );

            return $handover->fresh();
        });
    }

    /**
     * Record vehicle condition state (odometer, fuel level, accessories, inspection notes).
     *
     * @param  array<string, bool>  $accessoriesChecklist
     */
    public function recordCondition(
        Handover $handover,
        User $staff,
        int $odometerKm,
        FuelLevel $fuelLevel,
        array $accessoriesChecklist,
        bool $isClean = true,
        bool $smokingDetected = false,
        ?string $exteriorNotes = null,
        ?string $interiorNotes = null,
        bool $isZeroKmVerified = false,
        ?string $zeroKmReason = null
    ): VehicleConditionRecord {
        if ($odometerKm < 0) {
            throw new InvalidArgumentException('Odometer reading cannot be negative.');
        }

        return DB::transaction(function () use (
            $handover, $staff, $odometerKm, $fuelLevel, $accessoriesChecklist,
            $isClean, $smokingDetected, $exteriorNotes, $interiorNotes, $isZeroKmVerified, $zeroKmReason
        ) {
            // Delete any existing draft condition record for this handover
            $handover->conditionRecord()->delete();

            /** @var VehicleConditionRecord $record */
            $record = VehicleConditionRecord::create([
                'handover_id' => $handover->id,
                'record_type' => $handover->type->value,
                'odometer_km' => $odometerKm,
                'fuel_level' => $fuelLevel,
                'accessories_checklist' => $accessoriesChecklist,
                'is_clean' => $isClean,
                'smoking_detected' => $smokingDetected,
                'is_zero_km_verified' => $isZeroKmVerified,
                'zero_km_reason' => $zeroKmReason,
                'exterior_condition_notes' => $exteriorNotes,
                'interior_condition_notes' => $interiorNotes,
                'recorded_at' => Carbon::now('Asia/Colombo'),
                'recorded_by_user_id' => $staff->id,
            ]);

            $handover->update([
                'status' => HandoverStatus::ConditionCaptured,
                'staff_user_id' => $staff->id,
            ]);

            return $record;
        });
    }

    /**
     * Capture customer and staff digital signatures.
     */
    public function captureSignatures(
        Handover $handover,
        string $customerSignatureBase64,
        string $customerSignerName,
        string $staffSignatureBase64,
        User $staff
    ): Handover {
        $customerPath = $this->mediaPipeline->storeSignaturePng($customerSignatureBase64, 'customer');
        $staffPath = $this->mediaPipeline->storeSignaturePng($staffSignatureBase64, 'staff');
        $now = Carbon::now('Asia/Colombo');

        $handover->update([
            'customer_signature_path' => $customerPath,
            'customer_signer_name' => $customerSignerName,
            'customer_signed_at' => $now,
            'staff_signature_path' => $staffPath,
            'staff_signed_at' => $now,
            'status' => HandoverStatus::SignaturesCaptured,
        ]);

        return $handover->fresh();
    }

    /**
     * Evaluate release gates and dispatch vehicle.
     */
    public function releaseVehicle(Handover $handover, User $staff): Handover
    {
        $handover->load(['conditionRecord', 'rentDetail', 'booking', 'vehicle']);

        $violations = [];

        if ($handover->type === HandoverType::Collection) {
            if (! $handover->documents_inspected) {
                $violations['documents'] = 'Physical original documents have not been verified.';
            }

            // Statutory Foreign Licence IDP/AAC and expiry gate
            $isForeign = ! in_array(strtoupper(trim((string) $handover->driver_licence_country)), ['LK', 'SRI LANKA'], true);
            if ($isForeign && ! $handover->idp_or_aac_verified) {
                $violations['idp'] = 'Statutory violation: Foreign driver licence requires verified IDP or AAC endorsement.';
            }

            if ($handover->driver_licence_expiry && $handover->driver_licence_expiry->isPast()) {
                $violations['licence_expiry'] = 'Statutory violation: Driver licence has expired.';
            }

            if (! $handover->conditionRecord) {
                $violations['condition'] = 'Vehicle collection condition has not been recorded.';
            }

            if (! $handover->customer_signature_path || ! $handover->staff_signature_path) {
                $violations['signatures'] = 'Dual customer and staff signatures are required.';
            }
        }

        if (! empty($violations)) {
            throw new ReleaseGateBlockedException($violations);
        }

        return DB::transaction(function () use ($handover, $staff) {
            $now = Carbon::now('Asia/Colombo');
            $handover->update([
                'is_released' => true,
                'status' => HandoverStatus::Released,
                'completed_at' => $now,
                'staff_user_id' => $staff->id,
            ]);

            $this->auditService->record(
                action: 'handover.vehicle_released',
                actor: $staff,
                summary: "Vehicle {$handover->vehicle->license_plate} released for booking {$handover->booking->reference}",
                metadata: [
                    'handover_id' => $handover->id,
                    'booking_id' => $handover->booking_id,
                    'odometer_km' => $handover->conditionRecord?->odometer_km,
                    'fuel_level' => $handover->conditionRecord?->fuel_level?->value,
                ],
                auditable: $handover
            );

            return $handover->fresh();
        });
    }

    /**
     * Four-Eyes Exception Authorization: Release vehicle under audited manager override.
     */
    public function authorizeException(Handover $handover, User $authorizer, string $reason): Handover
    {
        // 1. Separation of duties (Four-Eyes Principle): Authorizer cannot be the field staff performing handover
        if ($handover->staff_user_id === null) {
            throw new InvalidArgumentException('Cannot authorize exception: Handover must have an assigned field staff member.');
        }

        if ($authorizer->id === $handover->staff_user_id) {
            throw new AuthorizationException('Four-Eyes principle violation: Field staff cannot authorize their own handover exception.');
        }

        // 2. Permission check: requires booking.override_conflict
        if (! $authorizer->can(Permission::BookingOverrideConflict->value) && ! $authorizer->hasPermissionTo(Permission::BookingOverrideConflict->value)) {
            throw new AuthorizationException('Actor does not have booking.override_conflict permission to authorize exceptions.');
        }

        if (trim($reason) === '') {
            throw new InvalidArgumentException('A structured exception reason is mandatory.');
        }

        return DB::transaction(function () use ($handover, $authorizer, $reason) {
            $now = Carbon::now('Asia/Colombo');

            $handover->update([
                'is_released' => true,
                'has_exception' => true,
                'exception_reason' => $reason,
                'exception_authorized_by_user_id' => $authorizer->id,
                'exception_authorized_at' => $now,
                'status' => HandoverStatus::ExceptionApproved,
                'completed_at' => $now,
            ]);

            $this->auditService->record(
                action: 'handover.exception_authorized',
                actor: $authorizer,
                summary: "Manager {$authorizer->name} authorized handover release exception for booking {$handover->booking->reference}: {$reason}",
                metadata: [
                    'handover_id' => $handover->id,
                    'booking_id' => $handover->booking_id,
                    'reason' => $reason,
                    'missing_documents' => ! $handover->documents_inspected,
                    'missing_condition' => ! $handover->conditionRecord,
                    'missing_signatures' => ! $handover->customer_signature_path,
                ],
                auditable: $handover
            );

            return $handover->fresh();
        });
    }

    /**
     * Complete a vehicle return handover.
     */
    public function completeReturn(Handover $handover, User $staff): Handover
    {
        $handover->load(['conditionRecord', 'rentDetail', 'booking', 'vehicle']);

        if ($handover->type !== HandoverType::Return) {
            throw new InvalidArgumentException('Only return handovers can be completed via completeReturn.');
        }

        if (! $handover->conditionRecord) {
            throw new ReleaseGateBlockedException(['condition' => 'Return condition must be recorded before completing check-in.']);
        }

        return DB::transaction(function () use ($handover, $staff) {
            $now = Carbon::now('Asia/Colombo');

            $handover->update([
                'status' => HandoverStatus::Completed,
                'completed_at' => $now,
                'staff_user_id' => $staff->id,
            ]);

            $this->auditService->record(
                action: 'handover.return_completed',
                actor: $staff,
                summary: "Vehicle {$handover->vehicle->license_plate} return completed for booking {$handover->booking->reference}",
                metadata: [
                    'handover_id' => $handover->id,
                    'booking_id' => $handover->booking_id,
                    'return_odometer_km' => $handover->conditionRecord->odometer_km,
                    'return_fuel_level' => $handover->conditionRecord->fuel_level->value,
                ],
                auditable: $handover
            );

            return $handover->fresh();
        });
    }
}
