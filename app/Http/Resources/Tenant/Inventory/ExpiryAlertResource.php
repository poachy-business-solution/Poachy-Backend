<?php

namespace App\Http\Resources\Tenant\Inventory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpiryAlertResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'alert_level' => $this->alert_level->value,
            'alert_level_label' => $this->alert_level->label(),
            'alert_date' => $this->alert_date->toDateString(),
            'days_until_expiry' => $this->days_until_expiry,
            'is_resolved' => $this->is_resolved,
            'resolution_action' => $this->resolution_action?->value,
            'resolution_action_label' => $this->resolution_action?->label(),
            'resolved_at' => $this->resolved_at?->toISOString(),
            'notes' => $this->notes,
            'severity' => $this->severity,
            'age_in_days' => $this->age_in_days,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),

            // Batch information
            'batch' => [
                'id' => $this->batch->id,
                'batch_number' => $this->batch->batch_number,
                'quantity_remaining' => $this->batch->quantity_remaining_in_base_uom,
                'expiry_date' => $this->batch->expiry_date?->toDateString(),
                'is_expired' => $this->batch->is_expired,
                'product' => [
                    'id' => $this->batch->product->id,
                    'name' => $this->batch->product->name,
                    'sku' => $this->batch->product->sku,
                    'base_uom' => $this->batch->product->baseUom->code ?? 'units',
                    'primary_image' => $this->batch->product->primary_image,
                ],
                'product_variant' => $this->when($this->batch->productVariant, [
                    'id' => $this->batch->productVariant?->id,
                    'variant_name' => $this->batch->productVariant?->variant_name,
                ]),
                'store' => [
                    'id' => $this->batch->store->id,
                    'name' => $this->batch->store->name,
                    'code' => $this->batch->store->code,
                ],
            ],
            'resolved_by' => $this->when($this->resolvedBy, [
                'id' => $this->resolvedBy?->id,
                'name' => $this->resolvedBy?->name,
            ]),
        ];
    }
}
