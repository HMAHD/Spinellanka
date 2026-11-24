<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Availability\Services\AvailabilityService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CleanupStaleBlocksCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'availability:cleanup-stale {--days=30 : Number of days retention for expired blocks}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up stale expired availability holds and blocks.';

    /**
     * Execute the console command.
     */
    public function handle(AvailabilityService $availabilityService): int
    {
        $days = (int) $this->option('days');
        $olderThan = Carbon::now('Asia/Colombo')->subDays($days);

        $this->components->info("Checking for expired blocks older than {$days} days ({$olderThan->toDateTimeString()})...");

        $cleaned = $availabilityService->cleanupStaleBlocks($olderThan);

        $this->components->info("Cleaned up {$cleaned} stale block(s).");

        return self::SUCCESS;
    }
}
