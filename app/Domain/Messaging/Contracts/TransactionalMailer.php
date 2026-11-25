<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Contracts;

use App\Domain\Messaging\DTOs\DeliveryResult;
use App\Domain\Messaging\DTOs\EmailMessage;

interface TransactionalMailer
{
    public function send(EmailMessage $message): DeliveryResult;
}
