<?php

declare(strict_types=1);

namespace App\Domain\Operations\Enums;

enum IncidentAttachmentCategory: string
{
    case VehicleDamage = 'vehicle_damage';
    case ScenePhoto = 'scene_photo';
    case ThirdPartyVehicle = 'third_party_vehicle';
    case RepairEstimate = 'repair_estimate';
    case PoliceReportPdf = 'police_report_pdf';
    case InsuranceClaimPdf = 'insurance_claim_pdf';

    public function label(): string
    {
        return match ($this) {
            self::VehicleDamage => 'Vehicle Damage Evidence',
            self::ScenePhoto => 'Incident Scene Photo',
            self::ThirdPartyVehicle => 'Third-Party Vehicle / Property',
            self::RepairEstimate => 'Garage Repair Quotation',
            self::PoliceReportPdf => 'Police Report Document (PDF)',
            self::InsuranceClaimPdf => 'Insurance Claim Form (PDF)',
        };
    }
}
