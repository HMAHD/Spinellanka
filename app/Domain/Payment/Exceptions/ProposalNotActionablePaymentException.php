<?php

declare(strict_types=1);

namespace App\Domain\Payment\Exceptions;

use DomainException;

class ProposalNotActionablePaymentException extends DomainException
{
    public function __construct(string $message = 'Proposal is expired, cancelled, or superseded and cannot be automatically confirmed.')
    {
        parent::__construct($message);
    }
}
