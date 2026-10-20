<?php

namespace App\Http\Resources\Central\Marketplace;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShoppingCartResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $this->resource->load('items.marketplaceProduct');

        $tenantGroups = $this->items->groupBy(
            fn ($item) => $item->marketplaceProduct->tenant_id ?? 'unknown'
        );

        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'item_count' => $this->getItemCount(),
            'subtotal' => $this->getSubtotal(),
            'tax_amount' => $this->getTaxAmount(),
            'total' => round($this->getSubtotal() + $this->getTaxAmount(), 2),
            'tenant_groups' => $tenantGroups->map(function ($items, $tenantId) {
                return [
                    'tenant_id' => $tenantId,
                    'items' => ShoppingCartItemResource::collection($items),
                    'subtotal' => $items->sum(fn ($item) => $item->getLineTotal()),
                    'tax_amount' => round($items->sum(fn ($item) => $item->getLineTax()), 2),
                ];
            })->values(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
