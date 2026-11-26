<?php

declare(strict_types=1);

namespace App\Domain\Payment\Exceptions;

use DomainException;

class InvalidWebhookSignatureException extends DomainException
{
    public function __construct(string $message = 'Invalid webhook cryptographic signature.')
    {
        parent::__construct($message);
    }
}
