<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Enums;

enum MessageDeliveryStatus: string
{
    case Pending = 'pending';
    case Sending = 'sending';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Failed = 'failed';
    case DeliveryUnknown = 'delivery_unknown';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Sending => 'Sending',
            self::Sent => 'Sent',
            self::Delivered => 'Delivered',
            self::Failed => 'Failed',
            self::DeliveryUnknown => 'Delivery Unknown',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Sending => 'info',
            self::Sent => 'success',
            self::Delivered => 'success',
            self::Failed => 'danger',
            self::DeliveryUnknown => 'warning',
        };
    }
}
