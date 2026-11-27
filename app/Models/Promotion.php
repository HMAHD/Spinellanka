<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Booking\Enums\PromotionDiscountType;
use App\Domain\Booking\Enums\PromotionScope;
use App\Domain\Booking\Enums\PromotionStatus;
use App\Domain\Booking\ServiceType;
use Carbon\Carbon;
use Database\Factories\PromotionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string|null $code
 * @property string $explanation
 * @property PromotionScope $scope
 * @property ServiceType|null $service_type
 * @property int|null $deal_id
 * @property PromotionDiscountType $discount_type
 * @property int $discount_value
 * @property string|null $currency
 * @property PromotionStatus $status
 * @property int $priority
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property int|null $created_by_user_id
 * @property int|null $updated_by_user_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Deal|null $deal
 * @property-read User|null $createdBy
 * @property-read User|null $updatedBy
 */
class Promotion extends Model
{
    /** @use HasFactory<PromotionFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'scope' => PromotionScope::class,
            'service_type' => ServiceType::class,
            'discount_type' => PromotionDiscountType::class,
            'discount_value' => 'integer',
            'status' => PromotionStatus::class,
            'priority' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Deal, $this>
     */
    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }

    /**
     * @return HasMany<Deal, $this>
     */
    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class);
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

    public function isActive(): bool
    {
        $now = Carbon::now('Asia/Colombo');

        return $this->status === PromotionStatus::Active
            && ($this->starts_at === null || $this->starts_at->lte($now))
            && ($this->ends_at === null || $this->ends_at->gte($now));
    }

    public function formattedDiscount(): string
    {
        if ($this->discount_type === PromotionDiscountType::PercentageBasisPoints) {
            return sprintf('%.1f%%', $this->discount_value / 100);
        }

        return sprintf('%s %.2f', $this->currency ?: 'USD', $this->discount_value / 100);
    }

    /**
     * @param  Builder<Promotion>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $now = Carbon::now('Asia/Colombo');

        $query->where('status', PromotionStatus::Active->value)
            ->where(function (Builder $q) use ($now): void {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function (Builder $q) use ($now): void {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
            });
    }
}
