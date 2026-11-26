<?php

declare(strict_types=1);

namespace App\Domain\Payment\Exceptions;

use RuntimeException;

class PaymentGatewayMisconfiguredException extends RuntimeException
{
    public function __construct(string $message = 'Payment gateway merchant credentials or secrets are unconfigured.')
    {
        parent::__construct($message);
    }
}
