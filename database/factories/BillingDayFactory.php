<?php

namespace Database\Factories;

use App\Models\BillingDay;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BillingDay>
 */
class BillingDayFactory extends Factory
{
    protected $model = BillingDay::class;

    public function definition(): array
    {
        return [
            'tenant_id' => fake()->uuid(),
            'store_id' => 1,
            'date' => now('Africa/Nairobi')->subDay()->toDateString(),
            'amount' => 100,
            'is_main_branch' => true,
        ];
    }
}
