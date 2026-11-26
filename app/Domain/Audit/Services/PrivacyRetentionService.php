<?php

declare(strict_types=1);

namespace App\Domain\Audit\Services;

use App\Domain\Booking\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\BookingContact;
use App\Models\BookingHold;
use App\Models\ConditionAttachment;
use App\Models\DataExport;
use App\Models\DriverAccessToken;
use App\Models\GuestAccessToken;
use App\Models\Handover;
use App\Models\IncidentAttachment;
use App\Models\LegalHold;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PrivacyRetentionService
{
    public function __construct(
        protected AuditService $auditService,
    ) {}

    /**
     * Execute an idempotent retention and anonymization pass.
     *
     * @return array{
     *     purged_tokens_count: int,
     *     purged_holds_count: int,
     *     purged_exports_count: int,
     *     anonymized_bookings_count: int
     * }
     */
    public function executeRetentionPass(?User $actor = null): array
    {
        $nowUtc = Carbon::now('UTC');

        // 1. Purge expired guest and driver tokens (> 30 days past expiration)
        $tokenCutoff = $nowUtc->copy()->subDays(30);
        $purgedGuestTokens = GuestAccessToken::where('expires_at', '<', $tokenCutoff)->delete();
        $purgedDriverTokens = DriverAccessToken::where('expires_at', '<', $tokenCutoff)->delete();
        $totalPurgedTokens = $purgedGuestTokens + $purgedDriverTokens;

        // 2. Purge stale booking holds (> 7 days past expiration)
        $holdCutoff = $nowUtc->copy()->subDays(7);
        $totalPurgedHolds = BookingHold::where('expires_at', '<', $holdCutoff)->delete();

        // 3. Purge expired data export files (> 24 hours past expiration)
        $expiredExports = DataExport::where('expires_at', '<', $nowUtc)->get();
        $totalPurgedExports = 0;
        foreach ($expiredExports as $export) {
            if ($export->file_path && Storage::disk('local')->exists($export->file_path)) {
                Storage::disk('local')->delete($export->file_path);
            }
            $export->update(['file_path' => 'purged']);
            $totalPurgedExports++;
        }

        // 4. Anonymize aged completed/cancelled bookings (> 3 years old) respecting active legal holds
        $bookingRetentionCutoff = $nowUtc->copy()->subYears(3);
        $anonymizedBookingsCount = 0;

        Booking::query()
            ->whereNull('anonymized_at')
            ->whereIn('status', [BookingStatus::Completed->value, BookingStatus::Cancelled->value])
            ->where(function ($query) use ($bookingRetentionCutoff) {
                $query->where('completed_at', '<', $bookingRetentionCutoff)
                    ->orWhere('cancelled_at', '<', $bookingRetentionCutoff);
            })
            ->whereDoesntHave('legalHolds', fn ($q) => $q->where('is_active', true))
            ->chunkById(100, function (Collection $bookings) use (&$anonymizedBookingsCount) {
                /** @var Booking $booking */
                foreach ($bookings as $booking) {
                    try {
                        $wasAnonymized = DB::transaction(function () use ($booking): bool {
                            // Pessimistic lock to prevent race condition with concurrent legal hold application
                            $lockedBooking = Booking::where('id', $booking->id)->lockForUpdate()->first();
                            if (! $lockedBooking || $lockedBooking->anonymized_at !== null) {
                                return false;
                            }

                            // Assert no active legal hold was applied during lock acquisition
                            $hasActiveHold = LegalHold::where('booking_id', $lockedBooking->id)
                                ->where('is_active', true)
                                ->exists();

                            if ($hasActiveHold) {
                                return false;
                            }

                            // Anonymize traveler contact details
                            BookingContact::where('booking_id', $lockedBooking->id)->update([
                                'first_name' => 'Anonymized',
                                'last_name' => 'Traveler',
                                'email' => sprintf('anonymized_%d@redacted.invalid', $lockedBooking->id),
                                'phone' => '[REDACTED]',
                                'is_whatsapp' => false,
                            ]);

                            // Anonymize handovers signatures and notes
                            $handovers = Handover::where('booking_id', $lockedBooking->id)->get();
                            foreach ($handovers as $handover) {
                                if ($handover->customer_signature_path && Storage::disk('local')->exists($handover->customer_signature_path)) {
                                    Storage::disk('local')->delete($handover->customer_signature_path);
                                }
                                if ($handover->staff_signature_path && Storage::disk('local')->exists($handover->staff_signature_path)) {
                                    Storage::disk('local')->delete($handover->staff_signature_path);
                                }

                                $handover->update([
                                    'customer_signature_path' => null,
                                    'customer_signer_name' => 'Anonymized',
                                    'staff_signature_path' => null,
                                    'document_inspection_notes' => '[REDACTED_RETENTION]',
                                    'operational_notes' => null,
                                ]);

                                // Purge condition attachment files
                                if ($handover->conditionRecord) {
                                    $attachments = ConditionAttachment::where('vehicle_condition_record_id', $handover->conditionRecord->id)->get();
                                    foreach ($attachments as $attachment) {
                                        if (Storage::disk('local')->exists($attachment->file_path)) {
                                            Storage::disk('local')->delete($attachment->file_path);
                                        }
                                        $attachment->update([
                                            'file_path' => 'purged',
                                            'processing_status' => 'purged_retention',
                                        ]);
                                    }
                                }
                            }

                            // Purge incident attachment files linked to booking
                            $incidentAttachments = IncidentAttachment::whereHas('incident', fn ($q) => $q->where('booking_id', $lockedBooking->id))->get();
                            foreach ($incidentAttachments as $incAttachment) {
                                if (Storage::disk('local')->exists($incAttachment->file_path)) {
                                    Storage::disk('local')->delete($incAttachment->file_path);
                                }
                                $incAttachment->update([
                                    'file_path' => 'purged',
                                    'processing_status' => 'purged_retention',
                                ]);
                            }

                            $lockedBooking->update([
                                'anonymized_at' => Carbon::now('UTC'),
                            ]);

                            return true;
                        });

                        if ($wasAnonymized) {
                            $anonymizedBookingsCount++;
                        }
                    } catch (\Throwable $e) {
                        Log::error(sprintf('Failed to anonymize booking ID %d: %s', $booking->id, $e->getMessage()), [
                            'booking_id' => $booking->id,
                            'exception' => $e,
                        ]);
                    }
                }
            });

        $this->auditService->record(
            action: 'retention.pass_completed',
            actor: $actor,
            summary: sprintf(
                'Privacy retention pass completed: %d tokens purged, %d holds purged, %d exports purged, %d bookings anonymized',
                $totalPurgedTokens,
                $totalPurgedHolds,
                $totalPurgedExports,
                $anonymizedBookingsCount
            ),
            metadata: [
                'purged_tokens_count' => $totalPurgedTokens,
                'purged_holds_count' => $totalPurgedHolds,
                'purged_exports_count' => $totalPurgedExports,
                'anonymized_bookings_count' => $anonymizedBookingsCount,
            ]
        );

        return [
            'purged_tokens_count' => $totalPurgedTokens,
            'purged_holds_count' => $totalPurgedHolds,
            'purged_exports_count' => $totalPurgedExports,
            'anonymized_bookings_count' => $anonymizedBookingsCount,
        ];
    }
}
