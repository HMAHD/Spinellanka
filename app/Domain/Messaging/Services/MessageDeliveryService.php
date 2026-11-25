<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Services;

use App\Domain\Audit\Services\AuditService;
use App\Domain\Messaging\Enums\MessageChannel;
use App\Domain\Messaging\Enums\MessageDeliveryStatus;
use App\Jobs\SendMessageDeliveryJob;
use App\Models\MessageDelivery;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class MessageDeliveryService
{
    public function __construct(
        protected AuditService $auditService,
    ) {}

    /**
     * Manually retry a failed or ambiguous message delivery with audit trail.
     */
    public function manualRetry(MessageDelivery $delivery, User $actor): MessageDelivery
    {
        $statusValue = $delivery->status instanceof MessageDeliveryStatus ? $delivery->status->value : (string) $delivery->status;
        if (! in_array($delivery->status, [MessageDeliveryStatus::Failed, MessageDeliveryStatus::DeliveryUnknown], true)) {
            throw new InvalidArgumentException("Delivery {$delivery->id} in status {$statusValue} cannot be manually retried.");
        }

        return DB::transaction(function () use ($delivery, $actor) {
            $delivery->update([
                'status' => MessageDeliveryStatus::Pending,
                'attempts' => 0,
                'max_attempts' => 5,
                'last_error_code' => null,
                'last_error_message' => null,
            ]);

            SendMessageDeliveryJob::dispatch($delivery->id);

            $channelName = $delivery->channel instanceof MessageChannel ? $delivery->channel->value : (string) $delivery->channel;

            $this->auditService->record(
                action: 'message_delivery.manually_retried',
                actor: $actor,
                summary: "Manually re-queued {$channelName} delivery for booking {$delivery->booking?->reference} to {$delivery->maskedRecipient()}",
                metadata: [
                    'message_delivery_id' => $delivery->id,
                    'booking_id' => $delivery->booking_id,
                    'channel' => $channelName,
                    'recipient' => $delivery->maskedRecipient(),
                    'template' => $delivery->template_name,
                ],
                auditable: $delivery->booking
            );

            return $delivery;
        });
    }
}

