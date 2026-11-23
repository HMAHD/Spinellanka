<?php

namespace App\Domain\Audit\Services;

use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;

class AuditService
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function record(
        string $action,
        ?User $actor = null,
        string $summary = '',
        array $metadata = [],
        ?Model $auditable = null,
        string $actorType = 'staff'
    ): AuditEvent {
        $actor = $actor ?? auth()->user();

        $sanitizedMetadata = $this->sanitizeMetadata($metadata);

        return AuditEvent::create([
            'actor_id' => $actor?->id,
            'actor_name' => $actor?->name,
            'actor_email' => $actor?->email,
            'actor_type' => $actor ? 'staff' : $actorType,
            'action' => $action,
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->getKey(),
            'summary' => $summary,
            'metadata' => $sanitizedMetadata ?: null,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'created_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    protected function sanitizeMetadata(array $metadata): array
    {
        $sensitiveKeys = [
            'password',
            'current_password',
            'reauth_password',
            'password_confirmation',
            'secret',
            'app_authentication_secret',
            'app_authentication_recovery_codes',
            'remember_token',
            'token',
            'card',
            'cvc',
        ];

        array_walk_recursive($metadata, function (mixed &$value, string|int $key) use ($sensitiveKeys): void {
            if (is_string($key) && in_array(strtolower($key), $sensitiveKeys, true)) {
                $value = '[REDACTED]';
            }
        });

        // For auth.failed, only keep email if it is a valid email format to prevent accidental password logs
        if (isset($metadata['attempted_email']) && is_string($metadata['attempted_email'])) {
            if (! filter_var($metadata['attempted_email'], FILTER_VALIDATE_EMAIL)) {
                $metadata['attempted_email'] = '[MALFORMED_NON_EMAIL_INPUT]';
            }
        }

        return $metadata;
    }
}
