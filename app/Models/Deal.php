<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Booking\Enums\DealStatus;
use App\Domain\Booking\ServiceType;
use Carbon\Carbon;
use Database\Factories\DealFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $slug
 * @property ServiceType $service_type
 * @property string $title
 * @property string $summary
 * @property string $description_markdown
 * @property array<string, mixed> $preset_payload
 * @property string|null $image_path
 * @property string|null $image_alt
 * @property string|null $image_source
 * @property string|null $image_approver
 * @property Carbon|null $image_approved_at
 * @property DealStatus $status
 * @property bool $is_featured
 * @property int $priority
 * @property Carbon|null $published_at
 * @property Carbon|null $expires_at
 * @property int|null $promotion_id
 * @property int|null $created_by_user_id
 * @property int|null $updated_by_user_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Promotion|null $promotion
 * @property-read User|null $createdBy
 * @property-read User|null $updatedBy
 */
class Deal extends Model
{
    /** @use HasFactory<DealFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'service_type' => ServiceType::class,
            'preset_payload' => 'array',
            'status' => DealStatus::class,
            'is_featured' => 'boolean',
            'priority' => 'integer',
            'image_approved_at' => 'datetime',
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Promotion, $this>
     */
    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    public function isMediaApproved(): bool
    {
        return ! empty($this->image_path)
            && ! empty($this->image_approver)
            && $this->image_approved_at !== null;
    }

    public function isPublished(): bool
    {
        $now = Carbon::now('Asia/Colombo');

        return $this->status === DealStatus::Published
            && ($this->published_at === null || $this->published_at->lte($now))
            && ($this->expires_at === null || $this->expires_at->gte($now));
    }

    /**
     * @param  Builder<Deal>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $now = Carbon::now('Asia/Colombo');

        $query->where('status', DealStatus::Published->value)
            ->where(function (Builder $q) use ($now): void {
                $q->whereNull('published_at')->orWhere('published_at', '<=', $now);
            })
            ->where(function (Builder $q) use ($now): void {
                $q->whereNull('expires_at')->orWhere('expires_at', '>=', $now);
            });
    }

    /**
     * @param  Builder<Deal>  $query
     */
    public function scopeFeatured(Builder $query): void
    {
        $this->scopePublished($query);
        $query->where('is_featured', true)->orderByDesc('priority');
    }

    /**
     * @param  Builder<Deal>  $query
     */
    public function scopeForService(Builder $query, ServiceType|string $service): void
    {
        $val = $service instanceof ServiceType ? $service->value : $service;
        $query->where('service_type', $val);
    }
}
