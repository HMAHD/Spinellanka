<?php

declare(strict_types=1);

namespace App\Domain\Operations\Services;

use App\Domain\Operations\DTOs\ConditionComparisonResult;
use App\Domain\Operations\Exceptions\OdometerRollbackAnomalyException;
use App\Models\Handover;
use App\Models\IncidentFinancialEntry;
use App\Models\VehicleConditionRecord;
use InvalidArgumentException;

class ConditionComparisonService
{
    /**
     * Compare collection and return conditions and calculate final settlement line-items.
     */
    public function compareConditions(Handover $collectionHandover, Handover $returnHandover): ConditionComparisonResult
    {
        $collectionHandover->load('conditionRecord');
        $returnHandover->load('conditionRecord');

        /** @var VehicleConditionRecord|null $collectionCondition */
        $collectionCondition = $collectionHandover->conditionRecord;
        /** @var VehicleConditionRecord|null $returnCondition */
        $returnCondition = $returnHandover->conditionRecord;

        if (! $collectionCondition || ! $returnCondition) {
            throw new InvalidArgumentException('Both collection and return handovers must have recorded condition states.');
        }

        // 1. Negative Odometer Detection (Rollback Anomaly / Cluster Swap)
        if ($returnCondition->odometer_km < $collectionCondition->odometer_km) {
            throw new OdometerRollbackAnomalyException(
                collectionKm: $collectionCondition->odometer_km,
                returnKm: $returnCondition->odometer_km
            );
        }

        $rentDetail = $collectionHandover->rentDetail;

        // 2. Zero-KM Return Verification for multi-day rentals
        $distanceDrivenKm = $returnCondition->odometer_km - $collectionCondition->odometer_km;
        if ($distanceDrivenKm === 0 && $rentDetail->duration_days >= 1 && ! $returnCondition->is_zero_km_verified) {
            throw new InvalidArgumentException('Multi-day rental with 0 kilometers driven must have explicit staff verification and reason recorded.');
        }

        // 3. Excess Mileage Calculation
        $includedKm = $rentDetail->included_distance_km;
        $excessKm = max(0, $distanceDrivenKm - $includedKm);
        $mileageRateMinorUnits = $rentDetail->excess_mileage_rate_minor_units;
        $excessMileageCharge = $excessKm * $mileageRateMinorUnits;

        // 4. Fuel Shortfall Calculation (Same-to-same policy)
        $collectionFraction = $collectionCondition->fuel_level->fraction();
        $returnFraction = $returnCondition->fuel_level->fraction();
        $fuelShortfallFraction = max(0.0, $collectionFraction - $returnFraction);

        $tankCapacityLitres = $collectionHandover->vehicle->fuel_tank_capacity_litres ?: 50;
        $shortfallLitres = (float) round($fuelShortfallFraction * $tankCapacityLitres, 2);

        $marketFuelRateMinorUnits = (int) config('booking.rent.market_fuel_rate_minor_units', 350); // e.g. $3.50/L
        $fuelCostMinorUnits = (int) round($shortfallLitres * $marketFuelRateMinorUnits);

        // 5. Smoking / Deep Cleaning Penalty
        $smokingDetected = $returnCondition->smoking_detected;
        $smokingPenaltyMinorUnits = $smokingDetected
            ? (int) config('booking.rent.smoking_penalty_minor_units', 15000) // $150.00 USD
            : 0;

        // 6. Damages & Incident Deduplication Bridge
        $damagesRecorded = [];
        if (! empty($returnCondition->exterior_condition_notes)) {
            $damagesRecorded[] = 'Exterior: '.$returnCondition->exterior_condition_notes;
        }
        if (! empty($returnCondition->interior_condition_notes)) {
            $damagesRecorded[] = 'Interior: '.$returnCondition->interior_condition_notes;
        }

        // Query any existing incident charges already billed to the customer for this booking
        $existingIncidentCharges = IncidentFinancialEntry::query()
            ->whereHas('incident', fn ($q) => $q->where('booking_id', $collectionHandover->booking_id))
            ->whereIn('entry_type', ['customer_charge', 'customer_deduction'])
            ->where('status', '!=', 'waived')
            ->get();

        $incidentDeductions = [];
        foreach ($existingIncidentCharges as $entry) {
            $incidentDeductions[] = [
                'incident_id' => $entry->incident_id,
                'description' => $entry->description,
                'amount_minor_units' => $entry->amount_minor_units,
                'status' => $entry->status->value,
            ];
        }

        $totalProposedSettlement = $excessMileageCharge + $fuelCostMinorUnits + $smokingPenaltyMinorUnits;

        return new ConditionComparisonResult(
            collectionKm: $collectionCondition->odometer_km,
            returnKm: $returnCondition->odometer_km,
            distanceDrivenKm: $distanceDrivenKm,
            includedDistanceKm: $includedKm,
            excessDistanceKm: $excessKm,
            excessMileageRateMinorUnits: $mileageRateMinorUnits,
            excessMileageChargeMinorUnits: $excessMileageCharge,
            collectionFuelLevel: $collectionCondition->fuel_level,
            returnFuelLevel: $returnCondition->fuel_level,
            fuelShortfallFraction: $fuelShortfallFraction,
            fuelShortfallLitres: $shortfallLitres,
            fuelShortfallCostMinorUnits: $fuelCostMinorUnits,
            smokingDetected: $smokingDetected,
            smokingPenaltyMinorUnits: $smokingPenaltyMinorUnits,
            damagesRecorded: $damagesRecorded,
            incidentDeductions: $incidentDeductions,
            totalProposedSettlementMinorUnits: $totalProposedSettlement,
            currency: 'USD'
        );
    }
}
