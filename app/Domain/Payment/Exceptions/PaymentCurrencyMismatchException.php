<?php

declare(strict_types=1);

namespace App\Domain\Payment\Exceptions;

use DomainException;

class PaymentCurrencyMismatchException extends DomainException
{
    public function __construct(string $message = 'Payment settlement currency does not match proposal currency.')
    {
        parent::__construct($message);
    }
}
