<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Messaging\Contracts\TransactionalMailer;
use App\Domain\Messaging\Contracts\WhatsAppSender;
use App\Domain\Messaging\DTOs\DeliveryResult;
use App\Domain\Messaging\DTOs\EmailMessage;
use App\Domain\Messaging\DTOs\WhatsAppMessage;
use App\Domain\Messaging\Enums\DeliveryResultStatus;
use App\Domain\Messaging\Enums\MessageChannel;
use App\Domain\Messaging\Enums\MessageDeliveryStatus;
use App\Models\MessageDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

class SendMessageDeliveryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $timeout = 25;

    /**
     * @var list<int>
     */
    public array $backoff = [30, 120, 600];

    public function __construct(
        public int $messageDeliveryId,
    ) {
        $this->onQueue('notifications');
    }

    public function handle(WhatsAppSender $whatsAppSender, TransactionalMailer $mailer): void
    {
        // Atomic claim under isolated micro-transaction
        $delivery = DB::transaction(function (): ?MessageDelivery {
            $record = MessageDelivery::where('id', $this->messageDeliveryId)
                ->whereIn('status', [
                    MessageDeliveryStatus::Pending,
                    MessageDeliveryStatus::Failed,
                    MessageDeliveryStatus::DeliveryUnknown,
                ])
                ->lockForUpdate()
                ->first();

            if (! $record) {
                return null;
            }

            $record->increment('attempts');
            $record->update([
                'status' => MessageDeliveryStatus::Sending,
                'last_attempted_at' => now(),
            ]);

            return $record;
        });

        if (! $delivery) {
            return;
        }

        $templateParams = is_array($delivery->template_params) ? $delivery->template_params : (json_decode((string) $delivery->template_params, true) ?? []);

        try {
            $result = match ($delivery->channel) {
                MessageChannel::WhatsApp => $whatsAppSender->send(new WhatsAppMessage(
                    recipientPhone: $delivery->recipient_identifier,
                    templateName: $delivery->template_name,
                    templateParams: $templateParams,
                    idempotencyKey: $delivery->idempotency_key,
                )),
                MessageChannel::Email => $mailer->send(new EmailMessage(
                    recipientEmail: $delivery->recipient_identifier,
                    subject: $this->resolveSubject($delivery),
                    templateName: $delivery->template_name,
                    templateParams: $templateParams,
                    idempotencyKey: $delivery->idempotency_key,
                )),
            };
        } catch (Throwable $e) {
            $result = DeliveryResult::unknown(
                errorCode: 'UNHANDLED_EXCEPTION',
                errorMessage: mb_substr($e->getMessage(), 0, 500)
            );
        }

        $this->applyResult($delivery, $result);
    }

    private function applyResult(MessageDelivery $delivery, DeliveryResult $result): void
    {
        if ($result->status === DeliveryResultStatus::Accepted) {
            $delivery->update([
                'status' => MessageDeliveryStatus::Sent,
                'sent_at' => now(),
                'provider_message_id' => $result->providerMessageId,
                'last_error_code' => null,
                'last_error_message' => null,
            ]);

            return;
        }

        if ($result->status === DeliveryResultStatus::Unknown) {
            $delivery->update([
                'status' => MessageDeliveryStatus::DeliveryUnknown,
                'last_error_code' => $result->errorCode ?? 'DELIVERY_UNKNOWN',
                'last_error_message' => $result->errorMessage ?? 'Delivery status unknown; manual reconciliation required.',
            ]);

            // Strictly DO NOT auto-retry unknown acceptance
            return;
        }

        // Failed status
        if ($result->isRetryable && $delivery->attempts < $delivery->max_attempts) {
            $delayIndex = min($delivery->attempts - 1, count($this->backoff) - 1);
            $delaySeconds = $this->backoff[$delayIndex];

            $delivery->update([
                'status' => MessageDeliveryStatus::Pending,
                'last_error_code' => $result->errorCode,
                'last_error_message' => $result->errorMessage,
            ]);

            $this->release($delaySeconds);

            return;
        }

        $delivery->update([
            'status' => MessageDeliveryStatus::Failed,
            'failed_at' => now(),
            'last_error_code' => $result->errorCode,
            'last_error_message' => $result->errorMessage,
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        MessageDelivery::where('id', $this->messageDeliveryId)->update([
            'status' => MessageDeliveryStatus::Failed,
            'failed_at' => now(),
            'last_error_code' => 'JOB_FAILED',
            'last_error_message' => $exception ? mb_substr($exception->getMessage(), 0, 500) : 'Job attempts exhausted',
        ]);
    }

    private function resolveSubject(MessageDelivery $delivery): string
    {
        $ref = $delivery->template_params['reference'] ?? 'Reservation';

        return match ($delivery->template_name) {
            'booking_submitted_v1' => "Spinellanka: Advance Request Received — {$ref}",
            'booking_confirmed_v1' => "Spinellanka: Reservation Confirmed — {$ref}",
            'booking_declined_v1' => "Spinellanka: Reservation Request Update — {$ref}",
            'booking_cancelled_v1' => "Spinellanka: Reservation Cancelled — {$ref}",
            'staff_new_booking_alert_v1' => "Operations Alert: New Request Received — {$ref}",
            default => "Spinellanka: Update for {$ref}",
        };
    }
}
