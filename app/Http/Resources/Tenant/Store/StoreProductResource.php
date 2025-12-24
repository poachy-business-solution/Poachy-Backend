<?php

namespace App\Http\Resources\Tenant\Store;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoreProductResource extends JsonResource
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

            // Store Information
            'store' => [
                'id' => $this->store->id,
                'name' => $this->store->name,
                'code' => $this->store->code,
                'is_main_store' => $this->store->is_main_store,
            ],

            // Product Information
            'product' => [
                'id' => $this->product->id,
                'name' => $this->product->name,
                'slug' => $this->product->slug,
                'sku' => $this->product->sku,
                'description' => $this->product->description,
                'product_type' => $this->product->product_type,
                'primary_image' => $this->product->primary_image,
                'is_active' => $this->product->is_active,
                'is_available_online' => $this->product->is_available_online,

                // Category
                'category' => $this->when($this->product->category, [
                    'id' => $this->product->category?->id,
                    'name' => $this->product->category?->name,
                    'slug' => $this->product->category?->slug,
                ]),

                // Brand
                'brand' => $this->when($this->product->brand, [
                    'id' => $this->product->brand?->id,
                    'name' => $this->product->brand?->name,
                    'slug' => $this->product->brand?->slug,
                    'logo_url' => $this->product->brand?->logo_url,
                ]),

                // Base UOM
                'base_uom' => [
                    'id' => $this->product->baseUom->id,
                    'code' => $this->product->baseUom->code,
                    'name' => $this->product->baseUom->name,
                    'type' => $this->product->baseUom->type,
                ],
            ],

            // Variant Information (if this is a variant assignment)
            'variant' => $this->when(
                $this->isVariant() && $this->productVariant,
                fn() => [
                    'id' => $this->productVariant?->id,
                    'variant_name' => $this->productVariant?->variant_name,
                    'sku' => $this->productVariant?->sku,
                    'attributes' => $this->productVariant?->attributes ?? [],
                    'uom_quantity' => $this->productVariant ? (float) $this->productVariant->uom_quantity : 0,
                    'quantity_in_base_uom' => $this->productVariant ? (float) $this->productVariant->quantity_in_base_uom : 0,
                ]
            ),

            // Display name (includes variant name if applicable)
            'display_name' => $this->display_name,

            // Assignment type
            'is_base_product' => $this->isBaseProduct(),
            'is_variant' => $this->isVariant(),

            // Pricing Information
            'pricing' => [
                'base_selling_price' => (float) $this->product->base_selling_price,
                'store_selling_price' => $this->store_selling_price
                    ? (float) $this->store_selling_price
                    : null,
                'effective_selling_price' => (float) $this->effective_selling_price,
                'is_price_overridden' => $this->is_price_overridden,
                'currency' => 'KES', // Could be from config
            ],

            // Stock Information
            'stock' => [
                'product_reorder_level' => (float) $this->product->reorder_level,
                'store_min_stock_level' => $this->min_stock_level,
                'effective_min_stock_level' => $this->effective_min_stock_level,
                'is_stock_level_overridden' => $this->is_stock_level_overridden,

                // Current inventory
                'quantity_on_hand' => $this->when(
                    $this->current_inventory,
                    (float) ($this->current_inventory?->quantity_on_hand ?? 0)
                ),
                'quantity_available' => (float) $this->available_quantity,
                'quantity_reserved' => $this->when(
                    $this->current_inventory,
                    (float) ($this->current_inventory?->quantity_reserved ?? 0)
                ),
                'quantity_damaged' => $this->when(
                    $this->current_inventory,
                    (float) ($this->current_inventory?->quantity_damaged ?? 0)
                ),

                // Stock status
                'stock_status' => $this->computed_stock_status,
                'is_low_stock' => $this->is_low_stock,
                'is_out_of_stock' => $this->is_out_of_stock,
                'last_restock_date' => $this->current_inventory?->last_restock_date,
            ],

            // Availability
            'is_available' => $this->is_available,

            // Variant Information
            'has_variants' => $this->hasVariants(),
            'variant_count' => $this->when(
                $this->hasVariants(),
                $this->product->variants()->where('is_active', true)->count()
            ),

            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
