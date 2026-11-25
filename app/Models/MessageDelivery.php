<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Messaging\Enums\MessageChannel;
use App\Domain\Messaging\Enums\MessageDeliveryStatus;
use App\Domain\Messaging\Enums\RecipientType;
use Database\Factories\MessageDeliveryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageDelivery extends Model
{
    /** @use HasFactory<MessageDeliveryFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'outbox_event_id',
        'booking_id',
        'channel',
        'recipient_type',
        'recipient_identifier',
        'template_name',
        'template_version',
        'template_params',
        'idempotency_key',
        'provider',
        'provider_message_id',
        'status',
        'attempts',
        'max_attempts',
        'last_attempted_at',
        'sent_at',
        'delivered_at',
        'failed_at',
        'last_error_code',
        'last_error_message',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channel' => MessageChannel::class,
            'recipient_type' => RecipientType::class,
            'template_params' => 'array',
            'status' => MessageDeliveryStatus::class,
            'attempts' => 'integer',
            'max_attempts' => 'integer',
            'last_attempted_at' => 'datetime',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<OutboxEvent, $this>
     */
    public function outboxEvent(): BelongsTo
    {
        return $this->belongsTo(OutboxEvent::class, 'outbox_event_id');
    }

    /**
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'booking_id');
    }

    /**
     * Mask recipient phone or email for privacy-safe display.
     */
    public function maskedRecipient(): string
    {
        $identifier = $this->recipient_identifier;

        if ($this->channel === MessageChannel::Email || str_contains($identifier, '@')) {
            $parts = explode('@', $identifier, 2);
            $local = $parts[0];
            $domain = $parts[1] ?? '';
            $maskedLocal = strlen($local) > 1 ? substr($local, 0, 1).'•••••' : $local;

            return $maskedLocal.'@'.$domain;
        }

        // WhatsApp / Phone
        $digits = preg_replace('/[^\d+]/', '', $identifier) ?? $identifier;
        if (strlen($digits) > 6) {
            return substr($digits, 0, 4).' ••••• '.substr($digits, -3);
        }

        return $identifier;
    }
}
