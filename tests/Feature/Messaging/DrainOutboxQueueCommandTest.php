<?php

declare(strict_types=1);

namespace Tests\Feature\Messaging;

use App\Domain\Messaging\Enums\OutboxEventStatus;
use App\Jobs\ProcessOutboxEventJob;
use App\Models\Booking;
use App\Models\OutboxEvent;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed();
});

test('outbox:drain sweeps orphaned pending events older than 15 seconds', function (): void {
    Queue::fake([ProcessOutboxEventJob::class]);

    $booking = Booking::factory()->create();

    // Event 1: Pending and 20 seconds old -> Should be swept
    $oldEvent = OutboxEvent::create([
        'event_type' => 'booking.submitted',
        'aggregate_type' => Booking::class,
        'aggregate_id' => $booking->id,
        'idempotency_key' => 'evt_test_old_1',
        'payload_version' => 1,
        'payload' => ['booking_id' => $booking->id],
        'status' => OutboxEventStatus::Pending,
        'attempts' => 0,
        'created_at' => Carbon::now()->subSeconds(20),
    ]);

    // Event 2: Pending and 5 seconds old -> Should NOT be swept yet (might be in flight)
    $recentEvent = OutboxEvent::create([
        'event_type' => 'booking.submitted',
        'aggregate_type' => Booking::class,
        'aggregate_id' => $booking->id,
        'idempotency_key' => 'evt_test_recent_2',
        'payload_version' => 1,
        'payload' => ['booking_id' => $booking->id],
        'status' => OutboxEventStatus::Pending,
        'attempts' => 0,
        'created_at' => Carbon::now()->subSeconds(5),
    ]);

    // Event 3: Already processed -> Should NOT be swept
    $processedEvent = OutboxEvent::create([
        'event_type' => 'booking.submitted',
        'aggregate_type' => Booking::class,
        'aggregate_id' => $booking->id,
        'idempotency_key' => 'evt_test_proc_3',
        'payload_version' => 1,
        'payload' => ['booking_id' => $booking->id],
        'status' => OutboxEventStatus::Processed,
        'attempts' => 1,
        'created_at' => Carbon::now()->subSeconds(30),
    ]);

    Artisan::call('outbox:drain', ['--max-jobs' => 1, '--max-time' => 1]);

    Queue::assertPushed(ProcessOutboxEventJob::class, function ($job) use ($oldEvent) {
        return $job->outboxEventId === $oldEvent->id;
    });

    Queue::assertNotPushed(ProcessOutboxEventJob::class, function ($job) use ($recentEvent) {
        return $job->outboxEventId === $recentEvent->id;
    });

    $oldEvent->refresh();
    expect($oldEvent->attempts)->toBe(1);
});

test('outbox:drain exits gracefully when overlapping lock is already acquired', function (): void {
    Queue::fake([ProcessOutboxEventJob::class]);

    $lock = Cache::lock('spinellanka_outbox_drain', 90);
    $lock->get();

    $exitCode = Artisan::call('outbox:drain');
    expect($exitCode)->toBe(0);

    $output = Artisan::output();
    expect($output)->toContain('Another outbox drain process is already active');

    $lock->release();
});

