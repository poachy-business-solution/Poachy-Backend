<?php

namespace Database\Factories;

use App\Enums\Central\BillingStatus;
use App\Models\BillingAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BillingAccount>
 */
class BillingAccountFactory extends Factory
{
    protected $model = BillingAccount::class;

    public function definition(): array
    {
        return [
            'tenant_id' => fake()->uuid(),
            'balance' => 0,
            'status' => BillingStatus::Active,
            'trial_ends_at' => null,
        ];
    }

    public function trial(int $days = 30): static
    {
        return $this->state(['status' => BillingStatus::Trial, 'trial_ends_at' => now()->addDays($days)]);
    }

    public function withBalance(float $balance): static
    {
        return $this->state(['balance' => $balance]);
    }
}
