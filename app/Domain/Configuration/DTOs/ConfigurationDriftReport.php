<?php

declare(strict_types=1);

namespace App\Domain\Configuration\DTOs;

final readonly class ConfigurationDriftReport
{
    /**
     * @param  array<string, array<int, string>>  $added
     * @param  array<string, array<int, string>>  $updated
     * @param  array<string, array<int, string>>  $unchanged
     * @param  array<string, array<int, string>>  $missingFromPackage
     * @param  array<int, string>  $warnings
     */
    public function __construct(
        public bool $schemaValid,
        public bool $checksumValid,
        public bool $isValid,
        public array $added,
        public array $updated,
        public array $unchanged,
        public array $missingFromPackage,
        public array $warnings = [],
    ) {}

    public function hasChanges(): bool
    {
        foreach ([$this->added, $this->updated, $this->missingFromPackage] as $bucket) {
            foreach ($bucket as $items) {
                if ($items !== []) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return array<string, int>
     */
    public function summaryCounts(): array
    {
        $sum = static fn (array $bucket): int => array_sum(array_map('count', $bucket));

        return [
            'added' => $sum($this->added),
            'updated' => $sum($this->updated),
            'unchanged' => $sum($this->unchanged),
            'missing_from_package' => $sum($this->missingFromPackage),
            'warnings' => count($this->warnings),
        ];
    }
}
