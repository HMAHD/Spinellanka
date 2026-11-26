<?php

declare(strict_types=1);

namespace App\Domain\Payment\Exceptions;

use DomainException;

class PaymentAmountMismatchException extends DomainException
{
    public function __construct(string $message = 'Payment settlement amount does not match proposal required amount.')
    {
        parent::__construct($message);
    }
}
