<?php

namespace App\Http\Resources\Central\Marketplace;

use App\Helpers\BusinessHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MarketplaceOrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                     => $this->id,
            'marketplace_product_id' => $this->marketplace_product_id,
            'product_name'           => $this->product_name,
            'product_sku'            => $this->product_sku,
            'variant_name'           => $this->variant_name,
            'uom'                    => [
                'code' => $this->uom_code,
                'name' => $this->uom_name,
            ],
            'quantity'               => (float) $this->quantity,
            'quantity_in_base_uom'   => (float) $this->quantity_in_base_uom,
            'unit_price'             => (float) $this->unit_price,
            'tax_rate'               => (float) $this->tax_rate,
            'tax_amount'             => (float) $this->tax_amount,
            'discount_amount'        => (float) $this->discount_amount,
            'subtotal'               => (float) $this->subtotal,
            'fulfillment_status'     => $this->fulfillment_status->value,
            'seller'                 => $this->when(
                $this->relationLoaded('marketplaceProduct') && $this->marketplaceProduct,
                fn () => BusinessHelper::getBusinessSummary($this->marketplaceProduct->tenant_id)
            ),
            'product'                => $this->when(
                $this->relationLoaded('marketplaceProduct') && $this->marketplaceProduct,
                fn () => [
                    'id'            => $this->marketplaceProduct->id,
                    'slug'          => $this->marketplaceProduct->slug,
                    'primary_image' => $this->marketplaceProduct->primary_image,
                ]
            ),
        ];
    }
}
