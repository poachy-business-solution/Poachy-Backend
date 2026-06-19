<?php

namespace App\Http\Resources\Tenant\Product;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ProductListResource extends JsonResource
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

            // Core Details
            'name' => $this->name,
            'slug' => $this->slug,
            'sku' => $this->sku,

            // Category & Brand (minimal)
            'category' => [
                'id' => $this->category?->id,
                'name' => $this->category?->name,
                'slug' => $this->category?->slug,
            ],
            'brand' => $this->when($this->brand, [
                'id' => $this->brand?->id,
                'name' => $this->brand?->name,
                'slug' => $this->brand?->slug,
            ]),

            // Pricing
            'base_selling_price' => $this->base_selling_price,
            'online_price' => $this->online_price,
            'formatted_base_price' => 'KES ' . number_format($this->base_selling_price, 2),
            'formatted_online_price' => $this->online_price
                ? 'KES ' . number_format($this->online_price, 2)
                : null,

            // Status
            'stock_status' => $this->stock_status,
            'stock_status_label' => $this->stock_status?->label(),

            // Flags
            'is_active' => $this->is_active,
            'is_featured' => $this->is_featured,
            'is_available_online' => $this->is_available_online,

            // Image
            'primary_image' => $this->getPrimaryImageUrl(),

            // Timestamp
            'created_at' => $this->created_at?->toISOString(),
        ];
    }

    protected function getPrimaryImageUrl(): ?string
    {
        if (!$this->primary_image) {
            return null;
        }

        return Storage::disk('public')->url($this->primary_image);
    }
}
