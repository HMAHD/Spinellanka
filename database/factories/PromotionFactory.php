<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Booking\Enums\PromotionDiscountType;
use App\Domain\Booking\Enums\PromotionScope;
use App\Domain\Booking\Enums\PromotionStatus;
use App\Domain\Booking\ServiceType;
use App\Models\Promotion;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Promotion>
 */
class PromotionFactory extends Factory
{
    protected $model = Promotion::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true).' Special',
            'code' => 'PROMO-'.strtoupper(fake()->bothify('##??')),
            'explanation' => 'Enjoy an exclusive online discount when booking advance island transport.',
            'scope' => PromotionScope::Global,
            'service_type' => null,
            'deal_id' => null,
            'discount_type' => PromotionDiscountType::PercentageBasisPoints,
            'discount_value' => 1000, // 10.00%
            'currency' => 'USD',
            'status' => PromotionStatus::Active,
            'priority' => 10,
            'starts_at' => Carbon::now('Asia/Colombo')->subDay(),
            'ends_at' => Carbon::now('Asia/Colombo')->addMonth(),
            'created_by_user_id' => null,
            'updated_by_user_id' => null,
        ];
    }

    public function fixedAmount(int $amountMinorUnits = 1500, string $currency = 'USD'): static
    {
        return $this->state(fn (array $attributes) => [
            'discount_type' => PromotionDiscountType::FixedAmount,
            'discount_value' => $amountMinorUnits,
            'currency' => $currency,
        ]);
    }

    public function forService(ServiceType $serviceType): static
    {
        return $this->state(fn (array $attributes) => [
            'scope' => PromotionScope::Service,
            'service_type' => $serviceType,
        ]);
    }
}
