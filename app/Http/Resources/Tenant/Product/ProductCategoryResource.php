<?php

namespace App\Http\Resources\Tenant\Product;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductCategoryResource extends JsonResource
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
            'parent_id' => $this->parent_id,
            'display_order' => $this->display_order,
            'is_active' => $this->is_active,

            // Relationships (loaded conditionally)
            'parent' => $this->when(
                $this->relationLoaded('parent'),
                fn() => $this->parent ? new ProductCategoryResource($this->parent) : null
            ),

            'children' => $this->when(
                $this->relationLoaded('children'),
                fn() => ProductCategoryResource::collection($this->children)
            ),

            'products' => $this->when(
                $this->relationLoaded('products'),
                fn() => ProductMinimalResource::collection($this->products)
            ),

            // Computed attributes
            'product_count' => $this->when(
                $this->relationLoaded('products'),
                fn() => $this->products->count()
            ),

            'has_children' => $this->when(
                $this->relationLoaded('children'),
                fn() => $this->children->isNotEmpty()
            ),

            'is_root' => is_null($this->parent_id),

            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
