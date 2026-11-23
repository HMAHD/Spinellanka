<?php

namespace App\Models;

use App\Domain\Audit\Exceptions\ImmutableAuditLogException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'actor_id',
        'actor_name',
        'actor_email',
        'actor_type',
        'action',
        'auditable_type',
        'auditable_id',
        'summary',
        'metadata',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new ImmutableAuditLogException('Audit events cannot be modified.');
        });

        static::deleting(function (): void {
            throw new ImmutableAuditLogException('Audit events cannot be deleted.');
        });
    }
}
