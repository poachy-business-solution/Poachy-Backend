<?php

namespace App\Http\Resources\Tenant\Inventory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductSerialResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'serial_number' => $this->serial_number,
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
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
                'is_available' => $this->is_available,
                'is_sold' => $this->is_sold,
            ],
            'cost' => (float) $this->cost,
            'sale_item_id' => $this->sale_item_id,
            'marketplace_sale_item_id' => $this->marketplace_sale_item_id,
            'notes' => $this->notes,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
