<?php

namespace App\Http\Resources\Tenant\Inventory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryResource extends JsonResource
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
            'store' => [
                'id' => $this->store->id,
                'name' => $this->store->name,
                'code' => $this->store->code,
                'is_main_store' => $this->store->is_main_store,
            ],
            'product' => [
                'id' => $this->product->id,
                'name' => $this->product->name,
                'slug' => $this->product->slug,
                'sku' => $this->product->sku,
                'base_selling_price' => (float) $this->product->base_selling_price,
                'primary_image' => $this->product->primary_image,
                'category' => $this->when($this->product->category, [
                    'id' => $this->product->category?->id,
                    'name' => $this->product->category?->name,
                    'slug' => $this->product->category?->slug,
                ]),
                'brand' => $this->when($this->product->brand, [
                    'id' => $this->product->brand?->id,
                    'name' => $this->product->brand?->name,
                    'slug' => $this->product->brand?->slug,
                ]),
            ],
            'variant' => $this->when($this->productVariant, [
                'id' => $this->productVariant?->id,
                'variant_name' => $this->productVariant?->variant_name,
                'sku' => $this->productVariant?->sku,
                'variant_price' => $this->productVariant ? (float) $this->productVariant->variant_price : null,
            ]),
            'base_uom' => [
                'id' => $this->product->baseUom->id,
                'code' => $this->product->baseUom->code,
                'name' => $this->product->baseUom->name,
                'type' => $this->product->baseUom->type,
            ],
            'quantities' => [
                'on_hand' => (float) $this->quantity_on_hand,
                'reserved' => (float) $this->quantity_reserved,
                'available' => (float) $this->quantity_available,
                'damaged' => (float) $this->quantity_damaged,
            ],
            'stock_status' => $this->stock_status,
            'is_low_stock' => $this->is_low_stock,
            'is_out_of_stock' => $this->is_out_of_stock,
            'reorder_level' => (float) $this->getEffectiveReorderLevel(),
            'last_restock_date' => $this->last_restock_date?->format('Y-m-d'),
            'last_stock_take_date' => $this->last_stock_take_date?->format('Y-m-d'),
            'last_restocked_by' => $this->when($this->lastRestocker, [
                'id' => $this->lastRestocker?->id,
                'name' => $this->lastRestocker?->name,
            ]),
            'display_name' => $this->display_name,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
