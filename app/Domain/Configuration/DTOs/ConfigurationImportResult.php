<?php

declare(strict_types=1);

namespace App\Domain\Configuration\DTOs;

use App\Models\ConfigurationSnapshot;

final readonly class ConfigurationImportResult
{
    /**
     * @param  array<string, int>  $appliedCounts
     */
    public function __construct(
        public ?ConfigurationSnapshot $snapshot,
        public ConfigurationDriftReport $driftReport,
        public bool $dryRun,
        public bool $wasNoOp,
        public array $appliedCounts,
    ) {}
}
