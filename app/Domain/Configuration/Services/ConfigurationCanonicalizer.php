<?php

declare(strict_types=1);

namespace App\Domain\Configuration\Services;

use Carbon\CarbonInterface;
use DateTimeInterface;

class ConfigurationCanonicalizer
{
    /**
     * Recursively canonicalize associative arrays by key order while preserving list order.
     */
    public function canonicalize(mixed $value): mixed
    {
        if ($value instanceof CarbonInterface || $value instanceof DateTimeInterface) {
            return $value->format('Y-m-d\TH:i:sP');
        }

        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(fn (mixed $item): mixed => $this->canonicalize($item), $value);
        }

        $copy = $value;
        ksort($copy, SORT_STRING);

        foreach ($copy as $k => $v) {
            $copy[$k] = $this->canonicalize($v);
        }

        return $copy;
    }

    /**
     * Deterministically encode data into canonical JSON.
     *
     * @param  array<string, mixed>  $data
     */
    public function encode(array $data): string
    {
        return json_encode(
            $this->canonicalize($data),
            JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
    }

    /**
     * Extract the deterministic signed subset of a configuration package.
     *
     * @param  array<string, mixed>  $package
     * @return array<string, mixed>
     */
    public function signedEnvelopeSubset(array $package): array
    {
        return [
            'schema_version' => $package['schema_version'] ?? null,
            'package_classification' => $package['package_classification'] ?? null,
            'record_counts' => $package['record_counts'] ?? [],
            'approval_manifest' => $package['approval_manifest'] ?? [],
            'payload' => $package['payload'] ?? [],
        ];
    }

    /**
     * Compute deterministic SHA-256 checksum over canonical signed subset.
     *
     * @param  array<string, mixed>  $package
     */
    public function computeChecksum(array $package): string
    {
        return hash('sha256', $this->encode($this->signedEnvelopeSubset($package)));
    }
}
