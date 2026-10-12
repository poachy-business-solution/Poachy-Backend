<?php

namespace Database\Factories;

use App\Models\PaymentLink;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PaymentLink>
 */
class PaymentLinkFactory extends Factory
{
    protected $model = PaymentLink::class;

    public function definition(): array
    {
        return [
            'tenant_id' => fake()->uuid(),
            'token_hash' => PaymentLink::hashToken(Str::random(40)),
            'amount' => null,
            'purpose' => 'topup',
            'expires_at' => now()->addDays(7),
        ];
    }
}
