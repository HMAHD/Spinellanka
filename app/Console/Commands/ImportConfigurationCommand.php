<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Configuration\Services\ConfigurationTransferService;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Throwable;

class ImportConfigurationCommand extends Command
{
    protected $signature = 'config:import-package
        {path : Input JSON configuration package path}
        {--dry-run : Validate and report drift without modifying database (default)}
        {--apply : Apply validated configuration changes atomically}
        {--notes= : Optional import audit notes}
        {--actor= : Optional staff user email for audit attribution}
        {--force : Skip interactive confirmation when applying}';

    protected $description = 'Validate, dry-run, or idempotently import a versioned configuration package';

    public function handle(ConfigurationTransferService $transferService): int
    {
        $path = (string) $this->argument('path');
        if (! File::isFile($path)) {
            $this->error("Configuration package file '{$path}' does not exist.");

            return self::FAILURE;
        }

        if ($this->option('dry-run') && $this->option('apply')) {
            $this->error('Specify either --dry-run or --apply, not both.');

            return self::FAILURE;
        }

        $dryRun = ! $this->option('apply');

        $actor = null;
        if ($actorEmail = $this->option('actor')) {
            $actor = User::where('email', (string) $actorEmail)->first();
            if ($actor === null) {
                $this->error("Actor user '{$actorEmail}' not found.");

                return self::FAILURE;
            }
        }

        try {
            $raw = File::get($path);
            $package = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($package)) {
                $this->error('Configuration package must decode to a JSON object.');

                return self::FAILURE;
            }

            if (! $dryRun && ! $this->option('force')) {
                if (! $this->confirm("Apply configuration package '{$path}' to database?", true)) {
                    $this->warn('Import cancelled.');

                    return self::FAILURE;
                }
            }

            $result = $transferService->import(
                package: $package,
                actor: $actor,
                dryRun: $dryRun,
                notes: $this->option('notes') ? (string) $this->option('notes') : null,
            );

            $summary = $result->driftReport->summaryCounts();
            $this->table(
                ['Metric', 'Count'],
                [
                    ['Mode', $result->dryRun ? 'dry-run' : ($result->wasNoOp ? 'apply (no-op)' : 'applied')],
                    ['Added', (string) $summary['added']],
                    ['Updated', (string) $summary['updated']],
                    ['Unchanged', (string) $summary['unchanged']],
                    ['Missing from package', (string) $summary['missing_from_package']],
                ]
            );

            if ($result->snapshot !== null) {
                $this->info("Active snapshot v{$result->snapshot->version_number} ({$result->snapshot->checksum_sha256})");
            }

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error("Configuration import rejected: {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}
