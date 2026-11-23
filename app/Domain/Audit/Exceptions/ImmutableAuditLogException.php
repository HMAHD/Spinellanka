<?php

namespace App\Domain\Audit\Exceptions;

use RuntimeException;

class ImmutableAuditLogException extends RuntimeException
{
    public function __construct(string $message = 'Audit events are immutable and cannot be updated or deleted.')
    {
        parent::__construct($message);
    }
}
