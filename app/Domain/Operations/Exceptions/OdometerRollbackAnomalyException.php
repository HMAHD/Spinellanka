<?php

declare(strict_types=1);

namespace App\Domain\Operations\Exceptions;

use RuntimeException;

class OdometerRollbackAnomalyException extends RuntimeException
{
    public function __construct(
        public readonly int $collectionKm,
        public readonly int $returnKm,
        string $message = 'Negative odometer delta detected. Return kilometers cannot be less than collection kilometers.'
    ) {
        parent::__construct(sprintf('%s (Collection: %d km, Return: %d km)', $message, $collectionKm, $returnKm));
    }
}
