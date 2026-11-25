<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Messaging\Enums\OutboxEventStatus;
use Database\Factories\OutboxEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class OutboxEvent extends Model
{
    /** @use HasFactory<OutboxEventFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'event_type',
        'aggregate_type',
        'aggregate_id',
        'idempotency_key',
        'payload_version',
        'payload',
        'status',
        'attempts',
        'last_error',
        'processed_at',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'status' => OutboxEventStatus::class,
            'attempts' => 'integer',
            'payload_version' => 'integer',
            'processed_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<MessageDelivery, $this>
     */
    public function messageDeliveries(): HasMany
    {
        return $this->hasMany(MessageDelivery::class, 'outbox_event_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function aggregate(): MorphTo
    {
        return $this->morphTo();
    }
}
