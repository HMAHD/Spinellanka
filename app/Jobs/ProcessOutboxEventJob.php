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
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProcessOutboxEventJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $timeout = 30;

    /**
     * @var list<int>
     */
    public array $backoff = [10, 30, 60];

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
                        // Booker WhatsApp Delivery (only if phone provided and is_whatsapp == true)
                        if ($booker->is_whatsapp && ! empty(trim($booker->phone))) {
                            $waDelivery = $this->firstOrCreateDelivery(
                                idempotencyKey: "deliv_{$event->id}_whatsapp_booker",
                                attributes: [
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

                        // Booker Email Delivery (if email provided)
                        if (! empty(trim((string) $booker->email))) {
                            $emailDelivery = $this->firstOrCreateDelivery(
                                idempotencyKey: "deliv_{$event->id}_email_booker",
                                attributes: [
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

                    // Lead Traveler WhatsApp Delivery (if different contact person)
                    $leadTraveler = $booking->contacts->firstWhere('type', ContactType::LeadTraveler);
                    if ($leadTraveler && $booker && ($leadTraveler->id !== $booker->id)) {
                        if ($leadTraveler->is_whatsapp && ! empty(trim($leadTraveler->phone)) && trim($leadTraveler->phone) !== trim($booker->phone)) {
                            $ltWaDelivery = $this->firstOrCreateDelivery(
                                idempotencyKey: "deliv_{$event->id}_whatsapp_lead_traveler",
                                attributes: [
                                    'outbox_event_id' => $event->id,
                                    'booking_id' => $booking->id,
                                    'channel' => MessageChannel::WhatsApp,
                                    'recipient_type' => RecipientType::LeadTraveler,
                                    'recipient_identifier' => trim($leadTraveler->phone),
                                    'template_name' => $templateName,
                                    'template_version' => 'v1',
                                    'template_params' => $templateParams,
                                    'provider' => config('services.whatsapp.provider', 'fake'),
                                    'status' => MessageDeliveryStatus::Pending,
                                ]
                            );

                            SendMessageDeliveryJob::dispatch($ltWaDelivery->id);
                        }
                    }

                    // Operations Staff Alert on new booking submission
                    if ($event->event_type === 'booking.submitted') {
                        $staffEmail = config('services.messaging.staff_alert_email') ?? config('mail.operations_alert_address', 'operations@spinellanka.com');
                        $staffDelivery = $this->firstOrCreateDelivery(
                            idempotencyKey: "deliv_{$event->id}_email_staff",
                            attributes: [
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
            $safeError = mb_substr(preg_replace('/Bearer\s+[A-Za-z0-9_\-\.]+/i', 'Bearer [REDACTED]', $e->getMessage()) ?? $e->getMessage(), 0, 500);
            $event->update([
                'status' => OutboxEventStatus::Failed,
                'last_error' => $safeError,
            ]);

            throw $e;
        }
    }

    /**
     * Idempotently create or retrieve message delivery record under concurrent execution.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function firstOrCreateDelivery(string $idempotencyKey, array $attributes): MessageDelivery
    {
        try {
            return MessageDelivery::firstOrCreate(
                ['idempotency_key' => $idempotencyKey],
                $attributes
            );
        } catch (UniqueConstraintViolationException) {
            return MessageDelivery::where('idempotency_key', $idempotencyKey)->firstOrFail();
        }
    }
}
