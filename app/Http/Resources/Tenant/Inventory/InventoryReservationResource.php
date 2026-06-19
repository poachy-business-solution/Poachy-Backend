<?php

namespace App\Http\Resources\Tenant\Inventory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryReservationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $inventory = $this->inventory;

        return [
            'id' => $this->id,
            'inventory' => [
                'id' => $inventory->id,
                'store' => [
                    'id' => $inventory->store->id,
                    'name' => $inventory->store->name,
                    'code' => $inventory->store->code,
                ],
                'product' => [
                    'id' => $inventory->product->id,
                    'name' => $inventory->product->name,
                    'sku' => $inventory->product->sku,
                ],
                'variant' => $this->when($inventory->productVariant, [
                    'id' => $inventory->productVariant?->id,
                    'variant_name' => $inventory->productVariant?->variant_name,
                    'sku' => $inventory->productVariant?->sku,
                ]),
                'base_uom' => [
                    'id' => $inventory->product->baseUom->id,
                    'code' => $inventory->product->baseUom->code,
                    'name' => $inventory->product->baseUom->name,
                ],
            ],
            'quantity_reserved' => (float) $this->quantity_reserved,
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
            ],
            'reserved_until' => $this->reserved_until->toISOString(),
            'is_expired' => $this->is_expired,
            'is_active' => $this->is_active,
            'can_be_cancelled' => $this->can_be_cancelled,
            'remaining_minutes' => $this->remaining_minutes,
            'reference' => $this->when($this->reference_type, [
                'type' => class_basename($this->reference_type),
                'id' => $this->reference_id,
            ]),
            'cancelled_by' => $this->when($this->cancelled_by, [
                'id' => $this->cancelledBy?->id,
                'name' => $this->cancelledBy?->name,
            ]),
            'cancellation_reason' => $this->when($this->cancellation_reason, $this->cancellation_reason),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
