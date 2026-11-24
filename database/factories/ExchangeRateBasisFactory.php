<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ExchangeRateBasis;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExchangeRateBasis>
 */
class ExchangeRateBasisFactory extends Factory
{
    protected $model = ExchangeRateBasis::class;

    public function definition(): array
    {
        return [
            'rate' => '315.5000',
            'source_note' => 'Central Bank of Sri Lanka indicative rate',
            'effective_at' => Carbon::now('Asia/Colombo'),
            'created_by' => User::factory(),
        ];
    }
}
