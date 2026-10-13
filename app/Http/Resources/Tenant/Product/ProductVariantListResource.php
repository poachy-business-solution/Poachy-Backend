<?php

namespace App\Http\Resources\Tenant\Product;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductVariantListResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            // Product (minimal)
            'product_id' => $this->product_id,
            'product_name' => $this->product?->name,
            'product_sku' => $this->product?->sku,
            'product_available_online' => $this->product?->is_available_online,

            // Variant basics
            'variant_name' => $this->variant_name,
            'sku' => $this->sku,
            'display_name' => $this->display_name,
            'attributes' => $this->attributes ?? [],

            // UOM (minimal)
            'uom_display' => $this->getUomDisplay(),
            'quantity_in_base_uom' => $this->quantity_in_base_uom,

            // Pricing
            'variant_price' => $this->variant_price === null ? null : (float) $this->variant_price,
            'computed_price' => $this->computed_price,
            'reorder_level' => $this->reorder_level === null ? null : (float) $this->reorder_level,
            // Buying price: only for staff who manage products (never cashiers).
            'cost_price' => $this->when($request->user()?->can('manage-products') ?? false, fn () => $this->cost_price === null ? null : (float) $this->cost_price),
            'formatted_price' => 'KES '.number_format($this->computed_price, 2),
            'online_price' => $this->online_price,
            'formatted_online_price' => $this->online_price
                ? 'KES '.number_format($this->online_price, 2)
                : null,
            'computed_online_price' => $this->computed_online_price,
            'formatted_computed_online_price' => 'KES '.number_format($this->computed_online_price, 2),
            'is_available_online' => $this->isAvailableOnline(),

            // Status
            'stock_status' => $this->stock_status?->value,
            'stock_status_label' => $this->stock_status?->label(),
            'is_active' => $this->is_active,

            // Timestamp
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
