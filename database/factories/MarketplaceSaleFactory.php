<?php

namespace Database\Factories;

use App\Enums\Tenant\PaymentMethod;
use App\Enums\Tenant\PaymentStatus;
use App\Models\Tenant\MarketplaceSale;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Tenant\MarketplaceSale>
 */
class MarketplaceSaleFactory extends Factory
{
    protected $model = MarketplaceSale::class;

    public function definition(): array
    {
        $subtotal = fake()->randomFloat(2, 100, 10000);
        $taxAmount = round($subtotal * 0.16, 2);
        $totalAmount = round($subtotal + $taxAmount, 2);

        return [
            'central_order_id' => fake()->numberBetween(1, 9999),
            'sale_number'      => 'MKT-ORD-' . now()->year . '-' . str_pad(fake()->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'store_id'         => 1,
            'sale_date'        => now(),
            'subtotal'         => $subtotal,
            'tax_amount'       => $taxAmount,
            'discount_amount'  => 0,
            'total_amount'     => $totalAmount,
            'payment_status'   => PaymentStatus::PAID,
            'amount_paid'      => $totalAmount,
            'amount_due'       => 0,
            'payment_method'   => PaymentMethod::MPESA,
            'payment_reference' => 'MKT-REF-' . fake()->unique()->numerify('########'),
            'fulfillment_type' => 'delivery',
        ];
    }

    public function cashOnDelivery(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_status'   => PaymentStatus::PENDING,
            'payment_method'   => PaymentMethod::CASH,
            'amount_paid'      => 0,
            'amount_due'       => $attributes['total_amount'],
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_status' => PaymentStatus::PAID,
            'amount_paid'    => $attributes['total_amount'],
            'amount_due'     => 0,
        ]);
    }
}
