<?php

declare(strict_types=1);

namespace Tests\Feature\Messaging;

use App\Domain\Booking\Enums\ContactType;
use App\Domain\Messaging\Enums\MessageChannel;
use App\Domain\Messaging\Enums\MessageDeliveryStatus;
use App\Domain\Messaging\Enums\OutboxEventStatus;
use App\Domain\Messaging\Enums\RecipientType;
use App\Domain\Messaging\Services\TemplateParameterMapper;
use App\Jobs\ProcessOutboxEventJob;
use App\Jobs\SendMessageDeliveryJob;
use App\Models\Booking;
use App\Models\BookingContact;
use App\Models\MessageDelivery;
use App\Models\OutboxEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed();
    Config::set('services.messaging.staff_alert_email', 'ops@spinellanka.test');
});

test('ProcessOutboxEventJob creates deliveries and marks outbox event processed', function (): void {
    Queue::fake([SendMessageDeliveryJob::class]);

    $booking = Booking::factory()->create([
        'reference' => 'BKG-JOB-TEST1',
    ]);

    BookingContact::create([
        'booking_id' => $booking->id,
        'type' => ContactType::Booker,
        'first_name' => 'Dilshan',
        'last_name' => 'Mendis',
        'phone' => '+94770001122',
        'is_whatsapp' => true,
        'email' => 'dilshan@example.com',
        'marketing_consent' => false,
    ]);

    $event = OutboxEvent::create([
        'event_type' => 'booking.submitted',
        'aggregate_type' => Booking::class,
        'aggregate_id' => $booking->id,
        'idempotency_key' => "evt_booking_{$booking->id}_submitted_v1",
        'payload_version' => 1,
        'payload' => [
            'booking_id' => $booking->id,
            'reference' => $booking->reference,
            'status_url' => 'https://spinellanka.test/status/test',
        ],
        'status' => OutboxEventStatus::Pending,
        'attempts' => 0,
    ]);

    $job = new ProcessOutboxEventJob($event->id);
    $job->handle(app(TemplateParameterMapper::class));

    $event->refresh();
    expect($event->status)->toBe(OutboxEventStatus::Processed)
        ->and($event->processed_at)->not->toBeNull();

    // Verify WhatsApp delivery created
    $waDelivery = MessageDelivery::where('outbox_event_id', $event->id)
        ->where('channel', MessageChannel::WhatsApp)
        ->first();

    expect($waDelivery)->not->toBeNull()
        ->and($waDelivery->recipient_identifier)->toBe('+94770001122')
        ->and($waDelivery->recipient_type)->toBe(RecipientType::Booker)
        ->and($waDelivery->status)->toBe(MessageDeliveryStatus::Pending)
        ->and($waDelivery->template_name)->toBe('booking_submitted_v1');

    // Verify Booker Email delivery created
    $emailDelivery = MessageDelivery::where('outbox_event_id', $event->id)
        ->where('channel', MessageChannel::Email)
        ->where('recipient_type', RecipientType::Booker)
        ->first();

    expect($emailDelivery)->not->toBeNull()
        ->and($emailDelivery->recipient_identifier)->toBe('dilshan@example.com')
        ->and($emailDelivery->status)->toBe(MessageDeliveryStatus::Pending);

    // Verify Staff Alert Email delivery created
    $staffDelivery = MessageDelivery::where('outbox_event_id', $event->id)
        ->where('channel', MessageChannel::Email)
        ->where('recipient_type', RecipientType::Staff)
        ->first();

    expect($staffDelivery)->not->toBeNull()
        ->and($staffDelivery->recipient_identifier)->toBe('ops@spinellanka.test')
        ->and($staffDelivery->template_name)->toBe('staff_new_booking_alert_v1');

    // Verify jobs pushed to notifications queue
    Queue::assertPushed(SendMessageDeliveryJob::class, 3);
});

test('ProcessOutboxEventJob skips whatsapp when booker is_whatsapp is false', function (): void {
    Queue::fake([SendMessageDeliveryJob::class]);

    $booking = Booking::factory()->create();

    BookingContact::create([
        'booking_id' => $booking->id,
        'type' => ContactType::Booker,
        'first_name' => 'Sarah',
        'last_name' => 'Connor',
        'phone' => '+447700900111',
        'is_whatsapp' => false,
        'email' => 'sarah@example.com',
        'marketing_consent' => false,
    ]);

    $event = OutboxEvent::create([
        'event_type' => 'booking.submitted',
        'aggregate_type' => Booking::class,
        'aggregate_id' => $booking->id,
        'idempotency_key' => "evt_booking_{$booking->id}_submitted_v1",
        'payload_version' => 1,
        'payload' => ['booking_id' => $booking->id, 'reference' => $booking->reference],
        'status' => OutboxEventStatus::Pending,
        'attempts' => 0,
    ]);

    $job = new ProcessOutboxEventJob($event->id);
    $job->handle(app(TemplateParameterMapper::class));

    $waCount = MessageDelivery::where('outbox_event_id', $event->id)
        ->where('channel', MessageChannel::WhatsApp)
        ->count();

    expect($waCount)->toBe(0);

    $emailCount = MessageDelivery::where('outbox_event_id', $event->id)
        ->where('channel', MessageChannel::Email)
        ->where('recipient_type', RecipientType::Booker)
        ->count();

    expect($emailCount)->toBe(1);
});

test('duplicate execution of ProcessOutboxEventJob is idempotent and produces zero duplicate deliveries', function (): void {
    Queue::fake([SendMessageDeliveryJob::class]);

    $booking = Booking::factory()->create();

    BookingContact::create([
        'booking_id' => $booking->id,
        'type' => ContactType::Booker,
        'first_name' => 'Alice',
        'last_name' => 'Smith',
        'phone' => '+94770099887',
        'is_whatsapp' => true,
        'email' => 'alice@example.com',
        'marketing_consent' => false,
    ]);

    $event = OutboxEvent::create([
        'event_type' => 'booking.submitted',
        'aggregate_type' => Booking::class,
        'aggregate_id' => $booking->id,
        'idempotency_key' => "evt_booking_{$booking->id}_submitted_v1",
        'payload_version' => 1,
        'payload' => ['booking_id' => $booking->id, 'reference' => $booking->reference],
        'status' => OutboxEventStatus::Pending,
        'attempts' => 0,
    ]);

    $mapper = app(TemplateParameterMapper::class);

    // First execution
    $job1 = new ProcessOutboxEventJob($event->id);
    $job1->handle($mapper);

    $deliveryCount1 = MessageDelivery::where('outbox_event_id', $event->id)->count();
    expect($deliveryCount1)->toBeGreaterThan(0);

    // Reset status to Pending to simulate replay/duplicate trigger
    $event->update(['status' => OutboxEventStatus::Pending]);

    // Second execution
    $job2 = new ProcessOutboxEventJob($event->id);
    $job2->handle($mapper);

    $deliveryCount2 = MessageDelivery::where('outbox_event_id', $event->id)->count();
    expect($deliveryCount2)->toBe($deliveryCount1);
});

