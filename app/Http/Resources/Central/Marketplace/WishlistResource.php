<?php

namespace App\Http\Resources\Central\Marketplace;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WishlistResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $priceChange = $this->hasPriceChanged() ? $this->getPriceChange() : null;

        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'marketplace_product_id' => $this->marketplace_product_id,
            'notes' => $this->notes,
            'desired_quantity' => $this->desired_quantity,
            'price_at_addition' => $this->price_at_addition ? (float) $this->price_at_addition : null,
            'product' => $this->when(
                $this->relationLoaded('marketplaceProduct') && $this->marketplaceProduct,
                fn() => [
                    'id' => $this->marketplaceProduct->id,
                    'name' => $this->marketplaceProduct->name,
                    'slug' => $this->marketplaceProduct->slug,
                    'sku' => $this->marketplaceProduct->sku,
                    'primary_image' => $this->marketplaceProduct->primary_image,
                    'online_price' => (float) $this->marketplaceProduct->online_price,
                    'in_stock' => $this->marketplaceProduct->isInStock(),
                    'available_qty' => (float) $this->marketplaceProduct->available_quantity,
                    'tenant_id' => $this->marketplaceProduct->tenant_id,
                ]
            ),
            'seller' => $this->when(
                $this->relationLoaded('marketplaceProduct') && $this->marketplaceProduct,
                fn() => \App\Helpers\BusinessHelper::getBusinessSummary($this->marketplaceProduct->tenant_id)
            ),
            'is_available' => $this->isProductAvailable(),
            'price_changed' => $this->hasPriceChanged(),
            'price_difference' => $priceChange ? $priceChange['difference'] : null,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
