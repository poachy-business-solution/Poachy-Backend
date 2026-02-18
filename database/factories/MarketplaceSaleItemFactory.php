<?php

namespace Database\Factories;

use App\Models\Tenant\MarketplaceSale;
use App\Models\Tenant\MarketplaceSaleItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Tenant\MarketplaceSaleItem>
 */
class MarketplaceSaleItemFactory extends Factory
{
    protected $model = MarketplaceSaleItem::class;

    public function definition(): array
    {
        $quantity = fake()->randomFloat(2, 1, 10);
        $unitPrice = fake()->randomFloat(2, 50, 5000);
        $subtotal = round($quantity * $unitPrice, 2);
        $taxAmount = round($subtotal * 0.16, 2);

        return [
            'marketplace_sale_id'  => MarketplaceSale::factory(),
            'product_id'           => 1,
            'product_variant_id'   => null,
            'bundle_id'            => null,
            'uom_id'               => 1,
            'quantity'             => $quantity,
            'quantity_in_base_uom' => $quantity,
            'unit_price'           => $unitPrice,
            'tax_amount'           => $taxAmount,
            'discount_amount'      => 0,
            'subtotal'             => round($subtotal + $taxAmount, 2),
        ];
    }
}
