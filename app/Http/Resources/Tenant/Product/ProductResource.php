<?php

namespace App\Http\Resources\Tenant\Product;

use App\Http\Resources\Tenant\Supplier\SupplierResource;
use App\Http\Resources\Tenant\Tax\TaxRateResource;
use App\Http\Resources\Tenant\Uom\UnitOfMeasureResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ProductResource extends JsonResource
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
            'uuid' => $this->uuid,

            // Basic Information
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'sku' => $this->sku,

            // Categorization
            'category' => new ProductCategoryResource($this->whenLoaded('category')),
            'brand' => new ProductBrandResource($this->whenLoaded('brand')),
            'supplier' => new SupplierResource($this->whenLoaded('supplier')),

            // Type & Status
            'product_type' => $this->product_type,
            'stock_status' => $this->stock_status,
            'stock_status_label' => $this->stock_status?->label(),

            // Pricing
            'base_selling_price' => $this->base_selling_price,
            'formatted_base_price' => 'KES ' . number_format($this->base_selling_price, 2),

            // Tax & UOM
            'tax_rate' => new TaxRateResource($this->whenLoaded('taxRate')),
            'base_uom' => new UnitOfMeasureResource($this->whenLoaded('baseUom')),

            // Inventory & Logistics
            'reorder_level' => $this->reorder_level,
            'shelf_life_days' => $this->shelf_life_days,
            'is_weighed' => $this->is_weighed,
            'requires_batch_tracking' => $this->requires_batch_tracking,
            'requires_serial_tracking' => $this->requires_serial_tracking,

            // Media
            'primary_image' => $this->getPrimaryImageUrl(),
            'secondary_images' => $this->getSecondaryImageUrls(),
            'image_count' => count($this->secondary_images ?? []),

            // Visibility Flags
            'is_active' => $this->is_active,
            'is_featured' => $this->is_featured,

            // Online Marketplace
            'is_available_online' => $this->is_available_online,
            'online_price' => $this->online_price,
            'formatted_online_price' => $this->online_price
                ? 'KES ' . number_format($this->online_price, 2)
                : null,
            'online_description' => $this->online_description,

            // Additional
            'notes' => $this->notes,

            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    protected function getPrimaryImageUrl(): ?string
    {
        if (!$this->primary_image) {
            return null;
        }

        return Storage::disk('public')->url($this->primary_image);
    }

    protected function getSecondaryImageUrls(): array
    {
        if (empty($this->secondary_images)) {
            return [];
        }

        return array_map(
            fn($imagePath) => Storage::disk('public')->url($imagePath),
            $this->secondary_images
        );
    }
}
