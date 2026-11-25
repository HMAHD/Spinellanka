<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Messaging\Enums\MessageDeliveryStatus;
use App\Domain\Messaging\Enums\OutboxEventStatus;
use App\Jobs\ProcessOutboxEventJob;
use App\Models\MessageDelivery;
use App\Models\OutboxEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

class DrainOutboxQueueCommand extends Command
{
    protected $signature = 'outbox:drain {--max-jobs=50} {--max-time=35}';

    protected $description = 'Drain outbox events and database queue jobs safely without worker overlap on shared cPanel hosting';

    public function handle(): int
    {
        // Acquire non-overlapping lock for 90 seconds (longer than max-time 35s to prevent cron overlap)
        $lock = Cache::lock('spinellanka_outbox_drain', 90);

        if (! $lock->get()) {
            $this->info('Another outbox drain process is already active. Exiting.');

            return self::SUCCESS;
        }

        try {
            $this->sweepOrphanedOutboxEvents();
            $this->sweepStalledSendingDeliveries();
            $this->drainQueue();
        } finally {
            $lock->release();
        }

        return self::SUCCESS;
    }

    /**
     * Phase 1: Outbox Poller / Sweeper
     * Recovers any outbox events that were committed to MySQL but never enqueued or stalled.
     */
    private function sweepOrphanedOutboxEvents(): void
    {
        $orphanedEvents = OutboxEvent::query()
            ->where(function ($query) {
                $query->where('status', OutboxEventStatus::Pending)
                    ->where('created_at', '<=', now()->subSeconds(15));
            })
            ->orWhere(function ($query) {
                // Recover stalled processing events where worker was killed by host
                $query->where('status', OutboxEventStatus::Processing)
                    ->where('updated_at', '<=', now()->subMinutes(5));
            })
            ->where('attempts', '<', 5)
            ->limit(50)
            ->get();

        foreach ($orphanedEvents as $event) {
            $event->increment('attempts');
            $event->touch();
            ProcessOutboxEventJob::dispatch($event->id);
        }
    }

    /**
     * Recover deliveries trapped in 'sending' status if a worker was killed by cPanel.
     * Transitions them to DeliveryUnknown to require manual staff review.
     */
    private function sweepStalledSendingDeliveries(): void
    {
        MessageDelivery::query()
            ->where('status', MessageDeliveryStatus::Sending)
            ->where('last_attempted_at', '<=', now()->subMinutes(5))
            ->update([
                'status' => MessageDeliveryStatus::DeliveryUnknown,
                'last_error_code' => 'WORKER_KILLED',
                'last_error_message' => 'Delivery worker terminated unexpectedly during gateway transmission.',
            ]);
    }

    /**
     * Phase 2: Short-Lived Queue Worker
     * Drains pending jobs and exits cleanly before next minute cron.
     */
    private function drainQueue(): void
    {
        Artisan::call('queue:work', [
            '--queue' => 'notifications,default',
            '--stop-when-empty' => true,
            '--max-time' => (int) $this->option('max-time'),
            '--max-jobs' => (int) $this->option('max-jobs'),
            '--tries' => 5,
        ]);
    }
}
