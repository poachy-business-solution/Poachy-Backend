<?php

namespace App\Http\Resources\Tenant\Inventory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryMovementResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'store' => [
                'id' => $this->store->id,
                'name' => $this->store->name,
                'code' => $this->store->code,
            ],
            'product' => [
                'id' => $this->product->id,
                'name' => $this->product->name,
                'slug' => $this->product->slug,
                'sku' => $this->product->sku,
            ],
            'variant' => $this->when($this->productVariant, [
                'id' => $this->productVariant?->id,
                'variant_name' => $this->productVariant?->variant_name,
                'sku' => $this->productVariant?->sku,
            ]),
            'movement_type' => [
                'value' => $this->movement_type->value,
                'label' => $this->movement_type->label(),
            ],
            'uom' => [
                'id' => $this->uom->id,
                'code' => $this->uom->code,
                'name' => $this->uom->name,
                'type' => $this->uom->type,
            ],
            'base_uom' => [
                'code' => $this->product->baseUom->code,
                'name' => $this->product->baseUom->name,
            ],
            'quantity' => (float) $this->quantity,
            'quantity_in_base_uom' => (float) $this->quantity_in_base_uom,
            'direction' => $this->direction,
            'is_positive' => $this->is_positive,
            'formatted_quantity' => $this->formatted_quantity,
            'formatted_base_quantity' => $this->formatted_base_quantity,
            'cost' => $this->when($this->hasCostData(), [
                'unit_cost' => (float) $this->unit_cost,
                'unit_cost_in_base_uom' => (float) $this->unit_cost_in_base_uom,
                'total_cost' => (float) $this->total_cost,
            ]),
            'reference' => $this->when($this->reference_type, [
                'type' => class_basename($this->reference_type),
                'id' => $this->reference_id,
            ]),
            'balance_after' => (float) $this->balance_after,
            'notes' => $this->notes,
            'created_by' => $this->when($this->createdByUser, [
                'id' => $this->createdByUser?->id,
                'name' => $this->createdByUser?->name,
                'email' => $this->createdByUser?->email,
            ]),
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
