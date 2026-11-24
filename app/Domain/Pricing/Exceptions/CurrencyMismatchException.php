<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Exceptions;

use RuntimeException;

class CurrencyMismatchException extends RuntimeException
{
    public function __construct(string $expectedCurrency, string $actualCurrency)
    {
        parent::__construct("Cannot perform monetary arithmetic between {$expectedCurrency} and {$actualCurrency}.");
    }
}
