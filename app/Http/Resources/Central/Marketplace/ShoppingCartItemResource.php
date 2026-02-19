<?php

namespace App\Http\Resources\Central\Marketplace;

use App\Helpers\BusinessHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShoppingCartItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                     => $this->id,
            'marketplace_product_id' => $this->marketplace_product_id,
            'product_name'           => $this->product_name,
            'product_sku'            => $this->product_sku,
            'quantity'               => (float) $this->quantity,
            'uom_code'               => $this->uom_code,
            'unit_price'             => (float) $this->unit_price,
            'current_price'          => (float) $this->current_price,
            'price_changed'          => $this->isPriceChanged(),
            'line_total'             => $this->getLineTotal(),
            'seller'                 => $this->when(
                $this->relationLoaded('marketplaceProduct') && $this->marketplaceProduct,
                fn () => BusinessHelper::getBusinessSummary($this->marketplaceProduct->tenant_id)
            ),
            'product'                => $this->when(
                $this->relationLoaded('marketplaceProduct') && $this->marketplaceProduct,
                fn () => [
                    'id'            => $this->marketplaceProduct->id,
                    'name'          => $this->marketplaceProduct->name,
                    'slug'          => $this->marketplaceProduct->slug,
                    'primary_image' => $this->marketplaceProduct->primary_image,
                    'tenant_id'     => $this->marketplaceProduct->tenant_id,
                    'in_stock'      => $this->marketplaceProduct->isInStock(),
                    'available_qty' => (float) $this->marketplaceProduct->available_quantity,
                ]
            ),
            'added_at'               => $this->added_at,
        ];
    }
}
