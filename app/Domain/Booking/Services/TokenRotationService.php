<?php

declare(strict_types=1);

namespace App\Domain\Booking\Services;

use App\Domain\Audit\Services\AuditService;
use App\Domain\Identity\Permission;
use App\Domain\Messaging\Services\OutboxService;
use App\Models\Booking;
use App\Models\GuestAccessToken;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TokenRotationService
{
    public function __construct(
        protected AuditService $auditService,
        protected OutboxService $outboxService,
    ) {}

    /**
     * Rotate the guest access token for a booking: revokes all existing active tokens and returns the new plain token.
     */
    public function rotateToken(Booking $booking, string $purpose = 'guest_access', ?User $staff = null): string
    {
        if ($staff !== null && ! $staff->can(Permission::BookingReview->value) && ! $staff->hasPermissionTo(Permission::BookingReview->value)) {
            throw new InvalidArgumentException('Actor does not have booking.review permission.');
        }

        return DB::transaction(function () use ($booking, $purpose, $staff) {
            $now = Carbon::now('Asia/Colombo');

            // Revoke all previous active tokens
            GuestAccessToken::where('booking_id', $booking->id)
                ->whereNull('revoked_at')
                ->update(['revoked_at' => $now]);

            $plainToken = GuestAccessToken::generateToken();
            $tokenHash = GuestAccessToken::hashToken($plainToken);

            $tokenRecord = GuestAccessToken::create([
                'booking_id' => $booking->id,
                'token_hash' => $tokenHash,
                'purpose' => $purpose,
                'expires_at' => (clone $now)->addDays(30),
            ]);

            $this->auditService->record(
                action: 'guest_access_token.rotated',
                actor: $staff,
                summary: "Rotated guest access token for booking {$booking->reference}",
                metadata: [
                    'booking_id' => $booking->id,
                    'purpose' => $purpose,
                    'rotated_by' => $staff ? $staff->name : 'system',
                ],
                auditable: $booking,
                actorType: $staff ? 'staff' : 'system'
            );

            $this->outboxService->record(
                eventType: 'booking.token_rotated',
                aggregate: $booking,
                payload: [
                    'booking_id' => $booking->id,
                    'reference' => $booking->reference,
                    'status_url' => route('booking.status', ['reference' => $booking->reference, 'token' => $plainToken]),
                ],
                idempotencyKey: "evt_booking_{$booking->id}_token_rotated_{$tokenRecord->id}"
            );

            return $plainToken;
        });
    }

    /**
     * Revoke all access tokens for a booking.
     */
    public function revokeTokens(Booking $booking, ?User $staff = null): void
    {
        if ($staff !== null && ! $staff->can(Permission::BookingReview->value) && ! $staff->hasPermissionTo(Permission::BookingReview->value)) {
            throw new InvalidArgumentException('Actor does not have booking.review permission.');
        }

        DB::transaction(function () use ($booking, $staff) {
            $now = Carbon::now('Asia/Colombo');

            $count = GuestAccessToken::where('booking_id', $booking->id)
                ->whereNull('revoked_at')
                ->update(['revoked_at' => $now]);

            $this->auditService->record(
                action: 'guest_access_token.revoked',
                actor: $staff,
                summary: "Revoked {$count} guest access token(s) for booking {$booking->reference}",
                metadata: [
                    'booking_id' => $booking->id,
                    'revoked_count' => $count,
                ],
                auditable: $booking,
                actorType: $staff ? 'staff' : 'system'
            );

            $this->outboxService->record(
                eventType: 'booking.tokens_revoked',
                aggregate: $booking,
                payload: [
                    'booking_id' => $booking->id,
                    'reference' => $booking->reference,
                ],
                idempotencyKey: "evt_booking_{$booking->id}_tokens_revoked_".hash('sha256', ($staff ? (string) $staff->id : 'system').$booking->id.Carbon::now('Asia/Colombo')->toDateString())
            );
        });
    }
}
