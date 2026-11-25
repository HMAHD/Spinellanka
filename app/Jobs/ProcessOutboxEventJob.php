<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Booking\Enums\ContactType;
use App\Domain\Messaging\Enums\MessageChannel;
use App\Domain\Messaging\Enums\MessageDeliveryStatus;
use App\Domain\Messaging\Enums\OutboxEventStatus;
use App\Domain\Messaging\Enums\RecipientType;
use App\Domain\Messaging\Services\TemplateParameterMapper;
use App\Models\Booking;
use App\Models\MessageDelivery;
use App\Models\OutboxEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProcessOutboxEventJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $timeout = 30;

    public function __construct(
        public int $outboxEventId,
    ) {
        $this->onQueue('notifications');
    }

    public function handle(TemplateParameterMapper $mapper): void
    {
        $event = OutboxEvent::find($this->outboxEventId);
        if (! $event || $event->status === OutboxEventStatus::Processed) {
            return;
        }

        $event->update([
            'status' => OutboxEventStatus::Processing,
        ]);

        try {
            if ($event->aggregate_type === Booking::class) {
                $booking = Booking::with(['contacts', 'vehicle', 'airportPickupDetail', 'airportTransferDetail', 'rideDetail', 'rentDetail'])->find($event->aggregate_id);

                if ($booking) {
                    $payload = is_array($event->payload) ? $event->payload : (json_decode((string) $event->payload, true) ?? []);
                    $templateParams = $mapper->mapBookingParams($booking, $payload);

                    $templateName = match ($event->event_type) {
                        'booking.submitted' => 'booking_submitted_v1',
                        'booking.confirmed' => 'booking_confirmed_v1',
                        'booking.declined' => 'booking_declined_v1',
                        'booking.cancelled' => 'booking_cancelled_v1',
                        default => 'booking_notice_v1',
                    };

                    $booker = $booking->contacts->firstWhere('type', ContactType::Booker);

                    if ($booker) {
                        // WhatsApp Delivery (only if phone provided and is_whatsapp == true)
                        if ($booker->is_whatsapp && ! empty(trim($booker->phone))) {
                            $waDelivery = MessageDelivery::firstOrCreate(
                                [
                                    'idempotency_key' => "deliv_{$event->id}_whatsapp_booker",
                                ],
                                [
                                    'outbox_event_id' => $event->id,
                                    'booking_id' => $booking->id,
                                    'channel' => MessageChannel::WhatsApp,
                                    'recipient_type' => RecipientType::Booker,
                                    'recipient_identifier' => trim($booker->phone),
                                    'template_name' => $templateName,
                                    'template_version' => 'v1',
                                    'template_params' => $templateParams,
                                    'provider' => config('services.whatsapp.provider', 'fake'),
                                    'status' => MessageDeliveryStatus::Pending,
                                ]
                            );

                            SendMessageDeliveryJob::dispatch($waDelivery->id);
                        }

                        // Email Delivery (if email provided)
                        if (! empty(trim((string) $booker->email))) {
                            $emailDelivery = MessageDelivery::firstOrCreate(
                                [
                                    'idempotency_key' => "deliv_{$event->id}_email_booker",
                                ],
                                [
                                    'outbox_event_id' => $event->id,
                                    'booking_id' => $booking->id,
                                    'channel' => MessageChannel::Email,
                                    'recipient_type' => RecipientType::Booker,
                                    'recipient_identifier' => trim($booker->email),
                                    'template_name' => $templateName,
                                    'template_version' => 'v1',
                                    'template_params' => $templateParams,
                                    'provider' => config('services.transactional_mailer.provider', 'fake'),
                                    'status' => MessageDeliveryStatus::Pending,
                                ]
                            );

                            SendMessageDeliveryJob::dispatch($emailDelivery->id);
                        }
                    }

                    // Operations Staff Alert on new booking submission
                    if ($event->event_type === 'booking.submitted') {
                        $staffEmail = config('services.messaging.staff_alert_email') ?? config('mail.operations_alert_address', 'operations@spinellanka.com');
                        $staffDelivery = MessageDelivery::firstOrCreate(
                            [
                                'idempotency_key' => "deliv_{$event->id}_email_staff",
                            ],
                            [
                                'outbox_event_id' => $event->id,
                                'booking_id' => $booking->id,
                                'channel' => MessageChannel::Email,
                                'recipient_type' => RecipientType::Staff,
                                'recipient_identifier' => $staffEmail,
                                'template_name' => 'staff_new_booking_alert_v1',
                                'template_version' => 'v1',
                                'template_params' => $templateParams,
                                'provider' => config('services.transactional_mailer.provider', 'fake'),
                                'status' => MessageDeliveryStatus::Pending,
                            ]
                        );

                        SendMessageDeliveryJob::dispatch($staffDelivery->id);
                    }
                }
            }

            $event->update([
                'status' => OutboxEventStatus::Processed,
                'processed_at' => now(),
            ]);
        } catch (Throwable $e) {
            $event->update([
                'status' => OutboxEventStatus::Failed,
                'last_error' => mb_substr($e->getMessage(), 0, 500),
            ]);
            throw $e;
        }
    }
}
