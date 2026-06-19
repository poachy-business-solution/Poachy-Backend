<?php

namespace App\Http\Resources\Tenant\Product;

use App\Http\Resources\Tenant\Tax\TaxRateResource;
use App\Http\Resources\Tenant\Uom\UnitOfMeasureResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ProductBundleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'bundle_name' => $this->bundle_name,
            'bundle_sku' => $this->bundle_sku,
            'description' => $this->description,
            'images' => $this->formatImages($this->images ?? []),
            'image_count' => $this->image_count,
            // 'primary_image' => $this->primary_image,

            // UOM & Tax
            'base_uom' => new UnitOfMeasureResource($this->whenLoaded('baseUom')),
            'tax_rate' => new TaxRateResource($this->whenLoaded('taxRate')),

            // Pricing
            'bundle_price' => $this->bundle_price,
            'calculated_individual_price' => $this->calculated_individual_price,
            'discount_amount' => $this->discount_amount,
            'savings_percentage' => $this->savings_percentage,
            'formatted_bundle_price' => $this->formatted_bundle_price,
            'formatted_discount' => $this->formatted_discount,

            // Online
            'is_available_online' => $this->is_available_online,
            'online_price' => $this->online_price,
            'formatted_online_price' => $this->formatted_online_price,
            'online_description' => $this->online_description,

            // Items
            'items' => ProductBundleItemResource::collection($this->whenLoaded('items')),
            'items_count' => $this->items->count() ?? 0,

            // Status
            'is_active' => $this->is_active,
            'has_minimum_items' => $this->hasMinimumItems(),
            'all_items_active' => $this->allItemsActive(),
            'is_available_for_sale' => $this->isAvailableForSale(),

            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    protected function formatImages(array $images): array
    {
        return array_map(function ($path) {
            return [
                'path' => $path,
                'url' => Storage::disk('public')->url($path),
                'filename' => basename($path)
            ];
        }, $images);
    }
}
