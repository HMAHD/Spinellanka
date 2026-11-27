<?php

declare(strict_types=1);

namespace App\Domain\Operations\Exceptions;

use RuntimeException;

class ReleaseGateBlockedException extends RuntimeException
{
    /**
     * @param  array<string, string>  $violations
     */
    public function __construct(
        public readonly array $violations,
        string $message = 'Vehicle release is blocked by unfulfilled statutory or operational requirements.'
    ) {
        parent::__construct(sprintf('%s Violations: %s', $message, implode('; ', $violations)));
    }
}
