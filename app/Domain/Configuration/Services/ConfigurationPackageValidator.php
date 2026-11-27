<?php

declare(strict_types=1);

namespace App\Domain\Configuration\Services;

use App\Domain\Booking\ServiceType;
use App\Domain\Catalog\Enums\MediaType;
use App\Domain\Catalog\Enums\TransmissionType;
use App\Domain\Catalog\Enums\VehicleStatus;
use App\Domain\Catalog\Enums\VehicleType;
use App\Domain\Configuration\Enums\PackageClassification;
use App\Domain\Configuration\Exceptions\ConfigurationSecurityException;
use App\Domain\Configuration\Exceptions\ConfigurationValidationException;
use Illuminate\Support\Facades\Storage;

class ConfigurationPackageValidator
{
    public const string SCHEMA_VERSION = '1.0';

    public const int REQUIRED_VEHICLE_COUNT = 5;

    /**
     * Required top-level keys in a configuration package.
     *
     * @var array<int, string>
     */
    public const array REQUIRED_ENVELOPE_KEYS = [
        'schema_version',
        'package_classification',
        'source_revision',
        'exported_at',
        'exported_by_role',
        'exclusions',
        'approval_manifest',
        'record_counts',
        'checksum_sha256',
        'payload',
    ];

    /**
     * Required payload section keys.
     *
     * @var array<int, string>
     */
    public const array REQUIRED_PAYLOAD_KEYS = [
        'vehicles',
        'vehicle_media',
        'extras',
        'extra_compatibility',
        'pricing_rules',
        'exchange_rate_bases',
        'rent_policy',
    ];

    /**
     * Keys strictly forbidden inside configuration payloads or unexpected top-level fields.
     *
     * @var array<int, string>
     */
    public const array FORBIDDEN_KEYS = [
        'full_name',
        'phone',
        'whatsapp',
        'email',
        'license_number',
        'license_expiry',
        'vin',
        'registration_expiry',
        'insurance_expiry',
        'internal_notes',
        'password',
        'current_password',
        'reauth_password',
        'password_confirmation',
        'secret',
        'app_authentication_secret',
        'app_authentication_recovery_codes',
        'remember_token',
        'token',
        'api_key',
        'dsn',
        'app_key',
        'merchant_secret',
        'system_user_token',
        'server_token',
        'private_key',
        'driver_operational_pii',
        'drivers',
        'users',
        'bookings',
        'booking_contacts',
        'payment_transactions',
        'payment_events',
        'refunds',
        'incidents',
        'handovers',
        'audit_events',
        'guest_access_tokens',
        'driver_access_tokens',
    ];

    public function __construct(
        protected ConfigurationCanonicalizer $canonicalizer,
    ) {}

    /**
     * Validate structure, checksum, exclusions, 5-vehicle invariant, and classification rules.
     *
     * @param  array<string, mixed>  $package
     */
    public function validate(array $package, bool $isBaselineRollback = false): void
    {
        $this->validateEnvelope($package);
        $this->validateChecksum($package);

        $classification = PackageClassification::from((string) $package['package_classification']);

        /** @var array<string, mixed> $payload */
        $payload = $package['payload'];

        $this->scanForForbiddenKeys($payload, 'payload');
        $this->scanPayloadForSensitiveValues($payload, 'payload');

        if (isset($package['approval_manifest']) && is_array($package['approval_manifest'])) {
            $this->scanForForbiddenKeys($package['approval_manifest'], 'approval_manifest');
            $this->scanPayloadForSensitiveValues($package['approval_manifest'], 'approval_manifest');
        }

        if ($isBaselineRollback) {
            return;
        }

        $this->validatePayloadSections($payload);
        $this->validateRecordCounts($package, $payload);
        $this->validateVehicles($payload['vehicles']);
        $knownFleetIds = array_map(static fn (array $v): string => (string) $v['internal_fleet_id'], $payload['vehicles']);
        $this->validateExtras($payload['extras']);
        $knownExtraSlugs = array_map(static fn (array $e): string => (string) $e['slug'], $payload['extras']);
        $this->validateExtraCompatibility($payload['extra_compatibility'], $knownExtraSlugs, $knownFleetIds);
        $this->validateVehicleMedia($payload['vehicle_media'], $classification, $knownFleetIds);
        $this->validatePricingRules($payload['pricing_rules'], $knownFleetIds);
        $this->validateExchangeRateBases($payload['exchange_rate_bases']);
        $this->validateRentPolicy($payload['rent_policy']);
        $this->validateClassificationGuardrails($classification, $package, $payload);
    }

    /**
     * @param  array<string, mixed>  $package
     */
    protected function validateEnvelope(array $package): void
    {
        foreach (self::REQUIRED_ENVELOPE_KEYS as $key) {
            if (! array_key_exists($key, $package)) {
                throw new ConfigurationValidationException("Missing required configuration envelope key: {$key}.");
            }
        }

        $unknownTopLevel = array_diff(array_keys($package), self::REQUIRED_ENVELOPE_KEYS);
        if ($unknownTopLevel !== []) {
            throw new ConfigurationValidationException(
                'Unexpected top-level keys in configuration package: '.implode(', ', $unknownTopLevel)
            );
        }

        if ((string) $package['schema_version'] !== self::SCHEMA_VERSION) {
            throw new ConfigurationValidationException(
                "Unsupported configuration schema_version '{$package['schema_version']}'; expected '".self::SCHEMA_VERSION."'."
            );
        }

        if (PackageClassification::tryFrom((string) $package['package_classification']) === null) {
            throw new ConfigurationValidationException(
                "Invalid package_classification '{$package['package_classification']}'."
            );
        }

        if (! is_array($package['payload']) || ! is_array($package['record_counts']) || ! is_array($package['approval_manifest'])) {
            throw new ConfigurationValidationException('Configuration package payload, record_counts, and approval_manifest must be arrays.');
        }

        if (trim((string) $package['source_revision']) === '') {
            throw new ConfigurationValidationException('Configuration package source_revision must be non-empty.');
        }
    }

    /**
     * @param  array<string, mixed>  $package
     */
    protected function validateChecksum(array $package): void
    {
        $provided = (string) ($package['checksum_sha256'] ?? '');
        if ($provided === '') {
            throw new ConfigurationValidationException('Configuration package checksum_sha256 is missing.');
        }

        $expected = $this->canonicalizer->computeChecksum($package);
        if (! hash_equals($expected, $provided)) {
            throw new ConfigurationValidationException('Configuration package checksum_sha256 mismatch; package may be corrupt or tampered.');
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function validatePayloadSections(array $payload): void
    {
        foreach (self::REQUIRED_PAYLOAD_KEYS as $section) {
            if (! array_key_exists($section, $payload) || ! is_array($payload[$section])) {
                throw new ConfigurationValidationException("Configuration payload missing required section array: {$section}.");
            }
        }
    }

    /**
     * @param  array<string, mixed>  $package
     * @param  array<string, mixed>  $payload
     */
    protected function validateRecordCounts(array $package, array $payload): void
    {
        /** @var array<string, mixed> $counts */
        $counts = $package['record_counts'];

        $componentCount = 0;
        foreach ($payload['pricing_rules'] as $rule) {
            if (is_array($rule) && isset($rule['components']) && is_array($rule['components'])) {
                $componentCount += count($rule['components']);
            }
        }

        $expected = [
            'vehicles' => count($payload['vehicles']),
            'vehicle_media' => count($payload['vehicle_media']),
            'extras' => count($payload['extras']),
            'extra_compatibility' => count($payload['extra_compatibility']),
            'pricing_rules' => count($payload['pricing_rules']),
            'pricing_rule_components' => $componentCount,
            'exchange_rate_bases' => count($payload['exchange_rate_bases']),
            'rent_policies' => $payload['rent_policy'] === [] ? 0 : 1,
        ];

        foreach ($expected as $key => $actualCount) {
            if (! array_key_exists($key, $counts) || (int) $counts[$key] !== $actualCount) {
                $reported = $counts[$key] ?? 'missing';
                throw new ConfigurationValidationException(
                    "Record count mismatch for '{$key}': package declares {$reported}, actual payload has {$actualCount}."
                );
            }
        }
    }

    /**
     * @param  array<int|string, mixed>  $data
     */
    protected function scanForForbiddenKeys(array $data, string $path): void
    {
        foreach ($data as $key => $value) {
            if (is_string($key)) {
                $normalizedKey = strtolower($key);
                if (in_array($normalizedKey, self::FORBIDDEN_KEYS, true)) {
                    throw new ConfigurationSecurityException(
                        "Excluded secret or PII field '{$key}' detected at '{$path}.{$key}'."
                    );
                }
            }

            if (is_array($value)) {
                $this->scanForForbiddenKeys($value, "{$path}.{$key}");
            }
        }
    }

    /**
     * @param  array<int|string, mixed>  $data
     */
    protected function scanPayloadForSensitiveValues(array $data, string $path): void
    {
        $patterns = [
            'Google API key' => '/AIza[0-9A-Za-z\-_]{20,}/',
            'Bearer credential' => '/\bBearer\s+[A-Za-z0-9\-_.=]{8,}/i',
            'Base64 application key' => '/base64:[A-Za-z0-9+\/=]{16,}/',
            'Embedded URI credential' => '/https?:\/\/[^\/\s:@]+:[^\/\s:@]+@/i',
            'Email address PII' => '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i',
            'Phone number PII' => '/\+\d{8,15}\b/',
        ];

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $this->scanPayloadForSensitiveValues($value, "{$path}.{$key}");

                continue;
            }

            if (! is_string($value) || $value === '') {
                continue;
            }

            foreach ($patterns as $label => $regex) {
                if (preg_match($regex, $value) === 1) {
                    throw new ConfigurationSecurityException(
                        "Excluded {$label} value detected at '{$path}.{$key}'."
                    );
                }
            }
        }
    }

    /**
     * @param  array<int|string, mixed>  $vehicles
     */
    protected function validateVehicles(array $vehicles): void
    {
        if (count($vehicles) !== self::REQUIRED_VEHICLE_COUNT) {
            throw new ConfigurationValidationException(
                'Configuration package must contain exactly '.self::REQUIRED_VEHICLE_COUNT.' non-retired vehicles; found '.count($vehicles).'.'
            );
        }

        $seenFleetIds = [];
        $seenPlates = [];

        foreach ($vehicles as $index => $vehicle) {
            if (! is_array($vehicle)) {
                throw new ConfigurationValidationException("Vehicle entry at index {$index} must be an associative array.");
            }

            $required = [
                'internal_fleet_id',
                'public_name',
                'make',
                'model',
                'year',
                'license_plate',
                'vehicle_type',
                'passenger_capacity',
                'luggage_capacity',
                'transmission',
                'has_ac',
                'eligible_for_pickup',
                'eligible_for_transfer',
                'eligible_for_ride',
                'eligible_for_rent',
                'turnaround_buffer_minutes',
                'status',
            ];

            foreach ($required as $field) {
                if (! array_key_exists($field, $vehicle) || $vehicle[$field] === null || $vehicle[$field] === '') {
                    throw new ConfigurationValidationException("Vehicle #{$index} missing required field '{$field}'.");
                }
            }

            $fleetIdKey = strtoupper(trim((string) $vehicle['internal_fleet_id']));
            $plateKey = strtoupper(trim((string) $vehicle['license_plate']));

            if (isset($seenFleetIds[$fleetIdKey])) {
                throw new ConfigurationValidationException("Duplicate internal_fleet_id '{$vehicle['internal_fleet_id']}' in configuration package.");
            }
            if (isset($seenPlates[$plateKey])) {
                throw new ConfigurationValidationException("Duplicate license_plate '{$vehicle['license_plate']}' in configuration package.");
            }

            $seenFleetIds[$fleetIdKey] = true;
            $seenPlates[$plateKey] = true;

            $status = VehicleStatus::tryFrom((string) $vehicle['status']);
            if ($status === null || $status === VehicleStatus::Retired) {
                throw new ConfigurationValidationException("Vehicle '{$fleetIdKey}' must have active or maintenance status.");
            }

            if (VehicleType::tryFrom((string) $vehicle['vehicle_type']) === null) {
                throw new ConfigurationValidationException("Vehicle '{$fleetIdKey}' has invalid vehicle_type '{$vehicle['vehicle_type']}'.");
            }

            if (TransmissionType::tryFrom((string) $vehicle['transmission']) === null) {
                throw new ConfigurationValidationException("Vehicle '{$fleetIdKey}' has invalid transmission '{$vehicle['transmission']}'.");
            }

            $year = (int) $vehicle['year'];
            $pax = (int) $vehicle['passenger_capacity'];
            $luggage = (int) $vehicle['luggage_capacity'];
            $buffer = (int) $vehicle['turnaround_buffer_minutes'];

            if ($year < 2000 || $year > 2100) {
                throw new ConfigurationValidationException("Vehicle '{$fleetIdKey}' year {$year} is out of range (2000-2100).");
            }
            if ($pax < 1 || $pax > 50 || $luggage < 0 || $luggage > 50) {
                throw new ConfigurationValidationException("Vehicle '{$fleetIdKey}' capacity values are out of range.");
            }
            if ($buffer < 30 || $buffer > 720) {
                throw new ConfigurationValidationException("Vehicle '{$fleetIdKey}' turnaround_buffer_minutes must be 30-720.");
            }
        }
    }

    /**
     * @param  array<int|string, mixed>  $extras
     */
    protected function validateExtras(array $extras): void
    {
        $seenSlugs = [];
        foreach ($extras as $index => $extra) {
            if (! is_array($extra)) {
                throw new ConfigurationValidationException("Extra #{$index} must be an array.");
            }
            $slug = trim((string) ($extra['slug'] ?? ''));
            $name = trim((string) ($extra['name'] ?? ''));
            if ($slug === '' || $name === '') {
                throw new ConfigurationValidationException("Extra #{$index} must define non-empty 'slug' and 'name'.");
            }
            if (isset($seenSlugs[$slug])) {
                throw new ConfigurationValidationException("Duplicate extra slug '{$slug}' in package.");
            }
            $seenSlugs[$slug] = true;

            if (! array_key_exists('price_minor_units', $extra) || ! is_numeric($extra['price_minor_units']) || (int) $extra['price_minor_units'] < 0) {
                throw new ConfigurationValidationException("Extra '{$slug}' must define a non-negative numeric price_minor_units.");
            }
        }
    }

    /**
     * @param  array<int|string, mixed>  $compatItems
     * @param  array<int, string>  $knownExtraSlugs
     * @param  array<int, string>  $knownFleetIds
     */
    protected function validateExtraCompatibility(array $compatItems, array $knownExtraSlugs, array $knownFleetIds): void
    {
        foreach ($compatItems as $index => $item) {
            if (! is_array($item)) {
                throw new ConfigurationValidationException("ExtraCompatibility #{$index} must be an array.");
            }
            $slug = trim((string) ($item['extra_slug'] ?? ''));
            if ($slug === '' || ! in_array($slug, $knownExtraSlugs, true)) {
                throw new ConfigurationValidationException("ExtraCompatibility #{$index} references unknown extra_slug '{$slug}'.");
            }
            $fleetId = $item['vehicle_internal_fleet_id'] ?? null;
            if (is_string($fleetId) && $fleetId !== '' && ! in_array($fleetId, $knownFleetIds, true)) {
                throw new ConfigurationValidationException("ExtraCompatibility #{$index} references unknown vehicle_internal_fleet_id '{$fleetId}'.");
            }
        }
    }

    /**
     * @param  array<int|string, mixed>  $mediaItems
     * @param  array<int, string>  $knownFleetIds
     */
    protected function validateVehicleMedia(array $mediaItems, PackageClassification $classification, array $knownFleetIds): void
    {
        foreach ($mediaItems as $index => $item) {
            if (! is_array($item)) {
                throw new ConfigurationValidationException("VehicleMedia entry at index {$index} must be an array.");
            }

            foreach (['vehicle_internal_fleet_id', 'media_type', 'file_path', 'disk', 'alt_text'] as $field) {
                if (empty($item[$field])) {
                    throw new ConfigurationValidationException("VehicleMedia #{$index} missing required field '{$field}'.");
                }
            }

            $fleetId = (string) $item['vehicle_internal_fleet_id'];
            if (! in_array($fleetId, $knownFleetIds, true)) {
                throw new ConfigurationValidationException("VehicleMedia #{$index} references unknown vehicle_internal_fleet_id '{$fleetId}'.");
            }

            if (MediaType::tryFrom((string) $item['media_type']) === null) {
                throw new ConfigurationValidationException("VehicleMedia #{$index} has invalid media_type '{$item['media_type']}'.");
            }

            $isApproved = (bool) ($item['is_approved'] ?? false);
            if ($classification === PackageClassification::ProductionApproved && $isApproved) {
                if (trim((string) ($item['photographer'] ?? '')) === '' || trim((string) ($item['usage_rights'] ?? '')) === '') {
                    throw new ConfigurationValidationException("Approved production media #{$index} requires photographer and usage_rights attribution.");
                }

                $disk = (string) $item['disk'];
                $path = (string) $item['file_path'];
                if (! Storage::disk($disk)->exists($path)) {
                    throw new ConfigurationValidationException("Approved production media file '{$disk}:{$path}' does not exist on storage disk.");
                }
            }
        }
    }

    /**
     * @param  array<int|string, mixed>  $rules
     * @param  array<int, string>  $knownFleetIds
     */
    protected function validatePricingRules(array $rules, array $knownFleetIds): void
    {
        $seenKeys = [];
        foreach ($rules as $index => $rule) {
            if (! is_array($rule)) {
                throw new ConfigurationValidationException("PricingRule #{$index} must be an array.");
            }

            if (ServiceType::tryFrom((string) ($rule['service_type'] ?? '')) === null) {
                throw new ConfigurationValidationException("PricingRule #{$index} has invalid service_type.");
            }

            $fleetId = $rule['vehicle_internal_fleet_id'] ?? null;
            if (is_string($fleetId) && $fleetId !== '' && ! in_array($fleetId, $knownFleetIds, true)) {
                throw new ConfigurationValidationException("PricingRule #{$index} references unknown vehicle_internal_fleet_id '{$fleetId}'.");
            }

            $configKey = trim((string) ($rule['config_key'] ?? ''));
            if ($configKey === '') {
                throw new ConfigurationValidationException("PricingRule #{$index} missing deterministic config_key.");
            }

            if (isset($seenKeys[$configKey])) {
                throw new ConfigurationValidationException("Duplicate pricing rule config_key '{$configKey}'.");
            }
            $seenKeys[$configKey] = true;

            if (! isset($rule['components']) || ! is_array($rule['components']) || $rule['components'] === []) {
                throw new ConfigurationValidationException("PricingRule '{$configKey}' must contain at least one component.");
            }

            foreach ($rule['components'] as $cIndex => $comp) {
                if (! is_array($comp)) {
                    throw new ConfigurationValidationException("PricingRule '{$configKey}' component #{$cIndex} must be an array.");
                }
                if (! empty($comp['is_deposit']) && ! empty($comp['is_taxable'])) {
                    throw new ConfigurationValidationException("PricingRule '{$configKey}' component #{$cIndex} cannot be both deposit and taxable.");
                }
            }
        }
    }

    /**
     * @param  array<int|string, mixed>  $rates
     */
    protected function validateExchangeRateBases(array $rates): void
    {
        foreach ($rates as $index => $rate) {
            if (! is_array($rate)) {
                throw new ConfigurationValidationException("ExchangeRateBasis #{$index} must be an array.");
            }
            if (! array_key_exists('rate', $rate) || ! is_numeric($rate['rate']) || (float) $rate['rate'] <= 0) {
                throw new ConfigurationValidationException("ExchangeRateBasis #{$index} must define a positive numeric 'rate'.");
            }
            if (! array_key_exists('source_note', $rate) || ! array_key_exists('effective_at', $rate)) {
                throw new ConfigurationValidationException("ExchangeRateBasis #{$index} must define 'source_note' and 'effective_at'.");
            }
        }
    }

    /**
     * @param  array<string, mixed>  $policy
     */
    protected function validateRentPolicy(array $policy): void
    {
        if ($policy === []) {
            return;
        }

        foreach ([
            'min_driver_age',
            'max_driver_age',
            'min_licence_years',
            'security_deposit_minor_units',
            'insurance_excess_minor_units',
            'daily_mileage_km',
            'excess_mileage_rate_minor_units',
            'grace_period_minutes',
            'extra_hours_threshold',
            'full_day_threshold_hours',
            'hotel_delivery_fee_minor_units',
            'smoking_penalty_minor_units',
        ] as $field) {
            if (! array_key_exists($field, $policy) || ! is_numeric($policy[$field])) {
                throw new ConfigurationValidationException("RentPolicy missing required numeric field '{$field}'.");
            }
        }
    }

    /**
     * @param  array<string, mixed>  $package
     * @param  array<string, mixed>  $payload
     */
    protected function validateClassificationGuardrails(
        PackageClassification $classification,
        array $package,
        array $payload
    ): void {
        if ($classification === PackageClassification::StagingSynthetic) {
            if (app()->isProduction()) {
                throw new ConfigurationSecurityException('Staging synthetic configuration packages cannot be exported or imported in production.');
            }

            return;
        }

        // ProductionApproved guardrails
        $manifest = $package['approval_manifest'] ?? [];
        foreach (['owner', 'approved_by', 'approved_at', 'effective_date'] as $requiredMeta) {
            if (empty($manifest[$requiredMeta])) {
                throw new ConfigurationValidationException("Production approved package missing approval_manifest.{$requiredMeta}.");
            }
        }

        $encodedPayload = json_encode($payload, JSON_THROW_ON_ERROR);
        if (preg_match('/\b(SYNTH-|SYNTHETIC|TEST-FLEET|TEST-VEH)/i', $encodedPayload) === 1) {
            throw new ConfigurationSecurityException('Production approved configuration package cannot contain synthetic or test fixture markers.');
        }
    }
}
