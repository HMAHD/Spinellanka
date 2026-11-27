<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Audit\Services\PrivacyRetentionService;
use Illuminate\Console\Command;

class ExecutePrivacyRetentionCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'retention:execute';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Execute idempotent privacy retention, token purging, and PII anonymization pass';

    /**
     * Execute the console command.
     */
    public function handle(PrivacyRetentionService $retentionService): int
    {
        $this->info('Starting privacy retention and anonymization execution...');

        $stats = $retentionService->executeRetentionPass(actor: null);

        $this->table(
            ['Retention Metric', 'Purged / Processed Count'],
            [
                ['Expired Access Tokens Purged', $stats['purged_tokens_count']],
                ['Stale Booking Holds Purged', $stats['purged_holds_count']],
                ['Expired Export Files Purged', $stats['purged_exports_count']],
                ['Aged Bookings Anonymized', $stats['anonymized_bookings_count']],
            ]
        );

        $this->info('Privacy retention pass completed successfully.');

        return self::SUCCESS;
    }
}
