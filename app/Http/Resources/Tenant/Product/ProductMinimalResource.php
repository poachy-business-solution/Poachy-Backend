<?php

namespace App\Http\Resources\Tenant\Product;

use App\Http\Resources\Tenant\Concerns\ResolvesTenantPublicAssetUrls;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductMinimalResource extends JsonResource
{
    use ResolvesTenantPublicAssetUrls;

    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'product_type' => $this->product_type,
            'base_selling_price' => $this->base_selling_price
                ? number_format((float) $this->base_selling_price, 2, '.', '')
                : null,
            'stock_status' => $this->stock_status,
            'primary_image' => $this->getPrimaryImageUrl(),
            'is_active' => $this->is_active,
        ];
    }

    protected function getPrimaryImageUrl(): ?string
    {
        if (! $this->primary_image) {
            return null;
        }

        return $this->tenantPublicAssetUrl($this->primary_image);
    }
}
