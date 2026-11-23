<?php

namespace App\Domain\Catalog\Services;

use App\Domain\Booking\ServiceType;
use App\Models\Extra;
use App\Models\ExtraCompatibility;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Collection;

class ExtraService
{
    public function isCompatible(Extra $extra, ServiceType $serviceType, ?Vehicle $vehicle = null): bool
    {
        if (! $extra->is_active) {
            return false;
        }

        $rules = ExtraCompatibility::where('extra_id', $extra->id)->get();

        // Fallback: If no compatibility rules exist, extra is globally compatible
        if ($rules->isEmpty()) {
            return true;
        }

        // Tier 1: Exact Vehicle Match
        if ($vehicle !== null) {
            $tier1 = $rules->first(fn ($r) => $r->vehicle_id === $vehicle->id && ($r->service_type === null || $r->service_type === $serviceType));
            if ($tier1 !== null) {
                return $tier1->is_compatible;
            }
        }

        // Tier 2: Service Type + Vehicle Type Match
        if ($vehicle !== null) {
            $tier2 = $rules->first(fn ($r) => $r->service_type === $serviceType && $r->vehicle_type === $vehicle->vehicle_type);
            if ($tier2 !== null) {
                return $tier2->is_compatible;
            }
        }

        // Tier 3: Service-wide Match (vehicle_type IS NULL and vehicle_id IS NULL)
        $tier3 = $rules->first(fn ($r) => $r->service_type === $serviceType && $r->vehicle_type === null && $r->vehicle_id === null);
        if ($tier3 !== null) {
            return $tier3->is_compatible;
        }

        // Tier 4: Vehicle Type Match (service_type IS NULL)
        if ($vehicle !== null) {
            $tier4 = $rules->first(fn ($r) => $r->service_type === null && $r->vehicle_type === $vehicle->vehicle_type);
            if ($tier4 !== null) {
                return $tier4->is_compatible;
            }
        }

        // Default Allowlist Fallback: If rules exist but none match, extra is incompatible
        return false;
    }

    /**
     * @return Collection<int, Extra>
     */
    public function getCompatibleExtras(ServiceType $serviceType, ?Vehicle $vehicle = null): Collection
    {
        $allActive = Extra::where('is_active', true)->get();

        return $allActive->filter(fn (Extra $extra) => $this->isCompatible($extra, $serviceType, $vehicle))->values();
    }
}

