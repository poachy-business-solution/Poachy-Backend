<?php

namespace App\Http\Resources\Tenant\Product;

use App\Http\Resources\Tenant\Uom\UnitOfMeasureResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductBundleItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'bundle_id' => $this->bundle_id,

            // Product/Variant
            'product_id' => $this->product_id,
            'product' => $this->when(
                $this->relationLoaded('product'),
                fn() => [
                    'id' => $this->product->id,
                    'name' => $this->product->name,
                    'sku' => $this->product->sku,
                ]
            ),
            'product_variant_id' => $this->product_variant_id,
            'variant' => $this->when(
                $this->relationLoaded('variant') && $this->variant,
                fn() => [
                    'id' => $this->variant->id,
                    'variant_name' => $this->variant->variant_name,
                    'sku' => $this->variant->sku,
                ]
            ),

            // Display
            'display_name' => $this->display_name,
            'sku' => $this->sku,
            'is_using_variant' => $this->isUsingVariant(),

            // Quantity & UOM
            'uom' => new UnitOfMeasureResource($this->whenLoaded('uom')),
            'quantity' => $this->quantity,
            'quantity_in_base_uom' => $this->quantity_in_base_uom,
            'uom_display' => $this->uom_display,

            // Pricing
            'item_price' => $this->item_price,
            'total_price' => $this->total_price,
            'formatted_item_price' => 'KES ' . number_format($this->item_price, 2),
            'formatted_total_price' => 'KES ' . number_format($this->total_price, 2),

            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
