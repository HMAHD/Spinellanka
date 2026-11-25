<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Messaging\Enums\OutboxEventStatus;
use App\Jobs\ProcessOutboxEventJob;
use App\Models\OutboxEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

class DrainOutboxQueueCommand extends Command
{
    protected $signature = 'outbox:drain {--max-jobs=25} {--max-time=35}';

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
            ->where('status', OutboxEventStatus::Pending)
            ->where('created_at', '<=', now()->subSeconds(15))
            ->where('attempts', '<', 5)
            ->limit(50)
            ->get();

        foreach ($orphanedEvents as $event) {
            $event->increment('attempts');
            ProcessOutboxEventJob::dispatch($event->id);
        }
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
            '--tries' => 3,
        ]);
    }
}

