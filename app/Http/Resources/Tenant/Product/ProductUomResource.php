<?php

namespace App\Http\Resources\Tenant\Product;

use App\Http\Resources\Tenant\Uom\UnitOfMeasureResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductUomResource extends JsonResource
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

            // UOM details
            'uom' => new UnitOfMeasureResource($this->whenLoaded('uom')),
            'uom_id' => $this->uom_id,

            // Configuration flags
            'is_base_uom' => $this->is_base_uom,
            'is_purchase_uom' => $this->is_purchase_uom,
            'is_sales_uom' => $this->is_sales_uom,
            'is_inventory_uom' => $this->is_inventory_uom,

            // Conversion
            'conversion_to_base' => $this->conversion_to_base,
            'conversion_description' => $this->conversion_description,

            // Display name
            'display_name' => $this->display_name,

            // Usage descriptions
            'can_purchase' => $this->is_purchase_uom,
            'can_sell' => $this->is_sales_uom,
            'can_track_inventory' => $this->is_inventory_uom,

            // Metadata
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
