<?php

namespace App\Http\Resources\Tenant\Product;

use App\Http\Resources\Tenant\Uom\UnitOfMeasureResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductVariantResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            // Product reference
            'product_id' => $this->product_id,
            'product' => $this->when(
                $this->relationLoaded('product'),
                fn() => [
                    'id' => $this->product->id,
                    'uuid' => $this->product->uuid,
                    'name' => $this->product->name,
                    'slug' => $this->product->slug,
                    'sku' => $this->product->sku,
                    'is_available_online' => $this->product->is_available_online,
                ]
            ),

            // Variant info
            'variant_name' => $this->variant_name,
            'sku' => $this->sku,
            'display_name' => $this->display_name,
            'attributes' => $this->attributes ?? [],

            // UOM details
            'uom' => new UnitOfMeasureResource($this->whenLoaded('uom')),
            'uom_id' => $this->uom_id,
            'uom_quantity' => $this->uom_quantity,
            'quantity_in_base_uom' => $this->quantity_in_base_uom,
            'uom_display' => $this->getUomDisplay(),

            // Pricing
            'base_selling_price_adjustment' => $this->base_selling_price_adjustment,
            'formatted_adjustment' => $this->formatted_adjustment,
            'variant_price' => $this->variant_price,
            'computed_price' => $this->computed_price,
            'formatted_variant_price' => $this->formatted_variant_price,
            'formatted_computed_price' => 'KES ' . number_format($this->computed_price, 2),
            'online_price' => $this->online_price,
            'formatted_online_price' => $this->formatted_online_price,
            'computed_online_price' => $this->computed_online_price,
            'formatted_computed_online_price' => 'KES ' . number_format($this->computed_online_price, 2),
            'is_available_online' => $this->isAvailableOnline(),

            // Inventory & Status
            'stock_status' => $this->stock_status?->value,
            'stock_status_label' => $this->stock_status?->label(),
            'reorder_level' => $this->reorder_level,
            'shelf_life_days' => $this->shelf_life_days,
            'is_active' => $this->is_active,

            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
