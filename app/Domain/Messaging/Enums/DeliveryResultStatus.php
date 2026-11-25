<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Enums;

enum DeliveryResultStatus: string
{
    case Accepted = 'accepted';
    case Failed = 'failed';
    case Unknown = 'unknown';
}

