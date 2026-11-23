<?php

namespace App\Domain\Identity\Services;

use App\Domain\Audit\Services\AuditService;
use App\Models\User;

class ReauthService
{
    public function __construct(protected AuditService $auditService) {}

    public function isRecentlyConfirmed(): bool
    {
        $confirmedAt = (int) session()->get('auth.password_confirmed_at', 0);
        $timeout = (int) config('auth.password_timeout', 900);

        return (now()->unix() - $confirmedAt) < $timeout;
    }

    public function confirm(User $user, string $action = 'sensitive_action'): void
    {
        session()->put('auth.password_confirmed_at', now()->unix());

        $this->auditService->record(
            action: 'auth.reauthenticated',
            actor: $user,
            summary: "Staff member confirmed password for action: {$action}",
            metadata: ['action' => $action],
            auditable: $user
        );
    }

    public function consumeConfirmation(): void
    {
        session()->forget('auth.password_confirmed_at');
    }
}

