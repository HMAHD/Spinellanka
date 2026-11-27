<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Booking\Enums\ReviewSource;
use App\Domain\Booking\Enums\ReviewStatus;
use App\Domain\Booking\ServiceType;
use Carbon\Carbon;
use Database\Factories\ReviewFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $booking_id
 * @property string $traveler_name
 * @property string|null $traveler_country
 * @property ServiceType $service_type
 * @property int $rating
 * @property string|null $title
 * @property string $review_text
 * @property ReviewSource $source
 * @property string|null $external_url
 * @property ReviewStatus $status
 * @property bool $is_featured
 * @property bool $verified_trip
 * @property string|null $invitation_token
 * @property Carbon|null $invited_at
 * @property Carbon|null $submitted_at
 * @property int|null $moderated_by_user_id
 * @property Carbon|null $moderated_at
 * @property string|null $moderation_notes
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Booking $booking
 * @property-read User|null $moderator
 */
class Review extends Model
{
    /** @use HasFactory<ReviewFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'service_type' => ServiceType::class,
            'source' => ReviewSource::class,
            'status' => ReviewStatus::class,
            'rating' => 'integer',
            'is_featured' => 'boolean',
            'verified_trip' => 'boolean',
            'invited_at' => 'datetime',
            'submitted_at' => 'datetime',
            'moderated_at' => 'datetime',
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
    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderated_by_user_id');
    }

    public function isPublished(): bool
    {
        return $this->status === ReviewStatus::Published;
    }

    /**
     * @param  Builder<Review>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', ReviewStatus::Published->value);
    }

    /**
     * @param  Builder<Review>  $query
     */
    public function scopeFeatured(Builder $query): void
    {
        $this->scopePublished($query);
        $query->where('is_featured', true)->latest('submitted_at');
    }
}
