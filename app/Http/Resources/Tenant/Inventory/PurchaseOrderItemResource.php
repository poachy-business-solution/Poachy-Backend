<?php

namespace App\Http\Resources\Tenant\Inventory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseOrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
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
            'uom' => [
                'id' => $this->uom->id,
                'code' => $this->uom->code,
                'name' => $this->uom->name,
            ],
            'quantities' => [
                'ordered' => (float) $this->quantity_ordered,
                'received' => (float) $this->quantity_received,
                'pending' => (float) $this->quantity_pending,
                'ordered_in_base_uom' => (float) $this->quantity_ordered_in_base_uom,
                'received_in_base_uom' => (float) $this->quantity_received_in_base_uom,
                'receive_progress' => round($this->receive_progress, 2),
            ],
            'costs' => [
                'unit_cost' => (float) $this->unit_cost,
                'unit_cost_in_base_uom' => (float) $this->unit_cost_in_base_uom,
                'subtotal' => (float) $this->subtotal,
                'tax_amount' => (float) $this->tax_amount,
                'total_cost' => (float) $this->total_cost,
            ],
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
                'is_pending' => $this->is_pending,
                'is_fully_received' => $this->is_fully_received,
                'is_partially_received' => $this->is_partially_received,
                'is_not_received' => $this->is_not_received,
                'can_receive' => $this->status->canReceive(),
            ],
            'notes' => $this->notes,
        ];
    }
}
