<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Configuration\Services\ConfigurationTransferService;
use App\Models\ConfigurationSnapshot;
use App\Models\User;
use Illuminate\Console\Command;
use Throwable;

class RollbackConfigurationCommand extends Command
{
    protected $signature = 'config:rollback-package
        {--snapshot= : Target snapshot version_number to restore (defaults to latest prior snapshot)}
        {--reason=Manual CLI rollback : Reason recorded in audit trail}
        {--actor= : Optional staff user email for audit attribution}
        {--force : Skip interactive confirmation}';

    protected $description = 'Roll back active configuration to a prior audited configuration snapshot';

    public function handle(ConfigurationTransferService $transferService): int
    {
        $actor = null;
        if ($actorEmail = $this->option('actor')) {
            $actor = User::where('email', (string) $actorEmail)->first();
            if ($actor === null) {
                $this->error("Actor user '{$actorEmail}' not found.");

                return self::FAILURE;
            }
        }

        $targetId = null;
        if ($versionOpt = $this->option('snapshot')) {
            $target = ConfigurationSnapshot::where('version_number', (int) $versionOpt)->first();
            if ($target === null) {
                $this->error("Configuration snapshot version v{$versionOpt} not found.");

                return self::FAILURE;
            }
            $targetId = $target->id;
        }

        if (! $this->option('force')) {
            if (! $this->confirm('Roll back configuration to prior snapshot?', false)) {
                $this->warn('Rollback cancelled.');

                return self::FAILURE;
            }
        }

        try {
            $result = $transferService->rollback(
                targetSnapshotId: $targetId,
                actor: $actor,
                reason: (string) $this->option('reason'),
            );

            $snapshot = $result->snapshot;
            $versionNumber = $snapshot !== null ? $snapshot->version_number : 0;
            $checksum = $snapshot !== null ? $snapshot->checksum_sha256 : '';
            $this->info(sprintf(
                'Configuration rolled back successfully. Active snapshot is now v%d (%s).',
                $versionNumber,
                $checksum
            ));

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error("Configuration rollback failed: {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}
