<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Services;

use App\Domain\Audit\Services\AuditService;
use App\Models\RentPolicy;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RentPolicyService
{
    public function __construct(
        protected AuditService $auditService,
    ) {}

    public function getCurrentPolicy(): RentPolicy
    {
        return RentPolicy::current();
    }

    /**
     * Update or activate the authoritative RentPolicy inside a transaction with audit logging.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function updateActivePolicy(array $attributes, ?User $actor = null): RentPolicy
    {
        return DB::transaction(function () use ($attributes, $actor): RentPolicy {
            $current = RentPolicy::current();

            $payload = [
                'min_driver_age' => (int) ($attributes['min_driver_age'] ?? $current->min_driver_age),
                'max_driver_age' => (int) ($attributes['max_driver_age'] ?? $current->max_driver_age),
                'min_licence_years' => (int) ($attributes['min_licence_years'] ?? $current->min_licence_years),
                'idp_required' => (bool) ($attributes['idp_required'] ?? $current->idp_required),
                'max_additional_drivers' => (int) ($attributes['max_additional_drivers'] ?? $current->max_additional_drivers),
                'security_deposit_minor_units' => (int) ($attributes['security_deposit_minor_units'] ?? $current->security_deposit_minor_units),
                'insurance_excess_minor_units' => (int) ($attributes['insurance_excess_minor_units'] ?? $current->insurance_excess_minor_units),
                'daily_mileage_km' => (int) ($attributes['daily_mileage_km'] ?? $current->daily_mileage_km),
                'excess_mileage_rate_minor_units' => (int) ($attributes['excess_mileage_rate_minor_units'] ?? $current->excess_mileage_rate_minor_units),
                'grace_period_minutes' => (int) ($attributes['grace_period_minutes'] ?? $current->grace_period_minutes),
                'extra_hours_threshold' => (int) ($attributes['extra_hours_threshold'] ?? $current->extra_hours_threshold),
                'full_day_threshold_hours' => (int) ($attributes['full_day_threshold_hours'] ?? $current->full_day_threshold_hours),
                'office_handover_available' => (bool) ($attributes['office_handover_available'] ?? $current->office_handover_available),
                'airport_handover_available' => (bool) ($attributes['airport_handover_available'] ?? $current->airport_handover_available),
                'hotel_delivery_available' => (bool) ($attributes['hotel_delivery_available'] ?? $current->hotel_delivery_available),
                'hotel_delivery_fee_minor_units' => (int) ($attributes['hotel_delivery_fee_minor_units'] ?? $current->hotel_delivery_fee_minor_units),
                'prohibited_roads_text' => array_key_exists('prohibited_roads_text', $attributes)
                    ? ($attributes['prohibited_roads_text'] !== null ? (string) $attributes['prohibited_roads_text'] : null)
                    : $current->prohibited_roads_text,
                'smoking_penalty_minor_units' => (int) ($attributes['smoking_penalty_minor_units'] ?? $current->smoking_penalty_minor_units),
                'is_active' => true,
            ];

            $current->update($payload);

            $this->auditService->record(
                action: 'pricing.rent_policy.updated',
                actor: $actor,
                summary: "Updated active RentPolicy #{$current->id}",
                metadata: [
                    'rent_policy_id' => $current->id,
                    'security_deposit_minor_units' => $current->security_deposit_minor_units,
                    'daily_mileage_km' => $current->daily_mileage_km,
                ],
                auditable: $current,
            );

            return $current->fresh() ?? $current;
        });
    }
}
