<?php

namespace App\Domain\Identity\Services;

use App\Domain\Audit\Services\AuditService;
use App\Domain\Identity\Role;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserService
{
    public function __construct(
        protected AuditService $auditService,
        protected SessionRevocationService $sessionRevocationService
    ) {}

    /**
     * @param  array{name: string, email: string, password?: string, is_active?: bool}  $data
     */
    public function create(array $data, Role|string $role = Role::Staff): User
    {
        $roleValue = $role instanceof Role ? $role->value : $role;

        return DB::transaction(function () use ($data, $roleValue): User {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => isset($data['password']) ? Hash::make($data['password']) : Hash::make(str()->random(32)),
                'is_active' => $data['is_active'] ?? true,
                'token_version' => 1,
            ]);

            $user->assignRole($roleValue);

            $this->auditService->record(
                action: 'user.created',
                actor: auth()->user(),
                summary: "Created staff user {$user->email} with role {$roleValue}",
                metadata: [
                    'user_id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $roleValue,
                ],
                auditable: $user
            );

            return $user;
        });
    }

    /**
     * @param  array{name?: string, email?: string, password?: string, is_active?: bool}  $data
     */
    public function update(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data): User {
            $changes = [];

            if (isset($data['name']) && $data['name'] !== $user->name) {
                $changes['name'] = ['from' => $user->name, 'to' => $data['name']];
                $user->name = $data['name'];
            }

            if (isset($data['email']) && $data['email'] !== $user->email) {
                $changes['email'] = ['from' => $user->email, 'to' => $data['email']];
                $user->email = $data['email'];
            }

            if (! empty($data['password'])) {
                $changes['password'] = 'changed';
                $user->password = Hash::make($data['password']);
                $user->token_version++;
            }

            if (isset($data['is_active']) && $data['is_active'] !== $user->is_active) {
                if (! $data['is_active']) {
                    $this->validateCanDeactivate(auth()->user(), $user);
                }
                $changes['is_active'] = ['from' => $user->is_active, 'to' => $data['is_active']];
                $user->is_active = $data['is_active'];
                if (! $user->is_active) {
                    $user->token_version++;
                }
            }

            $user->save();

            if (! empty($changes)) {
                $this->auditService->record(
                    action: 'user.updated',
                    actor: auth()->user(),
                    summary: "Updated staff user {$user->email}",
                    metadata: ['changes' => $changes],
                    auditable: $user
                );
            }

            return $user;
        });
    }

    public function deactivate(User $actor, User $target): void
    {
        $this->validateCanDeactivate($actor, $target);

        DB::transaction(function () use ($actor, $target): void {
            $target->is_active = false;
            $target->token_version++;
            $target->save();

            $this->sessionRevocationService->revokeUserSessions($target);

            $this->auditService->record(
                action: 'user.deactivated',
                actor: $actor,
                summary: "Deactivated staff account {$target->email} and revoked active sessions",
                metadata: ['target_id' => $target->id, 'email' => $target->email],
                auditable: $target
            );
        });
    }

    public function activate(User $actor, User $target): void
    {
        DB::transaction(function () use ($actor, $target): void {
            $target->is_active = true;
            $target->save();

            $this->auditService->record(
                action: 'user.activated',
                actor: $actor,
                summary: "Activated staff account {$target->email}",
                metadata: ['target_id' => $target->id, 'email' => $target->email],
                auditable: $target
            );
        });
    }

    public function assignRole(User $actor, User $target, Role|string $role): void
    {
        $roleValue = $role instanceof Role ? $role->value : $role;

        // If removing Owner/Admin from target, ensure they are not the last active owner
        if ($target->hasRole(Role::OwnerAdmin->value) && $roleValue !== Role::OwnerAdmin->value) {
            $this->validateCanDemoteOwner($target);
        }

        DB::transaction(function () use ($actor, $target, $roleValue): void {
            $oldRoles = $target->getRoleNames()->toArray();
            $target->syncRoles([$roleValue]);

            $this->auditService->record(
                action: 'role.assigned',
                actor: $actor,
                summary: "Updated roles for {$target->email} to {$roleValue}",
                metadata: [
                    'old_roles' => $oldRoles,
                    'new_role' => $roleValue,
                ],
                auditable: $target
            );
        });
    }

    public function validateCanDeactivate(?User $actor, User $target): void
    {
        if ($actor && $actor->id === $target->id) {
            throw new AuthorizationException('You cannot deactivate your own account.');
        }

        if ($target->hasRole(Role::OwnerAdmin->value)) {
            $this->validateCanDemoteOwner($target);
        }
    }

    public function validateCanDemoteOwner(User $target): void
    {
        $activeOwnerCount = User::role(Role::OwnerAdmin->value)
            ->where('is_active', true)
            ->count();

        if ($activeOwnerCount <= 1 && $target->is_active) {
            throw new AuthorizationException('Cannot deactivate or remove the Owner/Admin role from the last active owner.');
        }
    }
}
