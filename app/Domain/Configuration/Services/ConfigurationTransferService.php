<?php

declare(strict_types=1);

namespace App\Domain\Configuration\Services;

use App\Domain\Audit\Services\AuditService;
use App\Domain\Booking\Enums\BookingStatus;
use App\Domain\Booking\ServiceType;
use App\Domain\Catalog\Enums\VehicleStatus;
use App\Domain\Catalog\Services\ExtraService;
use App\Domain\Catalog\Services\VehicleService;
use App\Domain\Configuration\DTOs\ConfigurationDriftReport;
use App\Domain\Configuration\DTOs\ConfigurationImportResult;
use App\Domain\Configuration\Enums\PackageClassification;
use App\Domain\Configuration\Exceptions\ConfigurationRollbackException;
use App\Domain\Configuration\Exceptions\ConfigurationValidationException;
use App\Domain\Pricing\Services\ExchangeRateService;
use App\Domain\Pricing\Services\PricingRuleService;
use App\Domain\Pricing\Services\RentPolicyService;
use App\Models\Booking;
use App\Models\ConfigurationSnapshot;
use App\Models\ExchangeRateBasis;
use App\Models\Extra;
use App\Models\ExtraCompatibility;
use App\Models\PricingRule;
use App\Models\PricingRuleComponent;
use App\Models\RentPolicy;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleDriverAssignment;
use App\Models\VehicleMedia;
use App\Models\VehicleScheduleBlock;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class ConfigurationTransferService
{
    public function __construct(
        protected ConfigurationCanonicalizer $canonicalizer,
        protected ConfigurationPackageValidator $validator,
        protected VehicleService $vehicleService,
        protected ExtraService $extraService,
        protected PricingRuleService $pricingRuleService,
        protected ExchangeRateService $exchangeRateService,
        protected RentPolicyService $rentPolicyService,
        protected AuditService $auditService,
    ) {}

    /**
     * Export current non-secret, non-PII business configuration into a signed canonical package.
     *
     * @param  array<string, mixed>  $approvalManifest
     * @return array<string, mixed>
     */
    public function export(
        PackageClassification $classification,
        ?User $actor = null,
        array $approvalManifest = [],
        ?string $notes = null,
    ): array {
        $package = $this->buildPackageFromDatabase($classification, $actor, $approvalManifest);
        $this->validator->validate($package);

        $this->auditService->record(
            action: 'configuration.exported',
            actor: $actor,
            summary: "Exported {$classification->value} configuration package ({$package['checksum_sha256']})",
            metadata: [
                'schema_version' => $package['schema_version'],
                'package_classification' => $classification->value,
                'checksum_sha256' => $package['checksum_sha256'],
                'record_counts' => $package['record_counts'],
                'notes' => $notes,
            ],
        );

        return $package;
    }

    /**
     * Validate an incoming package and calculate drift against live DB without mutating state.
     *
     * @param  array<string, mixed>  $package
     */
    public function validateAndDryRun(array $package): ConfigurationDriftReport
    {
        $this->validator->validate($package);

        /** @var array<string, mixed> $payload */
        $payload = $package['payload'];

        return $this->computeDrift($payload);
    }

    /**
     * Import a versioned configuration package through audited domain services (or dry-run validate).
     *
     * @param  array<string, mixed>  $package
     */
    public function import(
        array $package,
        ?User $actor = null,
        bool $dryRun = false,
        ?string $notes = null,
    ): ConfigurationImportResult {
        $drift = $this->validateAndDryRun($package);

        if ($dryRun) {
            $this->auditService->record(
                action: 'configuration.dry_run_validated',
                actor: $actor,
                summary: "Dry-run validated configuration package ({$package['checksum_sha256']})",
                metadata: [
                    'package_classification' => $package['package_classification'],
                    'checksum_sha256' => $package['checksum_sha256'],
                    'drift_summary' => $drift->summaryCounts(),
                ],
            );

            return new ConfigurationImportResult(
                snapshot: null,
                driftReport: $drift,
                dryRun: true,
                wasNoOp: ! $drift->hasChanges(),
                appliedCounts: $drift->summaryCounts(),
            );
        }

        return Cache::lock('spinellanka_config_transfer', 30)->block(10, function () use ($package, $actor, $notes): ConfigurationImportResult {
            return DB::transaction(function () use ($package, $actor, $notes): ConfigurationImportResult {
                /** @var ConfigurationSnapshot|null $activeSnapshot */
                $activeSnapshot = ConfigurationSnapshot::query()
                    ->where('is_active', true)
                    ->lockForUpdate()
                    ->first();

                // Recompute drift inside lock
                /** @var array<string, mixed> $payload */
                $payload = $package['payload'];
                $drift = $this->computeDrift($payload);

                if (
                    $activeSnapshot !== null
                    && hash_equals($activeSnapshot->checksum_sha256, (string) $package['checksum_sha256'])
                    && ! $drift->hasChanges()
                ) {
                    return new ConfigurationImportResult(
                        snapshot: $activeSnapshot,
                        driftReport: $drift,
                        dryRun: false,
                        wasNoOp: true,
                        appliedCounts: ['added' => 0, 'updated' => 0, 'unchanged' => $drift->summaryCounts()['unchanged'], 'missing_from_package' => 0],
                    );
                }

                // Auto-capture baseline snapshot prior to first configuration mutation
                if (ConfigurationSnapshot::query()->lockForUpdate()->count() === 0) {
                    $baselineClassification = app()->isProduction()
                        ? PackageClassification::ProductionApproved
                        : PackageClassification::from((string) $package['package_classification']);
                    $baseline = $this->buildPackageFromDatabase(
                        $baselineClassification,
                        $actor,
                        ['baseline' => true]
                    );
                    ConfigurationSnapshot::create([
                        'version_number' => 1,
                        'schema_version' => (string) $baseline['schema_version'],
                        'package_classification' => $baselineClassification,
                        'source_revision' => (string) $baseline['source_revision'],
                        'checksum_sha256' => (string) $baseline['checksum_sha256'],
                        'record_counts' => $baseline['record_counts'],
                        'payload_json' => $this->canonicalizer->encode($baseline),
                        'is_active' => false,
                        'applied_by_user_id' => $actor?->id,
                        'applied_at' => Carbon::now('UTC'),
                        'notes' => 'Auto-captured pre-import baseline',
                    ]);
                }

                $appliedCounts = $this->applyPayload($payload, $actor, isRollback: false);

                ConfigurationSnapshot::query()->where('is_active', true)->update(['is_active' => false]);
                $nextVersion = ((int) ConfigurationSnapshot::query()->max('version_number')) + 1;

                $snapshot = ConfigurationSnapshot::create([
                    'version_number' => $nextVersion,
                    'schema_version' => (string) $package['schema_version'],
                    'package_classification' => PackageClassification::from((string) $package['package_classification']),
                    'source_revision' => (string) $package['source_revision'],
                    'checksum_sha256' => (string) $package['checksum_sha256'],
                    'record_counts' => $package['record_counts'],
                    'payload_json' => $this->canonicalizer->encode($package),
                    'is_active' => true,
                    'applied_by_user_id' => $actor?->id,
                    'applied_at' => Carbon::now('UTC'),
                    'notes' => $notes,
                ]);

                $this->auditService->record(
                    action: 'configuration.imported',
                    actor: $actor,
                    summary: "Imported configuration v{$snapshot->version_number} ({$snapshot->package_classification->value})",
                    metadata: [
                        'snapshot_id' => $snapshot->id,
                        'version_number' => $snapshot->version_number,
                        'checksum_sha256' => $snapshot->checksum_sha256,
                        'applied_counts' => $appliedCounts,
                        'notes' => $notes,
                    ],
                    auditable: $snapshot,
                );

                return new ConfigurationImportResult(
                    snapshot: $snapshot,
                    driftReport: $drift,
                    dryRun: false,
                    wasNoOp: false,
                    appliedCounts: $appliedCounts,
                );
            });
        });
    }

    /**
     * Roll back to a prior approved/captured configuration snapshot inside an audited transaction.
     */
    public function rollback(
        ?int $targetSnapshotId = null,
        ?User $actor = null,
        string $reason = 'Manual configuration rollback',
    ): ConfigurationImportResult {
        return Cache::lock('spinellanka_config_transfer', 30)->block(10, function () use ($targetSnapshotId, $actor, $reason): ConfigurationImportResult {
            return DB::transaction(function () use ($targetSnapshotId, $actor, $reason): ConfigurationImportResult {
                /** @var ConfigurationSnapshot|null $active */
                $active = ConfigurationSnapshot::query()
                    ->where('is_active', true)
                    ->lockForUpdate()
                    ->first();

                $targetQuery = ConfigurationSnapshot::query()->lockForUpdate();
                if ($targetSnapshotId !== null) {
                    $target = $targetQuery->find($targetSnapshotId);
                } else {
                    $target = $targetQuery
                        ->when($active !== null, fn ($q) => $q->where('id', '!=', $active->id))
                        ->orderByDesc('version_number')
                        ->first();
                }

                if (! $target instanceof ConfigurationSnapshot) {
                    throw new ConfigurationRollbackException('No prior configuration snapshot is available for rollback.');
                }

                if ($active !== null && $target->id === $active->id) {
                    throw new ConfigurationRollbackException('Target rollback snapshot is already the active configuration version.');
                }

                $targetPackage = $target->decodedPackage();
                $isBaseline = (bool) ($targetPackage['approval_manifest']['baseline'] ?? false);
                $this->validator->validate($targetPackage, isBaselineRollback: $isBaseline);

                /** @var array<string, mixed> $payload */
                $payload = $targetPackage['payload'];
                $drift = $this->computeDrift($payload);

                $appliedCounts = $this->applyPayload(
                    $payload,
                    $actor,
                    isRollback: true,
                    rollbackTargetVersion: $target->version_number,
                    allowEmptyVehicles: $isBaseline && empty($payload['vehicles']),
                );

                ConfigurationSnapshot::query()->where('is_active', true)->update(['is_active' => false]);
                $nextVersion = ((int) ConfigurationSnapshot::query()->max('version_number')) + 1;

                $restoredSnapshot = ConfigurationSnapshot::create([
                    'version_number' => $nextVersion,
                    'schema_version' => $target->schema_version,
                    'package_classification' => $target->package_classification,
                    'source_revision' => $target->source_revision,
                    'checksum_sha256' => $target->checksum_sha256,
                    'record_counts' => $target->record_counts,
                    'payload_json' => $target->payload_json,
                    'is_active' => true,
                    'applied_by_user_id' => $actor?->id,
                    'applied_at' => Carbon::now('UTC'),
                    'rolled_back_from_id' => $target->id,
                    'rollback_reason' => $reason,
                    'notes' => "Rolled back to snapshot v{$target->version_number}: {$reason}",
                ]);

                $this->auditService->record(
                    action: 'configuration.rolled_back',
                    actor: $actor,
                    summary: "Rolled back configuration to v{$target->version_number} (new snapshot v{$restoredSnapshot->version_number})",
                    metadata: [
                        'previous_snapshot_id' => $active?->id,
                        'restored_from_snapshot_id' => $target->id,
                        'restored_from_version' => $target->version_number,
                        'new_snapshot_id' => $restoredSnapshot->id,
                        'reason' => $reason,
                        'applied_counts' => $appliedCounts,
                    ],
                    auditable: $restoredSnapshot,
                );

                return new ConfigurationImportResult(
                    snapshot: $restoredSnapshot,
                    driftReport: $drift,
                    dryRun: false,
                    wasNoOp: false,
                    appliedCounts: $appliedCounts,
                );
            });
        });
    }

    /**
     * Build canonical non-secret, non-PII package from current database state.
     *
     * @param  array<string, mixed>  $approvalManifest
     * @return array<string, mixed>
     */
    protected function buildPackageFromDatabase(
        PackageClassification $classification,
        ?User $actor,
        array $approvalManifest,
    ): array {
        $vehicles = Vehicle::query()
            ->where('status', '!=', VehicleStatus::Retired->value)
            ->orderBy('internal_fleet_id')
            ->get();

        $vehicleMapById = $vehicles->keyBy('id');

        $vehiclePayload = $vehicles->map(fn (Vehicle $v): array => [
            'internal_fleet_id' => $v->internal_fleet_id,
            'public_name' => $v->public_name,
            'make' => $v->make,
            'model' => $v->model,
            'year' => $v->year,
            'license_plate' => $v->license_plate,
            'vehicle_type' => $v->vehicle_type->value,
            'passenger_capacity' => $v->passenger_capacity,
            'luggage_capacity' => $v->luggage_capacity,
            'transmission' => $v->transmission->value,
            'has_ac' => (bool) $v->has_ac,
            'fuel_tank_capacity_litres' => $v->getAttribute('fuel_tank_capacity_litres') !== null ? (int) $v->getAttribute('fuel_tank_capacity_litres') : null,
            'accessibility_features' => array_values($v->accessibility_features ?? []),
            'amenities' => array_values($v->amenities ?? []),
            'eligible_for_pickup' => (bool) $v->eligible_for_pickup,
            'eligible_for_transfer' => (bool) $v->eligible_for_transfer,
            'eligible_for_ride' => (bool) $v->eligible_for_ride,
            'eligible_for_rent' => (bool) $v->eligible_for_rent,
            'turnaround_buffer_minutes' => $v->turnaround_buffer_minutes,
            'status' => $v->status->value,
        ])->values()->all();

        $mediaPayload = VehicleMedia::query()
            ->whereIn('vehicle_id', $vehicleMapById->keys())
            ->orderBy('vehicle_id')
            ->orderBy('media_type')
            ->orderBy('sort_order')
            ->get()
            ->map(function (VehicleMedia $media) use ($vehicleMapById): array {
                $vehicle = $vehicleMapById->get($media->vehicle_id);

                return [
                    'vehicle_internal_fleet_id' => $vehicle !== null ? $vehicle->internal_fleet_id : '',
                    'file_path' => $media->file_path,
                    'disk' => $media->disk,
                    'media_type' => $media->media_type->value,
                    'alt_text' => $media->alt_text,
                    'photographer' => $media->photographer,
                    'usage_rights' => $media->usage_rights,
                    'sort_order' => $media->sort_order,
                    'is_approved' => (bool) $media->is_approved,
                ];
            })
            ->values()
            ->all();

        $extras = Extra::query()->where('is_active', true)->orderBy('slug')->get();
        $extraMapById = $extras->keyBy('id');

        $extrasPayload = $extras->map(fn (Extra $e): array => [
            'name' => $e->name,
            'slug' => $e->slug,
            'description' => $e->description,
            'price_minor_units' => $e->price_minor_units,
            'currency' => $e->currency,
            'pricing_type' => $e->pricing_type->value,
            'max_quantity' => $e->max_quantity,
            'is_active' => (bool) $e->is_active,
        ])->values()->all();

        $compatPayload = ExtraCompatibility::query()
            ->whereIn('extra_id', $extraMapById->keys())
            ->orderBy('extra_id')
            ->orderBy('scope_key')
            ->get()
            ->map(function (ExtraCompatibility $c) use ($extraMapById, $vehicleMapById): ?array {
                $extra = $extraMapById->get($c->extra_id);
                if (! $extra) {
                    return null;
                }
                if ($c->vehicle_id !== null && ! $vehicleMapById->has($c->vehicle_id)) {
                    return null;
                }

                return [
                    'extra_slug' => $extra->slug,
                    'service_type' => $c->service_type?->value,
                    'vehicle_type' => $c->vehicle_type?->value,
                    'vehicle_internal_fleet_id' => $c->vehicle_id !== null ? $vehicleMapById->get($c->vehicle_id)?->internal_fleet_id : null,
                    'is_compatible' => (bool) $c->is_compatible,
                ];
            })
            ->filter()
            ->values()
            ->all();

        $rules = PricingRule::query()
            ->with(['vehicle', 'components'])
            ->where('is_active', true)
            ->orderBy('service_type')
            ->orderByDesc('priority')
            ->orderBy('id')
            ->get();

        $componentTotal = 0;
        $rulesPayload = $rules->map(function (PricingRule $rule) use (&$componentTotal): array {
            $configKey = $this->resolvePricingRuleConfigKey($rule);
            $components = $rule->components
                ->sortBy(fn (PricingRuleComponent $c): string => implode('|', [
                    $c->component_type->value,
                    $c->unit->value,
                    (string) $c->amount_minor_units,
                    $c->currency,
                ]))
                ->map(fn (PricingRuleComponent $c): array => [
                    'component_type' => $c->component_type->value,
                    'unit' => $c->unit->value,
                    'amount_minor_units' => $c->amount_minor_units,
                    'currency' => $c->currency,
                    'min_units' => $c->min_units !== null ? (float) $c->min_units : null,
                    'max_units' => $c->max_units !== null ? (float) $c->max_units : null,
                    'is_taxable' => (bool) $c->is_taxable,
                    'is_deposit' => (bool) $c->is_deposit,
                    'metadata' => $c->metadata,
                ])
                ->values()
                ->all();

            $componentTotal += count($components);

            $metadata = is_array($rule->metadata) ? $rule->metadata : [];
            $metadata['config_key'] = $configKey;

            return [
                'config_key' => $configKey,
                'service_type' => $rule->service_type->value,
                'vehicle_internal_fleet_id' => $rule->vehicle?->internal_fleet_id,
                'priority' => $rule->priority,
                'is_active' => (bool) $rule->is_active,
                'effective_from' => $rule->effective_from->copy()->setTimezone('Asia/Colombo')->format('Y-m-d\TH:i:sP'),
                'effective_to' => $rule->effective_to?->copy()->setTimezone('Asia/Colombo')->format('Y-m-d\TH:i:sP'),
                'notes' => $rule->notes,
                'metadata' => $metadata,
                'components' => $components,
            ];
        })->values()->all();

        $latestRate = $this->exchangeRateService->getLatestRate();
        $ratesPayload = $latestRate !== null ? [[
            'rate' => (string) $latestRate->rate,
            'source_note' => $latestRate->source_note,
            'effective_at' => $latestRate->effective_at->copy()->setTimezone('Asia/Colombo')->format('Y-m-d\TH:i:sP'),
        ]] : [];

        $activePolicy = RentPolicy::query()->where('is_active', true)->latest('id')->first();
        $policyPayload = $activePolicy !== null ? [
            'min_driver_age' => $activePolicy->min_driver_age,
            'max_driver_age' => $activePolicy->max_driver_age,
            'min_licence_years' => $activePolicy->min_licence_years,
            'idp_required' => (bool) $activePolicy->idp_required,
            'max_additional_drivers' => $activePolicy->max_additional_drivers,
            'security_deposit_minor_units' => $activePolicy->security_deposit_minor_units,
            'insurance_excess_minor_units' => $activePolicy->insurance_excess_minor_units,
            'daily_mileage_km' => $activePolicy->daily_mileage_km,
            'excess_mileage_rate_minor_units' => $activePolicy->excess_mileage_rate_minor_units,
            'grace_period_minutes' => $activePolicy->grace_period_minutes,
            'extra_hours_threshold' => $activePolicy->extra_hours_threshold,
            'full_day_threshold_hours' => $activePolicy->full_day_threshold_hours,
            'office_handover_available' => (bool) $activePolicy->office_handover_available,
            'airport_handover_available' => (bool) $activePolicy->airport_handover_available,
            'hotel_delivery_available' => (bool) $activePolicy->hotel_delivery_available,
            'hotel_delivery_fee_minor_units' => $activePolicy->hotel_delivery_fee_minor_units,
            'prohibited_roads_text' => $activePolicy->prohibited_roads_text,
            'smoking_penalty_minor_units' => $activePolicy->smoking_penalty_minor_units,
            'is_active' => true,
        ] : [];

        $payload = [
            'vehicles' => $vehiclePayload,
            'vehicle_media' => $mediaPayload,
            'extras' => $extrasPayload,
            'extra_compatibility' => $compatPayload,
            'pricing_rules' => $rulesPayload,
            'exchange_rate_bases' => $ratesPayload,
            'rent_policy' => $policyPayload,
        ];

        $package = [
            'schema_version' => ConfigurationPackageValidator::SCHEMA_VERSION,
            'package_classification' => $classification->value,
            'source_revision' => $this->resolveSourceRevision(),
            'exported_at' => Carbon::now('UTC')->toIso8601String(),
            'exported_by_role' => $actor !== null ? ((string) ($actor->getRoleNames()->first() ?? 'staff')) : 'cli_system',
            'exclusions' => [
                'users',
                'drivers_operational_pii',
                'vehicle_vin_registration_insurance_notes',
                'bookings',
                'booking_contacts',
                'payment_transactions',
                'incidents',
                'handovers',
                'audit_events',
                'secrets',
            ],
            'approval_manifest' => $approvalManifest,
            'record_counts' => [
                'vehicles' => count($vehiclePayload),
                'vehicle_media' => count($mediaPayload),
                'extras' => count($extrasPayload),
                'extra_compatibility' => count($compatPayload),
                'pricing_rules' => count($rulesPayload),
                'pricing_rule_components' => $componentTotal,
                'exchange_rate_bases' => count($ratesPayload),
                'rent_policies' => $policyPayload === [] ? 0 : 1,
            ],
            'payload' => $payload,
        ];

        $package['checksum_sha256'] = $this->canonicalizer->computeChecksum($package);

        return $package;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function computeDrift(array $payload): ConfigurationDriftReport
    {
        $added = ['vehicles' => [], 'extras' => [], 'extra_compatibility' => [], 'pricing_rules' => [], 'vehicle_media' => [], 'exchange_rate_bases' => [], 'rent_policy' => []];
        $updated = ['vehicles' => [], 'extras' => [], 'extra_compatibility' => [], 'pricing_rules' => [], 'vehicle_media' => [], 'exchange_rate_bases' => [], 'rent_policy' => []];
        $unchanged = ['vehicles' => [], 'extras' => [], 'extra_compatibility' => [], 'pricing_rules' => [], 'vehicle_media' => [], 'exchange_rate_bases' => [], 'rent_policy' => []];
        $missing = ['vehicles' => [], 'extras' => [], 'extra_compatibility' => [], 'pricing_rules' => [], 'vehicle_media' => []];
        $warnings = [];

        // Vehicles diff
        $existingVehicles = Vehicle::query()->where('status', '!=', VehicleStatus::Retired->value)->get()->keyBy('internal_fleet_id');
        $incomingVehicleIds = [];
        foreach ($payload['vehicles'] as $vData) {
            $fid = (string) $vData['internal_fleet_id'];
            $incomingVehicleIds[] = $fid;
            $existing = Vehicle::where('internal_fleet_id', $fid)->first();
            if ($existing === null) {
                $added['vehicles'][] = $fid;
            } elseif ($this->vehicleDiffers($existing, $vData)) {
                $updated['vehicles'][] = $fid;
            } else {
                $unchanged['vehicles'][] = $fid;
            }
        }
        foreach ($existingVehicles as $fid => $v) {
            if (! in_array($fid, $incomingVehicleIds, true)) {
                $missing['vehicles'][] = (string) $fid;
            }
        }

        // Extras diff
        $existingExtras = Extra::query()->where('is_active', true)->get()->keyBy('slug');
        $incomingExtraSlugs = [];
        foreach ($payload['extras'] as $eData) {
            $slug = (string) $eData['slug'];
            $incomingExtraSlugs[] = $slug;
            $existing = Extra::where('slug', $slug)->first();
            if ($existing === null) {
                $added['extras'][] = $slug;
            } elseif ($this->extraDiffers($existing, $eData)) {
                $updated['extras'][] = $slug;
            } else {
                $unchanged['extras'][] = $slug;
            }
        }
        foreach ($existingExtras as $slug => $e) {
            if (! in_array($slug, $incomingExtraSlugs, true)) {
                $missing['extras'][] = (string) $slug;
            }
        }

        // Pricing rules diff
        $activeRules = PricingRule::query()->with(['vehicle', 'components'])->where('is_active', true)->get();
        $activeRulesByKey = [];
        foreach ($activeRules as $r) {
            $activeRulesByKey[$this->resolvePricingRuleConfigKey($r)] = $r;
        }
        $incomingRuleKeys = [];
        foreach ($payload['pricing_rules'] as $rData) {
            $key = (string) $rData['config_key'];
            $incomingRuleKeys[] = $key;
            $existing = $activeRulesByKey[$key] ?? null;
            if ($existing === null) {
                $added['pricing_rules'][] = $key;
            } elseif ($this->pricingRuleDiffers($existing, $rData)) {
                $updated['pricing_rules'][] = $key;
            } else {
                $unchanged['pricing_rules'][] = $key;
            }
        }
        foreach ($activeRulesByKey as $key => $r) {
            if (! in_array($key, $incomingRuleKeys, true)) {
                $missing['pricing_rules'][] = $key;
            }
        }

        // Exchange rate diff
        $latestRate = $this->exchangeRateService->getLatestRate();
        foreach ($payload['exchange_rate_bases'] as $rateData) {
            $rateStr = number_format((float) $rateData['rate'], 4, '.', '');
            $note = (string) $rateData['source_note'];
            if ($latestRate === null) {
                $added['exchange_rate_bases'][] = $rateStr;
            } elseif ((string) $latestRate->rate !== $rateStr || $latestRate->source_note !== $note) {
                $updated['exchange_rate_bases'][] = $rateStr;
            } else {
                $unchanged['exchange_rate_bases'][] = $rateStr;
            }
        }

        // VehicleMedia diff
        $existingVehiclesById = Vehicle::query()->pluck('internal_fleet_id', 'id');
        $existingMedia = VehicleMedia::query()->get();
        $existingMediaByKey = [];
        foreach ($existingMedia as $media) {
            $fid = (string) ($existingVehiclesById->get($media->vehicle_id) ?? '');
            if ($fid === '') {
                continue;
            }
            $mKey = "{$fid}:{$media->media_type->value}:{$media->sort_order}:{$media->file_path}";
            $existingMediaByKey[$mKey] = $media;
        }
        $incomingMediaKeys = [];
        foreach ($payload['vehicle_media'] as $mData) {
            $fid = (string) $mData['vehicle_internal_fleet_id'];
            $mType = (string) $mData['media_type'];
            $sortOrder = (int) ($mData['sort_order'] ?? 0);
            $filePath = (string) $mData['file_path'];
            $mKey = "{$fid}:{$mType}:{$sortOrder}:{$filePath}";
            $incomingMediaKeys[] = $mKey;
            $existing = $existingMediaByKey[$mKey] ?? null;
            if ($existing === null) {
                $added['vehicle_media'][] = $mKey;
            } elseif (
                $existing->disk !== (string) ($mData['disk'] ?? 'public')
                || $existing->alt_text !== (string) $mData['alt_text']
                || $existing->photographer !== ($mData['photographer'] ?? null)
                || $existing->usage_rights !== ($mData['usage_rights'] ?? null)
                || (bool) $existing->is_approved !== (bool) ($mData['is_approved'] ?? false)
            ) {
                $updated['vehicle_media'][] = $mKey;
            } else {
                $unchanged['vehicle_media'][] = $mKey;
            }
        }
        foreach ($existingMediaByKey as $mKey => $media) {
            if ($media->is_approved && ! in_array($mKey, $incomingMediaKeys, true)) {
                $missing['vehicle_media'][] = $mKey;
            }
        }

        // ExtraCompatibility diff
        $existingExtrasById = Extra::query()->pluck('slug', 'id');
        $existingCompatByKey = [];
        foreach (ExtraCompatibility::query()->get() as $compat) {
            $slug = (string) ($existingExtrasById->get($compat->extra_id) ?? '');
            if ($slug === '') {
                continue;
            }
            $fid = $compat->vehicle_id !== null ? (string) ($existingVehiclesById->get($compat->vehicle_id) ?? '*') : '*';
            $serviceType = $compat->service_type !== null ? $compat->service_type->value : '*';
            $vehicleType = $compat->vehicle_type !== null ? $compat->vehicle_type->value : '*';
            $cKey = sprintf('%s:%s:%s:%s', $slug, $serviceType, $vehicleType, $fid);
            $existingCompatByKey[$cKey] = $compat;
        }
        $incomingCompatKeys = [];
        foreach ($payload['extra_compatibility'] as $cData) {
            $cKey = sprintf(
                '%s:%s:%s:%s',
                (string) $cData['extra_slug'],
                $cData['service_type'] ?? '*',
                $cData['vehicle_type'] ?? '*',
                $cData['vehicle_internal_fleet_id'] ?? '*'
            );
            $incomingCompatKeys[] = $cKey;
            $existing = $existingCompatByKey[$cKey] ?? null;
            if ($existing === null) {
                $added['extra_compatibility'][] = $cKey;
            } elseif ((bool) $existing->is_compatible !== (bool) ($cData['is_compatible'] ?? true)) {
                $updated['extra_compatibility'][] = $cKey;
            } else {
                $unchanged['extra_compatibility'][] = $cKey;
            }
        }
        foreach ($existingCompatByKey as $cKey => $compat) {
            if (! in_array($cKey, $incomingCompatKeys, true)) {
                $missing['extra_compatibility'][] = $cKey;
            }
        }

        // Rent policy diff
        if ($payload['rent_policy'] !== []) {
            $currentPolicy = RentPolicy::query()->where('is_active', true)->latest('id')->first();
            if ($currentPolicy === null) {
                $added['rent_policy'][] = 'active_rent_policy';
            } elseif ($this->rentPolicyDiffers($currentPolicy, $payload['rent_policy'])) {
                $updated['rent_policy'][] = 'active_rent_policy';
            } else {
                $unchanged['rent_policy'][] = 'active_rent_policy';
            }
        }

        return new ConfigurationDriftReport(
            schemaValid: true,
            checksumValid: true,
            isValid: true,
            added: $added,
            updated: $updated,
            unchanged: $unchanged,
            missingFromPackage: $missing,
            warnings: $warnings,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, int>
     */
    protected function applyPayload(
        array $payload,
        ?User $actor,
        bool $isRollback = false,
        ?int $rollbackTargetVersion = null,
        bool $allowEmptyVehicles = false,
    ): array {
        $counts = ['vehicles' => 0, 'vehicle_media' => 0, 'extras' => 0, 'pricing_rules' => 0, 'exchange_rate_bases' => 0, 'rent_policies' => 0];

        // 1. Vehicles (2-pass plate swap protection + active schedule/assignment/booking safety)
        $incomingFleetIds = array_map(fn (array $v): string => (string) $v['internal_fleet_id'], $payload['vehicles']);
        $vehiclesToRetireQuery = Vehicle::query()->where('status', '!=', VehicleStatus::Retired->value);
        if ($incomingFleetIds !== []) {
            $vehiclesToRetireQuery->whereNotIn('internal_fleet_id', $incomingFleetIds);
        }
        $vehiclesToRetire = $vehiclesToRetireQuery->get();

        foreach ($vehiclesToRetire as $orphan) {
            $hasOpenAssignment = VehicleDriverAssignment::query()
                ->where('vehicle_id', $orphan->id)
                ->where('is_permanent', true)
                ->whereNull('ends_at')
                ->exists();
            $hasFutureBlock = VehicleScheduleBlock::query()
                ->where('vehicle_id', $orphan->id)
                ->where('blocks_availability', true)
                ->where('ends_at', '>', Carbon::now('Asia/Colombo'))
                ->exists();
            $hasActiveBooking = Booking::query()
                ->where('vehicle_id', $orphan->id)
                ->whereIn('status', [
                    BookingStatus::Submitted->value,
                    BookingStatus::UnderReview->value,
                    BookingStatus::ProposalSent->value,
                    BookingStatus::AwaitingPayment->value,
                    BookingStatus::Confirmed->value,
                ])
                ->exists();

            if ($hasOpenAssignment || $hasFutureBlock || $hasActiveBooking) {
                throw new ConfigurationValidationException(
                    "Cannot retire vehicle '{$orphan->internal_fleet_id}' during configuration sync while it has open permanent driver assignments, active future schedule blocks, or active bookings."
                );
            }

            $this->vehicleService->updateStatus($orphan, VehicleStatus::Retired, 'Retired by configuration sync', $actor);
        }

        if (! $allowEmptyVehicles) {
            // Pass A: clear colliding license plates temporarily (including retired/replaced rows reusing a plate)
            $targetPlates = array_map(fn (array $v): string => strtoupper(trim((string) $v['license_plate'])), $payload['vehicles']);
            foreach ($payload['vehicles'] as $vData) {
                $existing = Vehicle::where('internal_fleet_id', (string) $vData['internal_fleet_id'])->first();
                $currentPlate = $existing !== null ? strtoupper(trim($existing->license_plate)) : null;
                $desiredPlate = strtoupper(trim((string) $vData['license_plate']));
                if (($existing === null || $currentPlate !== $desiredPlate) && in_array($desiredPlate, $targetPlates, true)) {
                    $collidingQuery = Vehicle::query()->where('license_plate', $desiredPlate);
                    if ($existing !== null) {
                        $collidingQuery->where('id', '!=', $existing->id);
                    }
                    $collidingOwner = $collidingQuery->first();
                    if ($collidingOwner !== null) {
                        $this->vehicleService->updateVehicle(
                            $collidingOwner,
                            ['license_plate' => 'SWAP-'.$collidingOwner->id.'-'.substr(md5((string) microtime(true)), 0, 6)],
                            $actor
                        );
                    }
                }
            }

            // Pass B: upsert vehicles through VehicleService
            foreach ($payload['vehicles'] as $vData) {
                $attributes = [
                    'internal_fleet_id' => (string) $vData['internal_fleet_id'],
                    'public_name' => (string) $vData['public_name'],
                    'make' => (string) $vData['make'],
                    'model' => (string) $vData['model'],
                    'year' => (int) $vData['year'],
                    'license_plate' => (string) $vData['license_plate'],
                    'vehicle_type' => (string) $vData['vehicle_type'],
                    'passenger_capacity' => (int) $vData['passenger_capacity'],
                    'luggage_capacity' => (int) $vData['luggage_capacity'],
                    'transmission' => (string) $vData['transmission'],
                    'has_ac' => (bool) $vData['has_ac'],
                    'accessibility_features' => $vData['accessibility_features'] ?? [],
                    'amenities' => $vData['amenities'] ?? [],
                    'eligible_for_pickup' => (bool) $vData['eligible_for_pickup'],
                    'eligible_for_transfer' => (bool) $vData['eligible_for_transfer'],
                    'eligible_for_ride' => (bool) $vData['eligible_for_ride'],
                    'eligible_for_rent' => (bool) $vData['eligible_for_rent'],
                    'turnaround_buffer_minutes' => (int) $vData['turnaround_buffer_minutes'],
                    'status' => (string) $vData['status'],
                ];
                if (array_key_exists('fuel_tank_capacity_litres', $vData) && $vData['fuel_tank_capacity_litres'] !== null) {
                    $attributes['fuel_tank_capacity_litres'] = (int) $vData['fuel_tank_capacity_litres'];
                }

                $existing = Vehicle::where('internal_fleet_id', (string) $vData['internal_fleet_id'])->first();
                if ($existing === null) {
                    $this->vehicleService->createVehicle($attributes, $actor);
                    $counts['vehicles']++;
                } elseif ($this->vehicleDiffers($existing, $vData)) {
                    $this->vehicleService->updateVehicle($existing, $attributes, $actor);
                    $counts['vehicles']++;
                }
            }
        }

        $vehicleByFleetId = Vehicle::query()->get()->keyBy('internal_fleet_id');

        // 2. VehicleMedia (never delete rows on sync to preserve disk files across rollback; audit mutations)
        $touchedMediaIds = [];
        foreach ($payload['vehicle_media'] as $mData) {
            $vehicle = $vehicleByFleetId->get((string) $mData['vehicle_internal_fleet_id']);
            if (! $vehicle instanceof Vehicle) {
                continue;
            }
            $media = VehicleMedia::updateOrCreate(
                [
                    'vehicle_id' => $vehicle->id,
                    'media_type' => (string) $mData['media_type'],
                    'sort_order' => (int) ($mData['sort_order'] ?? 0),
                    'file_path' => (string) $mData['file_path'],
                ],
                [
                    'disk' => (string) ($mData['disk'] ?? 'public'),
                    'alt_text' => (string) $mData['alt_text'],
                    'photographer' => $mData['photographer'] ?? null,
                    'usage_rights' => $mData['usage_rights'] ?? null,
                    'is_approved' => (bool) ($mData['is_approved'] ?? false),
                ]
            );
            $touchedMediaIds[] = $media->id;
            $counts['vehicle_media']++;
        }
        $mediaToDeactivateQuery = VehicleMedia::query()->where('is_approved', true);
        if ($touchedMediaIds !== []) {
            $mediaToDeactivateQuery->whereNotIn('id', $touchedMediaIds);
        }
        $deactivatedMediaCount = $mediaToDeactivateQuery->update(['is_approved' => false]);
        if ($counts['vehicle_media'] > 0 || $deactivatedMediaCount > 0) {
            $this->auditService->record(
                action: 'catalog.vehicle_media.synced',
                actor: $actor,
                summary: sprintf('Synced %d vehicle media items and deactivated %d omitted items.', $counts['vehicle_media'], $deactivatedMediaCount),
                metadata: [
                    'synced_count' => $counts['vehicle_media'],
                    'deactivated_count' => $deactivatedMediaCount,
                    'is_rollback' => $isRollback,
                ],
            );
        }

        // 3. Extras & Compatibility
        $incomingExtraSlugs = [];
        foreach ($payload['extras'] as $eData) {
            $slug = (string) $eData['slug'];
            $incomingExtraSlugs[] = $slug;
            $attributes = [
                'name' => (string) $eData['name'],
                'slug' => $slug,
                'description' => $eData['description'] ?? null,
                'price_minor_units' => (int) $eData['price_minor_units'],
                'currency' => (string) ($eData['currency'] ?? 'USD'),
                'pricing_type' => (string) ($eData['pricing_type'] ?? 'per_booking'),
                'max_quantity' => (int) ($eData['max_quantity'] ?? 1),
                'is_active' => (bool) ($eData['is_active'] ?? true),
            ];
            $existing = Extra::where('slug', $slug)->first();
            if ($existing === null) {
                $this->extraService->createExtra($attributes, $actor);
                $counts['extras']++;
            } elseif ($this->extraDiffers($existing, $eData)) {
                $this->extraService->updateExtra($existing, $attributes, $actor);
                $counts['extras']++;
            }
        }
        $extrasToDeactivateQuery = Extra::query()->where('is_active', true);
        if ($incomingExtraSlugs !== []) {
            $extrasToDeactivateQuery->whereNotIn('slug', $incomingExtraSlugs);
        }
        $extrasToDeactivateQuery->get()->each(fn (Extra $e) => $this->extraService->updateExtra($e, ['is_active' => false], $actor));

        $extraBySlug = Extra::query()->get()->keyBy('slug');
        foreach ($extraBySlug as $extra) {
            $desiredForExtra = array_values(array_filter(
                is_array($payload['extra_compatibility']) ? $payload['extra_compatibility'] : [],
                static fn (mixed $c): bool => is_array($c) && (string) ($c['extra_slug'] ?? '') === $extra->slug
            ));
            $existingRules = $extra->compatibilities()->get();

            $desiredScopes = [];
            foreach ($desiredForExtra as $cData) {
                $vehicleId = isset($cData['vehicle_internal_fleet_id'])
                    ? $vehicleByFleetId->get((string) $cData['vehicle_internal_fleet_id'])?->id
                    : null;
                $scopeKey = sprintf(
                    '%d:%s:%s:%d',
                    $extra->id,
                    $cData['service_type'] ?? '*',
                    $cData['vehicle_type'] ?? '*',
                    $vehicleId ?? 0,
                );
                $desiredScopes[] = $scopeKey;

                $match = $existingRules->firstWhere('scope_key', $scopeKey);
                if ($match === null) {
                    $this->extraService->createCompatibilityRule($extra, [
                        'service_type' => $cData['service_type'] ?? null,
                        'vehicle_type' => $cData['vehicle_type'] ?? null,
                        'vehicle_id' => $vehicleId,
                        'is_compatible' => (bool) ($cData['is_compatible'] ?? true),
                    ], $actor);
                } elseif ((bool) $match->is_compatible !== (bool) ($cData['is_compatible'] ?? true)) {
                    $this->extraService->deleteCompatibilityRule($match, $actor);
                    $this->extraService->createCompatibilityRule($extra, [
                        'service_type' => $cData['service_type'] ?? null,
                        'vehicle_type' => $cData['vehicle_type'] ?? null,
                        'vehicle_id' => $vehicleId,
                        'is_compatible' => (bool) ($cData['is_compatible'] ?? true),
                    ], $actor);
                }
            }

            foreach ($existingRules as $oldRule) {
                if (! in_array($oldRule->scope_key, $desiredScopes, true)) {
                    $this->extraService->deleteCompatibilityRule($oldRule, $actor);
                }
            }
        }

        // 4. PricingRules (preserve immutable Estimate/EstimateItem FKs)
        $activeRules = PricingRule::query()->with(['vehicle', 'components'])->where('is_active', true)->get();
        $activeByKey = [];
        foreach ($activeRules as $rule) {
            $activeByKey[$this->resolvePricingRuleConfigKey($rule)] = $rule;
        }

        $incomingRuleKeys = [];
        foreach ($payload['pricing_rules'] as $rData) {
            $key = (string) $rData['config_key'];
            $incomingRuleKeys[] = $key;
            $existing = $activeByKey[$key] ?? null;

            if ($existing !== null && ! $this->pricingRuleDiffers($existing, $rData)) {
                continue;
            }

            if ($existing !== null) {
                $this->pricingRuleService->deactivateRule($existing, $actor);
            }

            $vehicleId = ! empty($rData['vehicle_internal_fleet_id'])
                ? $vehicleByFleetId->get((string) $rData['vehicle_internal_fleet_id'])?->id
                : null;

            $meta = is_array($rData['metadata'] ?? null) ? $rData['metadata'] : [];
            $meta['config_key'] = $key;

            $this->pricingRuleService->createRule([
                'service_type' => ServiceType::from((string) $rData['service_type']),
                'vehicle_id' => $vehicleId,
                'priority' => (int) ($rData['priority'] ?? 0),
                'is_active' => (bool) ($rData['is_active'] ?? true),
                'effective_from' => (string) $rData['effective_from'],
                'effective_to' => $rData['effective_to'] ?? null,
                'notes' => $rData['notes'] ?? null,
                'metadata' => $meta,
                'components' => $rData['components'],
            ], $actor);
            $counts['pricing_rules']++;
        }

        foreach ($activeByKey as $key => $rule) {
            if (! in_array($key, $incomingRuleKeys, true)) {
                $this->pricingRuleService->deactivateRule($rule, $actor);
            }
        }

        // 5. ExchangeRateBasis
        foreach ($payload['exchange_rate_bases'] as $rateData) {
            $rateStr = number_format((float) $rateData['rate'], 4, '.', '');
            $sourceNote = (string) $rateData['source_note'];
            $latest = $this->exchangeRateService->getLatestRate();

            if ($latest !== null && (string) $latest->rate === $rateStr && $latest->source_note === $sourceNote) {
                continue;
            }

            $effectiveAt = Carbon::now('Asia/Colombo');

            $note = $isRollback && $rollbackTargetVersion !== null && ! str_starts_with($sourceNote, '[Rollback')
                ? "[Rollback v{$rollbackTargetVersion}] {$sourceNote}"
                : $sourceNote;

            $this->exchangeRateService->recordRate($rateStr, $note, $effectiveAt, $actor);
            $counts['exchange_rate_bases']++;
        }

        // 6. RentPolicy
        if ($payload['rent_policy'] !== []) {
            $currentPolicy = RentPolicy::query()->where('is_active', true)->latest('id')->first();
            if ($currentPolicy === null || $this->rentPolicyDiffers($currentPolicy, $payload['rent_policy'])) {
                $this->rentPolicyService->updateActivePolicy($payload['rent_policy'], $actor);
                $counts['rent_policies']++;
            }
        }

        return $counts;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function vehicleDiffers(Vehicle $vehicle, array $data): bool
    {
        $left = [
            'public_name' => $vehicle->public_name,
            'make' => $vehicle->make,
            'model' => $vehicle->model,
            'year' => $vehicle->year,
            'license_plate' => $vehicle->license_plate,
            'vehicle_type' => $vehicle->vehicle_type->value,
            'passenger_capacity' => $vehicle->passenger_capacity,
            'luggage_capacity' => $vehicle->luggage_capacity,
            'transmission' => $vehicle->transmission->value,
            'has_ac' => (bool) $vehicle->has_ac,
            'accessibility_features' => array_values($vehicle->accessibility_features ?? []),
            'amenities' => array_values($vehicle->amenities ?? []),
            'eligible_for_pickup' => (bool) $vehicle->eligible_for_pickup,
            'eligible_for_transfer' => (bool) $vehicle->eligible_for_transfer,
            'eligible_for_ride' => (bool) $vehicle->eligible_for_ride,
            'eligible_for_rent' => (bool) $vehicle->eligible_for_rent,
            'turnaround_buffer_minutes' => $vehicle->turnaround_buffer_minutes,
            'fuel_tank_capacity_litres' => $vehicle->fuel_tank_capacity_litres,
            'status' => $vehicle->status->value,
        ];

        $right = [
            'public_name' => (string) $data['public_name'],
            'make' => (string) $data['make'],
            'model' => (string) $data['model'],
            'year' => (int) $data['year'],
            'license_plate' => (string) $data['license_plate'],
            'vehicle_type' => (string) $data['vehicle_type'],
            'passenger_capacity' => (int) $data['passenger_capacity'],
            'luggage_capacity' => (int) $data['luggage_capacity'],
            'transmission' => (string) $data['transmission'],
            'has_ac' => (bool) $data['has_ac'],
            'accessibility_features' => array_values($data['accessibility_features'] ?? []),
            'amenities' => array_values($data['amenities'] ?? []),
            'eligible_for_pickup' => (bool) $data['eligible_for_pickup'],
            'eligible_for_transfer' => (bool) $data['eligible_for_transfer'],
            'eligible_for_ride' => (bool) $data['eligible_for_ride'],
            'eligible_for_rent' => (bool) $data['eligible_for_rent'],
            'turnaround_buffer_minutes' => (int) $data['turnaround_buffer_minutes'],
            'fuel_tank_capacity_litres' => array_key_exists('fuel_tank_capacity_litres', $data) && $data['fuel_tank_capacity_litres'] !== null
                ? (int) $data['fuel_tank_capacity_litres']
                : null,
            'status' => (string) $data['status'],
        ];

        return $this->canonicalizer->encode($left) !== $this->canonicalizer->encode($right);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function extraDiffers(Extra $extra, array $data): bool
    {
        $left = [
            'name' => $extra->name,
            'description' => $extra->description,
            'price_minor_units' => $extra->price_minor_units,
            'currency' => $extra->currency,
            'pricing_type' => $extra->pricing_type->value,
            'max_quantity' => $extra->max_quantity,
            'is_active' => (bool) $extra->is_active,
        ];

        $right = [
            'name' => (string) $data['name'],
            'description' => $data['description'] ?? null,
            'price_minor_units' => (int) $data['price_minor_units'],
            'currency' => (string) ($data['currency'] ?? 'USD'),
            'pricing_type' => (string) ($data['pricing_type'] ?? 'per_booking'),
            'max_quantity' => (int) ($data['max_quantity'] ?? 1),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];

        return $this->canonicalizer->encode($left) !== $this->canonicalizer->encode($right);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function pricingRuleDiffers(PricingRule $rule, array $data): bool
    {
        $leftComponents = $rule->components
            ->map(fn (PricingRuleComponent $c): array => [
                'component_type' => $c->component_type->value,
                'unit' => $c->unit->value,
                'amount_minor_units' => $c->amount_minor_units,
                'currency' => $c->currency,
                'min_units' => $c->min_units !== null ? (float) $c->min_units : null,
                'max_units' => $c->max_units !== null ? (float) $c->max_units : null,
                'is_taxable' => (bool) $c->is_taxable,
                'is_deposit' => (bool) $c->is_deposit,
            ])
            ->sortBy(fn (array $c): string => implode('|', [$c['component_type'], $c['unit'], (string) $c['amount_minor_units']]))
            ->values()
            ->all();

        $rawRightComponents = is_array($data['components']) ? $data['components'] : [];
        $rightComponents = array_values(array_map(
            static fn (array $c): array => [
                'component_type' => (string) $c['component_type'],
                'unit' => (string) $c['unit'],
                'amount_minor_units' => (int) $c['amount_minor_units'],
                'currency' => (string) ($c['currency'] ?? 'USD'),
                'min_units' => isset($c['min_units']) ? (float) $c['min_units'] : null,
                'max_units' => isset($c['max_units']) ? (float) $c['max_units'] : null,
                'is_taxable' => (bool) ($c['is_taxable'] ?? false),
                'is_deposit' => (bool) ($c['is_deposit'] ?? false),
            ],
            array_filter($rawRightComponents, 'is_array')
        ));
        usort(
            $rightComponents,
            static fn (array $a, array $b): int => implode('|', [$a['component_type'], $a['unit'], (string) $a['amount_minor_units']])
                <=> implode('|', [$b['component_type'], $b['unit'], (string) $b['amount_minor_units']])
        );

        $left = [
            'service_type' => $rule->service_type->value,
            'vehicle_internal_fleet_id' => $rule->vehicle?->internal_fleet_id,
            'priority' => $rule->priority,
            'is_active' => (bool) $rule->is_active,
            'effective_from' => $rule->effective_from->copy()->setTimezone('Asia/Colombo')->format('Y-m-d\TH:i:sP'),
            'effective_to' => $rule->effective_to?->copy()->setTimezone('Asia/Colombo')->format('Y-m-d\TH:i:sP'),
            'notes' => $rule->notes,
            'components' => $leftComponents,
        ];

        $right = [
            'service_type' => (string) $data['service_type'],
            'vehicle_internal_fleet_id' => $data['vehicle_internal_fleet_id'] ?? null,
            'priority' => (int) ($data['priority'] ?? 0),
            'is_active' => (bool) ($data['is_active'] ?? true),
            'effective_from' => Carbon::parse((string) $data['effective_from'])->setTimezone('Asia/Colombo')->format('Y-m-d\TH:i:sP'),
            'effective_to' => ! empty($data['effective_to']) ? Carbon::parse((string) $data['effective_to'])->setTimezone('Asia/Colombo')->format('Y-m-d\TH:i:sP') : null,
            'notes' => $data['notes'] ?? null,
            'components' => $rightComponents,
        ];

        return $this->canonicalizer->encode($left) !== $this->canonicalizer->encode($right);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function rentPolicyDiffers(RentPolicy $policy, array $data): bool
    {
        $left = [
            'min_driver_age' => $policy->min_driver_age,
            'max_driver_age' => $policy->max_driver_age,
            'min_licence_years' => $policy->min_licence_years,
            'idp_required' => (bool) $policy->idp_required,
            'max_additional_drivers' => $policy->max_additional_drivers,
            'security_deposit_minor_units' => $policy->security_deposit_minor_units,
            'insurance_excess_minor_units' => $policy->insurance_excess_minor_units,
            'daily_mileage_km' => $policy->daily_mileage_km,
            'excess_mileage_rate_minor_units' => $policy->excess_mileage_rate_minor_units,
            'grace_period_minutes' => $policy->grace_period_minutes,
            'extra_hours_threshold' => $policy->extra_hours_threshold,
            'full_day_threshold_hours' => $policy->full_day_threshold_hours,
            'office_handover_available' => (bool) $policy->office_handover_available,
            'airport_handover_available' => (bool) $policy->airport_handover_available,
            'hotel_delivery_available' => (bool) $policy->hotel_delivery_available,
            'hotel_delivery_fee_minor_units' => $policy->hotel_delivery_fee_minor_units,
            'prohibited_roads_text' => $policy->prohibited_roads_text,
            'smoking_penalty_minor_units' => $policy->smoking_penalty_minor_units,
        ];

        $right = [
            'min_driver_age' => (int) $data['min_driver_age'],
            'max_driver_age' => (int) $data['max_driver_age'],
            'min_licence_years' => (int) $data['min_licence_years'],
            'idp_required' => (bool) $data['idp_required'],
            'max_additional_drivers' => (int) $data['max_additional_drivers'],
            'security_deposit_minor_units' => (int) $data['security_deposit_minor_units'],
            'insurance_excess_minor_units' => (int) $data['insurance_excess_minor_units'],
            'daily_mileage_km' => (int) $data['daily_mileage_km'],
            'excess_mileage_rate_minor_units' => (int) $data['excess_mileage_rate_minor_units'],
            'grace_period_minutes' => (int) $data['grace_period_minutes'],
            'extra_hours_threshold' => (int) $data['extra_hours_threshold'],
            'full_day_threshold_hours' => (int) $data['full_day_threshold_hours'],
            'office_handover_available' => (bool) $data['office_handover_available'],
            'airport_handover_available' => (bool) $data['airport_handover_available'],
            'hotel_delivery_available' => (bool) $data['hotel_delivery_available'],
            'hotel_delivery_fee_minor_units' => (int) $data['hotel_delivery_fee_minor_units'],
            'prohibited_roads_text' => $data['prohibited_roads_text'] ?? null,
            'smoking_penalty_minor_units' => (int) $data['smoking_penalty_minor_units'],
        ];

        return $this->canonicalizer->encode($left) !== $this->canonicalizer->encode($right);
    }

    protected function resolvePricingRuleConfigKey(PricingRule $rule): string
    {
        $metadata = is_array($rule->metadata) ? $rule->metadata : [];
        if (! empty($metadata['config_key']) && is_string($metadata['config_key'])) {
            return $metadata['config_key'];
        }

        $fleetKey = $rule->vehicle !== null ? $rule->vehicle->internal_fleet_id : 'default';

        return sprintf(
            '%s:%s:%d:%s',
            $rule->service_type->value,
            $fleetKey,
            $rule->priority,
            $rule->effective_from->copy()->setTimezone('Asia/Colombo')->format('YmdHis'),
        );
    }

    protected function resolveSourceRevision(): string
    {
        try {
            $headFile = base_path('.git/HEAD');
            if (is_file($headFile)) {
                $head = trim((string) file_get_contents($headFile));
                if (str_starts_with($head, 'ref: ')) {
                    $refPath = base_path('.git/'.substr($head, 5));
                    if (is_file($refPath)) {
                        return substr(trim((string) file_get_contents($refPath)), 0, 12);
                    }
                }

                return substr($head, 0, 12);
            }
        } catch (Throwable) {
            // Fallback below
        }

        return 'unversioned-local';
    }
}
