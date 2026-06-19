<?php

namespace App\Http\Resources\Tenant\Inventory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockTransferItemResource extends JsonResource
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
            'quantity_requested' => (float) $this->quantity_requested,
            'quantity_sent' => (float) $this->quantity_sent,
            'quantity_received' => (float) $this->quantity_received,
            'quantity_requested_in_base_uom' => (float) $this->quantity_requested_in_base_uom,
            'quantity_sent_in_base_uom' => (float) $this->quantity_sent_in_base_uom,
            'quantity_received_in_base_uom' => (float) $this->quantity_received_in_base_uom,
            'has_discrepancy' => $this->quantity_received != $this->quantity_sent && $this->quantity_received > 0,
            'notes' => $this->notes,
        ];
    }
}
