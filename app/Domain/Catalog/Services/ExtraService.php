<?php

namespace App\Domain\Catalog\Services;

use App\Domain\Audit\Services\AuditService;
use App\Domain\Booking\ServiceType;
use App\Models\Extra;
use App\Models\ExtraCompatibility;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ExtraService
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createExtra(array $attributes, ?User $actor = null): Extra
    {
        return DB::transaction(function () use ($attributes, $actor) {
            $extra = Extra::create($attributes);

            $this->auditService->record(
                action: 'fleet.extra.created',
                actor: $actor,
                summary: "Created catalog extra {$extra->name}",
                metadata: [
                    'extra_id' => $extra->id,
                    'name' => $extra->name,
                    'slug' => $extra->slug,
                    'price_minor_units' => $extra->price_minor_units,
                    'currency' => $extra->currency,
                ],
                auditable: $extra
            );

            return $extra;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateExtra(Extra $extra, array $attributes, ?User $actor = null): Extra
    {
        return DB::transaction(function () use ($extra, $attributes, $actor) {
            $oldAttributes = $extra->only(array_keys($attributes));
            $extra->update($attributes);

            $diff = array_diff_assoc(
                $this->normalizeAttributesForDiff($attributes),
                $this->normalizeAttributesForDiff($oldAttributes)
            );
            if (! empty($diff)) {
                $this->auditService->record(
                    action: 'fleet.extra.updated',
                    actor: $actor,
                    summary: "Updated catalog extra {$extra->name}",
                    metadata: [
                        'extra_id' => $extra->id,
                        'changes' => $diff,
                    ],
                    auditable: $extra
                );
            }

            return $extra;
        });
    }

    public function deleteExtra(Extra $extra, ?User $actor = null): void
    {
        DB::transaction(function () use ($extra, $actor) {
            $extraId = $extra->id;
            $name = $extra->name;

            $extra->delete();

            $this->auditService->record(
                action: 'fleet.extra.deleted',
                actor: $actor,
                summary: "Deleted catalog extra {$name} (#{$extraId})",
                metadata: [
                    'extra_id' => $extraId,
                    'name' => $name,
                ],
                auditable: null
            );
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createCompatibilityRule(Extra $extra, array $attributes, ?User $actor = null): ExtraCompatibility
    {
        return DB::transaction(function () use ($extra, $attributes, $actor) {
            $rule = $extra->compatibilities()->create($attributes);

            $this->auditService->record(
                action: 'fleet.extra.compatibility_created',
                actor: $actor,
                summary: "Added compatibility rule for extra {$extra->name}",
                metadata: [
                    'extra_id' => $extra->id,
                    'rule_id' => $rule->id,
                    'service_type' => $rule->service_type,
                    'vehicle_type' => $rule->vehicle_type,
                    'vehicle_id' => $rule->vehicle_id,
                    'is_compatible' => $rule->is_compatible,
                ],
                auditable: $rule
            );

            return $rule;
        });
    }

    public function deleteCompatibilityRule(ExtraCompatibility $rule, ?User $actor = null): void
    {
        DB::transaction(function () use ($rule, $actor) {
            $ruleId = $rule->id;
            $extraId = $rule->extra_id;
            $extra = $rule->extra;

            $rule->delete();

            $this->auditService->record(
                action: 'fleet.extra.compatibility_deleted',
                actor: $actor,
                summary: "Deleted compatibility rule #{$ruleId} for extra ".($extra ? $extra->name : "#{$extraId}"),
                metadata: [
                    'extra_id' => $extraId,
                    'rule_id' => $ruleId,
                ],
                auditable: $extra
            );
        });
    }

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

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function normalizeAttributesForDiff(array $attributes): array
    {
        $normalized = [];
        foreach ($attributes as $key => $val) {
            if ($val instanceof \BackedEnum) {
                $normalized[$key] = $val->value;
            } elseif ($val instanceof \UnitEnum) {
                $normalized[$key] = $val->name;
            } elseif ($val instanceof CarbonInterface) {
                $normalized[$key] = $val->toIso8601String();
            } elseif (is_array($val)) {
                $normalized[$key] = json_encode($val);
            } else {
                $normalized[$key] = (string) $val;
            }
        }

        return $normalized;
    }
}
