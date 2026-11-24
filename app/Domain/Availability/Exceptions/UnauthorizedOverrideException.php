<?php

declare(strict_types=1);

namespace App\Domain\Availability\Exceptions;

use RuntimeException;
use Throwable;

class UnauthorizedOverrideException extends RuntimeException
{
    public function __construct(
        string $message = 'Unauthorized schedule override attempt: missing required permission or reason.',
        int $code = 403,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
