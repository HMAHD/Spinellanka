<?php

declare(strict_types=1);

namespace App\Domain\Payment\Exceptions;

use DomainException;

class RefundExceedsSettledAmountException extends DomainException
{
    public function __construct(string $message = 'Requested refund exceeds remaining refundable settled balance.')
    {
        parent::__construct($message);
    }
}
