<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Configuration\Enums\PackageClassification;
use App\Domain\Configuration\Services\ConfigurationCanonicalizer;
use App\Domain\Configuration\Services\ConfigurationTransferService;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Throwable;

class ExportConfigurationCommand extends Command
{
    protected $signature = 'config:export-package
        {path : Output JSON file path}
        {--classification=staging_synthetic : Package classification (production_approved|staging_synthetic)}
        {--notes= : Optional export notes}
        {--actor= : Optional staff user email for audit attribution}
        {--owner= : Approval owner (required for production_approved)}
        {--approved-by= : Approver name/role (required for production_approved)}
        {--approved-at= : Approval date YYYY-MM-DD (required for production_approved)}
        {--effective-date= : Effective date YYYY-MM-DD (required for production_approved)}
        {--force : Overwrite existing output file}';

    protected $description = 'Export versioned, checksummed, non-secret, non-PII configuration package';

    public function handle(
        ConfigurationTransferService $transferService,
        ConfigurationCanonicalizer $canonicalizer,
    ): int {
        $path = (string) $this->argument('path');
        $classification = PackageClassification::tryFrom((string) $this->option('classification'));
        if ($classification === null) {
            $this->error('Invalid --classification. Expected production_approved or staging_synthetic.');

            return self::FAILURE;
        }

        if (File::exists($path) && ! $this->option('force')) {
            $this->error("Output file '{$path}' already exists. Pass --force to overwrite.");

            return self::FAILURE;
        }

        $actor = null;
        if ($actorEmail = $this->option('actor')) {
            $actor = User::where('email', (string) $actorEmail)->first();
            if ($actor === null) {
                $this->error("Actor user '{$actorEmail}' not found.");

                return self::FAILURE;
            }
        }

        $manifest = array_filter([
            'owner' => $this->option('owner'),
            'approved_by' => $this->option('approved-by'),
            'approved_at' => $this->option('approved-at'),
            'effective_date' => $this->option('effective-date'),
            'is_synthetic_staging' => $classification === PackageClassification::StagingSynthetic ? true : null,
        ], fn (mixed $v): bool => $v !== null && $v !== '');

        try {
            $package = $transferService->export(
                classification: $classification,
                actor: $actor,
                approvalManifest: $manifest,
                notes: $this->option('notes') ? (string) $this->option('notes') : null,
            );

            $dir = dirname($path);
            if (! File::isDirectory($dir)) {
                File::makeDirectory($dir, 0755, true);
            }

            File::put($path, $canonicalizer->encode($package));

            $this->info("Exported {$classification->value} package to {$path}");
            $this->line("Checksum (SHA-256): {$package['checksum_sha256']}");

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error("Configuration export failed: {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}
