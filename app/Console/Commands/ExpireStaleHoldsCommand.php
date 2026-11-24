<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Availability\Services\AvailabilityService;
use Illuminate\Console\Command;

class ExpireStaleHoldsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'availability:expire-holds';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Expire stale booking holds whose hold window has elapsed.';

    /**
     * Execute the console command.
     */
    public function handle(AvailabilityService $availabilityService): int
    {
        $this->components->info('Scanning for stale booking holds...');

        $expired = $availabilityService->expireStaleHolds();

        $this->components->info("Expired {$expired} stale hold(s).");

        return self::SUCCESS;
    }
}
