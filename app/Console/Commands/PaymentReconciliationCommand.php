<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Payment\Services\PaymentReconciliationService;
use Illuminate\Console\Command;

class PaymentReconciliationCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payments:reconcile {--timeout-hours=2 : Number of hours after which a pending transaction is considered stale}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sweep stale pending payment transactions and reconcile missing gateway confirmations';

    /**
     * Execute the console command.
     */
    public function handle(PaymentReconciliationService $reconciliationService): int
    {
        $timeoutHours = (int) $this->option('timeout-hours');

        $this->info("Sweeping stale pending payment transactions older than {$timeoutHours} hours...");

        $count = $reconciliationService->sweepStalePendingTransactions($timeoutHours);

        $this->info("Reconciliation sweep complete. {$count} stale transactions processed.");

        return Command::SUCCESS;
    }
}
