<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Availability\Services\AvailabilityService;
use App\Domain\Booking\Enums\ProposalStatus;
use App\Domain\Booking\Services\ProposalService;
use App\Models\Proposal;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ExpireStaleProposalsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'proposals:expire-stale';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Expire proposals whose response deadline has elapsed and release schedule holds';

    /**
     * Execute the console command.
     */
    public function handle(ProposalService $proposalService, AvailabilityService $availabilityService): int
    {
        $now = Carbon::now('Asia/Colombo');

        $staleProposals = Proposal::where('status', ProposalStatus::Sent->value)
            ->where('expires_at', '<=', $now)
            ->get();

        $count = 0;
        foreach ($staleProposals as $proposal) {
            $proposalService->expireProposal($proposal);
            $count++;
        }

        // Also sweep any orphaned expired proposal holds
        $expiredHoldsCount = $availabilityService->expireStaleProposalHolds();

        $this->info("Expired {$count} stale proposal(s) and {$expiredHoldsCount} stale hold block(s).");

        return self::SUCCESS;
    }
}
