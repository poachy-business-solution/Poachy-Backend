<?php

namespace App\Http\Resources\Tenant\Sales;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaleItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'product' => $this->when(
                $this->product,
                function () {
                    return [
                        'id' => $this->product->id,
                        'name' => $this->product->name,
                        'sku' => $this->product->sku,
                    ];
                }
            ),

            'variant' => $this->when(
                $this->productVariant,
                function () {
                    return [
                        'id' => $this->productVariant->id,
                        'name' => $this->productVariant->variant_name,
                        'sku' => $this->productVariant->sku,
                    ];
                }
            ),

            'bundle' => $this->when(
                $this->bundle,
                function () {
                    return [
                        'id' => $this->bundle->id,
                        'name' => $this->bundle->bundle_name,
                        'sku' => $this->bundle->bundle_sku,
                    ];
                }
            ),

            'display_name' => $this->display_name,

            'uom' => [
                'id' => $this->uom->id,
                'code' => $this->uom->code,
                'name' => $this->uom->name,
            ],

            'quantity' => (float) $this->quantity,
            'quantity_in_base_uom' => (float) $this->quantity_in_base_uom,

            'unit_price' => (float) $this->unit_price,
            'unit_cost' => $this->when(
                $request->input('include_cost', false),
                (float) $this->unit_cost
            ),

            'line_total_before_tax' => (float) $this->line_total_before_tax,
            'discount_amount' => (float) $this->discount_amount,
            'tax_amount' => (float) $this->tax_amount,
            'subtotal' => (float) $this->subtotal,

            'effective_unit_price' => (float) $this->effective_unit_price,

            'profit' => $this->when(
                $request->input('include_profit', false),
                (float) $this->profit
            ),
        ];
    }
}
