<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $booking_id
 * @property string $reason
 * @property string|null $details
 * @property int|null $applied_by_user_id
 * @property Carbon $applied_at
 * @property bool $is_active
 * @property int|null $released_by_user_id
 * @property Carbon|null $released_at
 * @property string|null $release_reason
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Booking $booking
 * @property-read User|null $appliedByUser
 * @property-read User|null $releasedByUser
 */
class LegalHold extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'applied_at' => 'datetime',
            'is_active' => 'boolean',
            'released_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function appliedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applied_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function releasedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by_user_id');
    }
}
