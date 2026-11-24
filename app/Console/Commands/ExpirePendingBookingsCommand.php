<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Booking\Enums\BookingStatus;
use App\Domain\Booking\Services\BookingLifecycleService;
use App\Models\Booking;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ExpirePendingBookingsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'booking:expire-pending';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Expire stale pending booking requests whose review window has elapsed.';

    /**
     * Execute the console command.
     */
    public function handle(BookingLifecycleService $lifecycleService): int
    {
        $this->components->info('Scanning for expired pending bookings...');

        $now = Carbon::now('Asia/Colombo');
        $staleBookings = Booking::whereIn('status', [BookingStatus::Submitted->value, BookingStatus::UnderReview->value])
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $now)
            ->get();

        $count = 0;
        foreach ($staleBookings as $booking) {
            $lifecycleService->expirePending($booking);
            $count++;
        }

        $this->components->info("Expired {$count} stale pending booking(s).");

        return self::SUCCESS;
    }
}

