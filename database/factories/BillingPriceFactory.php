<?php

namespace Database\Factories;

use App\Models\BillingPrice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BillingPrice>
 */
class BillingPriceFactory extends Factory
{
    protected $model = BillingPrice::class;

    public function definition(): array
    {
        return [
            'effective_from' => '2026-01-01',
            'first_branch_day' => 100,
            'extra_branch_day' => 50,
            'first_branch_cap' => 2500,
            'extra_branch_cap' => 1250,
            'trial_days' => 30,
            'min_topup' => 10,
            'low_balance_days' => 3,
            'grace_days' => 1,
        ];
    }
}
