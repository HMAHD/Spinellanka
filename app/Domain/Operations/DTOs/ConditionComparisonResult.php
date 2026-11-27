<?php

declare(strict_types=1);

namespace App\Domain\Operations\DTOs;

use App\Domain\Operations\Enums\FuelLevel;

readonly class ConditionComparisonResult
{
    /**
     * @param  array<int, string>  $damagesRecorded
     * @param  array<int, array<string, mixed>>  $incidentDeductions
     */
    public function __construct(
        public int $collectionKm,
        public int $returnKm,
        public int $distanceDrivenKm,
        public int $includedDistanceKm,
        public int $excessDistanceKm,
        public int $excessMileageRateMinorUnits,
        public int $excessMileageChargeMinorUnits,
        public FuelLevel $collectionFuelLevel,
        public FuelLevel $returnFuelLevel,
        public float $fuelShortfallFraction,
        public float $fuelShortfallLitres,
        public int $fuelShortfallCostMinorUnits,
        public bool $smokingDetected,
        public int $smokingPenaltyMinorUnits,
        public array $damagesRecorded,
        public array $incidentDeductions,
        public int $totalProposedSettlementMinorUnits,
        public string $currency = 'USD',
    ) {}
}
