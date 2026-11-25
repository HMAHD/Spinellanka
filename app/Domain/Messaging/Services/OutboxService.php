<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Services;

use App\Domain\Messaging\Enums\OutboxEventStatus;
use App\Jobs\ProcessOutboxEventJob;
use App\Models\OutboxEvent;
use Illuminate\Database\Eloquent\Model;

class OutboxService
{
    /**
     * Record an outbox event within the active database transaction.
     * Dispatches ProcessOutboxEventJob to the queue with afterCommit.
     *
     * @param  array<string, mixed>  $payload
     */
    public function record(
        string $eventType,
        Model $aggregate,
        array $payload,
        ?string $idempotencyKey = null,
        int $payloadVersion = 1,
    ): OutboxEvent {
        $key = $idempotencyKey ?? sprintf(
            'evt_%s_%s_%s_%s',
            strtolower(class_basename($aggregate)),
            $aggregate->getKey(),
            str_replace('.', '_', $eventType),
            $payloadVersion
        );

        $event = OutboxEvent::create([
            'event_type' => $eventType,
            'aggregate_type' => $aggregate->getMorphClass(),
            'aggregate_id' => $aggregate->getKey(),
            'idempotency_key' => $key,
            'payload_version' => $payloadVersion,
            'payload' => $payload,
            'status' => OutboxEventStatus::Pending,
            'attempts' => 0,
        ]);

        ProcessOutboxEventJob::dispatch($event->id)->afterCommit();

        return $event;
    }
}

