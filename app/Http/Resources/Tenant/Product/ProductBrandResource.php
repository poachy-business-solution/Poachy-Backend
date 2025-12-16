<?php

namespace App\Http\Resources\Tenant\Product;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ProductBrandResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'logo_url' => $this->logo_url ? asset('storage/' . $this->logo_url) : null,
            'is_active' => $this->is_active,
            'is_featured' => $this->is_featured,
            'display_order' => $this->display_order,

            // Relationships (loaded conditionally)
            'products' => $this->when(
                $this->relationLoaded('products'),
                fn() => ProductMinimalResource::collection($this->products)
            ),

            // Computed attributes
            'product_count' => $this->when(
                $this->relationLoaded('products'),
                fn() => $this->products->count()
            ),

            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
