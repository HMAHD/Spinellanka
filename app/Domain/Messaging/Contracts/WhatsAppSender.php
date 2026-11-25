<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Contracts;

use App\Domain\Messaging\DTOs\DeliveryResult;
use App\Domain\Messaging\DTOs\WhatsAppMessage;

interface WhatsAppSender
{
    public function send(WhatsAppMessage $message): DeliveryResult;
}
