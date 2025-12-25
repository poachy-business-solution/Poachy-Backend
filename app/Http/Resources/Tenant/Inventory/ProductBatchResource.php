<?php

namespace App\Http\Resources\Tenant\Inventory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductBatchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'batch_number' => $this->batch_number,
            'store' => $this->when($this->store, [
                'id' => $this->store?->id,
                'name' => $this->store?->name,
                'code' => $this->store?->code,
            ]),
            'product' => [
                'id' => $this->product->id,
                'name' => $this->product->name,
                'sku' => $this->product->sku,
            ],
            'variant' => $this->when($this->productVariant, [
                'id' => $this->productVariant?->id,
                'variant_name' => $this->productVariant?->variant_name,
                'sku' => $this->productVariant?->sku,
            ]),
            'purchase_order' => $this->when($this->purchaseOrder, [
                'id' => $this->purchaseOrder?->id,
                'po_number' => $this->purchaseOrder?->po_number,
                'order_date' => $this->purchaseOrder?->order_date?->format('Y-m-d'),
            ]),
            'supplier' => $this->when($this->supplier, [
                'id' => $this->supplier?->id,
                'name' => $this->supplier?->name,
            ]),
            'purchase_uom' => $this->when($this->purchaseUom, [
                'id' => $this->purchaseUom?->id,
                'code' => $this->purchaseUom?->code,
                'name' => $this->purchaseUom?->name,
            ]),
            'quantities' => [
                'received_in_purchase_uom' => (float) $this->quantity_received_in_purchase_uom,
                'received_in_base_uom' => (float) $this->quantity_received_in_base_uom,
                'remaining_in_base_uom' => (float) $this->quantity_remaining_in_base_uom,
                'depleted' => (float) $this->quantity_depleted,
                'percentage_remaining' => round($this->percentage_remaining, 2),
            ],
            'costs' => [
                'cost_per_purchase_uom' => (float) $this->cost_per_purchase_uom,
                'cost_per_base_uom' => (float) $this->cost_per_base_uom,
                'total_cost' => (float) $this->total_cost,
                'remaining_value' => (float) $this->remaining_value,
            ],
            'dates' => [
                'manufacture_date' => $this->manufacture_date?->format('Y-m-d'),
                'expiry_date' => $this->expiry_date?->format('Y-m-d'),
                'days_until_expiry' => $this->days_until_expiry,
            ],
            'status' => [
                'is_available' => $this->is_available,
                'is_depleted' => $this->is_depleted,
                'is_expired' => $this->is_expired,
                'is_expiring_soon' => $this->is_expiring_soon,
            ],
            'notes' => $this->notes,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
