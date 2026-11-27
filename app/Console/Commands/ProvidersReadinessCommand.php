<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Operations\Services\ProviderReadinessService;
use Illuminate\Console\Command;

class ProvidersReadinessCommand extends Command
{
    protected $signature = 'providers:readiness';

    protected $description = 'Inspect non-secret sandbox/production provider readiness and synthetic staging guardrails';

    public function handle(ProviderReadinessService $readinessService): int
    {
        $rows = array_map(
            fn (array $item): array => [
                $item['capability'],
                $item['provider'],
                $item['state'],
                $item['missing_keys'] === [] ? 'None' : implode(', ', $item['missing_keys']),
            ],
            $readinessService->evaluateAll()
        );

        $this->table(['Capability', 'Adapter', 'State', 'Missing Env Keys'], $rows);

        if ($readinessService->hasSyntheticStagingState()) {
            $this->warn('Synthetic staging fixtures (CHG-003) detected in database; production export remains blocked.');
        }

        return self::SUCCESS;
    }
}
