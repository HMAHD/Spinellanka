<?php

declare(strict_types=1);

namespace App\Domain\Operations\Services;

use App\Domain\Configuration\Enums\PackageClassification;
use App\Models\ConfigurationSnapshot;
use App\Models\Vehicle;
use Illuminate\Support\Facades\Schema;

class ProviderReadinessService
{
    /**
     * Evaluate non-secret configuration readiness for all external integrations.
     *
     * @return array<int, array{capability: string, provider: string, state: string, missing_keys: array<int, string>, notes: string}>
     */
    public function evaluateAll(): array
    {
        $mapsProvider = (string) config('services.maps.provider', '');
        $mapsKeySet = filled(config('services.maps.key'));

        $whatsappProvider = (string) config('services.whatsapp.provider', 'fake');
        $metaPhoneSet = filled(config('services.meta_whatsapp.phone_number_id'));
        $metaTokenSet = filled(config('services.meta_whatsapp.system_user_token'));

        $mailerProvider = (string) config('services.transactional_mailer.provider', 'fake');
        $postmarkTokenSet = filled(config('services.postmark.server_token'));

        $paymentGateway = (string) config('services.payment.gateway', 'fake');
        $payhereIdSet = filled(config('services.payhere.merchant_id'));
        $payhereSecretSet = filled(config('services.payhere.merchant_secret'));

        $sentrySet = filled(config('services.sentry.dsn'));
        $backupDisk = (string) config('services.backup.off_host_disk', '');
        $backupConfigured = $backupDisk !== '' && filled(config("filesystems.disks.{$backupDisk}.bucket"));

        $analyticsProvider = (string) config('services.analytics.provider', 'none');
        $plausibleDomainSet = filled(config('services.analytics.plausible_domain'));

        return [
            [
                'capability' => 'Maps & Routes',
                'provider' => $mapsProvider === 'google_maps' ? 'google_maps' : 'null_fallback',
                'state' => ($mapsProvider === 'google_maps' && $mapsKeySet) ? 'Sandbox/Live Ready' : 'Disabled Fallback (Null*)',
                'missing_keys' => array_values(array_filter([
                    $mapsProvider === 'google_maps' ? null : 'MAPS_PROVIDER',
                    $mapsKeySet ? null : 'MAPS_API_KEY',
                ])),
                'notes' => 'Safe fallback to NullPlaceSearchService; route errors degrade to custom quote.',
            ],
            [
                'capability' => 'WhatsApp Business',
                'provider' => $whatsappProvider,
                'state' => ($whatsappProvider === 'meta_whatsapp' && $metaPhoneSet && $metaTokenSet) ? 'Sandbox/Live Ready' : 'Disabled Fallback (Fake)',
                'missing_keys' => array_values(array_filter([
                    $whatsappProvider === 'meta_whatsapp' ? null : 'WHATSAPP_PROVIDER',
                    $metaPhoneSet ? null : 'META_WHATSAPP_PHONE_NUMBER_ID',
                    $metaTokenSet ? null : 'META_WHATSAPP_SYSTEM_USER_TOKEN',
                ])),
                'notes' => 'Canonical v1 templates; never auto-retries delivery_unknown.',
            ],
            [
                'capability' => 'Transactional Email',
                'provider' => $mailerProvider,
                'state' => ($mailerProvider === 'postmark' && $postmarkTokenSet) ? 'Sandbox/Live Ready' : 'Blocked / Disabled (Fake)',
                'missing_keys' => array_values(array_filter([
                    $mailerProvider === 'postmark' ? null : 'TRANSACTIONAL_MAILER_PROVIDER',
                    $postmarkTokenSet ? null : 'POSTMARK_SERVER_TOKEN',
                ])),
                'notes' => 'Awaiting verified client sending domain & Postmark token.',
            ],
            [
                'capability' => 'Hosted Payments',
                'provider' => $paymentGateway,
                'state' => ($paymentGateway === 'payhere' && $payhereIdSet && $payhereSecretSet) ? 'Sandbox Ready' : 'Blocked / Disabled (Fake)',
                'missing_keys' => array_values(array_filter([
                    $paymentGateway === 'payhere' ? null : 'PAYMENT_GATEWAY',
                    $payhereIdSet ? null : 'PAYHERE_MERCHANT_ID',
                    $payhereSecretSet ? null : 'PAYHERE_MERCHANT_SECRET',
                ])),
                'notes' => 'Webhook verification mandatory; browser return never settles.',
            ],
            [
                'capability' => 'Error Reporting',
                'provider' => $sentrySet ? 'sentry' : 'scrubber_only',
                'state' => $sentrySet ? 'DSN Configured (Scrubber Active)' : 'Blocked / Local Scrubbing Only',
                'missing_keys' => $sentrySet ? [] : ['SENTRY_LARAVEL_DSN'],
                'notes' => 'PII, private tokens, API keys, and coordinates scrubbed in bootstrap/app.php exception context.',
            ],
            [
                'capability' => 'Off-Host Backups',
                'provider' => $backupConfigured ? $backupDisk : 'local_manifest_only',
                'state' => $backupConfigured ? 'Configured' : 'Blocked (Phase 01 Gate 10)',
                'missing_keys' => $backupConfigured ? [] : ['BACKUP_OFF_HOST_DISK', 'AWS_BUCKET'],
                'notes' => 'Local backup:verify checks dump capability & SHA-256 manifest; off-host S3 unprovisioned.',
            ],
            [
                'capability' => 'Public Analytics',
                'provider' => $analyticsProvider,
                'state' => ($analyticsProvider === 'plausible' && $plausibleDomainSet) ? 'Opt-In Public Ready' : 'Disabled',
                'missing_keys' => array_values(array_filter([
                    $analyticsProvider === 'plausible' ? null : 'ANALYTICS_PROVIDER',
                    $plausibleDomainSet ? null : 'PLAUSIBLE_DOMAIN',
                ])),
                'notes' => 'Allowlisted public routes only; gated behind explicit visitor consent.',
            ],
        ];
    }

    /**
     * Detect whether synthetic staging fixtures are active in the database.
     */
    public function hasSyntheticStagingState(): bool
    {
        $activeSyntheticSnapshot = Schema::hasTable('configuration_snapshots')
            && ConfigurationSnapshot::query()
                ->where('is_active', true)
                ->where('package_classification', PackageClassification::StagingSynthetic->value)
                ->exists();

        if ($activeSyntheticSnapshot) {
            return true;
        }

        return Schema::hasTable('vehicles')
            && Vehicle::query()
                ->where('internal_fleet_id', 'like', 'SYNTH-%')
                ->exists();
    }
}
