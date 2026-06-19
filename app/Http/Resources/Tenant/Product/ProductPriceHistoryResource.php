<?php

namespace App\Http\Resources\Tenant\Product;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductPriceHistoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'product' => [
                'id' => $this->product_id,
                'name' => $this->product->name,
                'sku' => $this->product->sku,
            ],
            'variant' => $this->when($this->product_variant_id, [
                'id' => $this->product_variant_id,
                'name' => $this->productVariant?->variant_name,
                'sku' => $this->productVariant?->sku,
            ]),
            'base_uom' => $this->when($this->base_uom_id, [
                'id' => $this->base_uom_id,
                'code' => $this->baseUom->code,
                'name' => $this->baseUom->name,
            ]),
            'price_change' => [
                'old_price' => $this->old_selling_price ? (float) $this->old_selling_price : null,
                'new_price' => (float) $this->new_selling_price,
                'change_amount' => $this->price_change_amount ? (float) $this->price_change_amount : null,
                'change_percentage' => $this->price_change_percentage ? (float) $this->price_change_percentage : null,
                'is_increase' => $this->price_change_amount > 0,
                'is_decrease' => $this->price_change_amount < 0,
            ],
            'change_info' => [
                'reason' => $this->change_reason,
                'changed_by' => [
                    'id' => $this->changed_by,
                    'name' => $this->changedBy->name ?? 'System',
                ],
                'effective_from' => $this->effective_from->format('Y-m-d H:i:s'),
            ],
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
        ];
    }
}
