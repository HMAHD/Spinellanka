<?php

namespace App\Domain\Identity\Services;

use App\Domain\Audit\Services\AuditService;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SessionRevocationService
{
    public function __construct(protected AuditService $auditService) {}

    public function revokeUserSessions(User $user, ?string $exceptSessionId = null): int
    {
        return DB::transaction(function () use ($user, $exceptSessionId): int {
            // 1. Invalidate via token_version increment
            $user->increment('token_version');

            // 2. Delete database session records
            $query = DB::table('sessions')->where('user_id', $user->id);
            if ($exceptSessionId !== null) {
                $query->where('id', '!=', $exceptSessionId);
            }

            $deletedCount = $query->delete();

            $this->auditService->record(
                action: 'session.revoked',
                actor: auth()->user() ?? $user,
                summary: "Revoked {$deletedCount} active sessions for staff user {$user->email}",
                metadata: [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'deleted_sessions_count' => $deletedCount,
                ],
                auditable: $user
            );

            return $deletedCount;
        });
    }
}
