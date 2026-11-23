<?php

namespace App\Policies;

use App\Domain\Identity\Permission;
use App\Models\AuditEvent;
use App\Models\User;

class AuditEventPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::AuditView->value);
    }

    public function view(User $user, AuditEvent $event): bool
    {
        return $user->can(Permission::AuditView->value);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, AuditEvent $event): bool
    {
        // Audit events are append-only; update is unconditionally forbidden for ALL roles
        return false;
    }

    public function delete(User $user, AuditEvent $event): bool
    {
        // Audit events are append-only; deletion is unconditionally forbidden for ALL roles
        return false;
    }
}
