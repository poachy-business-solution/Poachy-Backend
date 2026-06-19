<?php

namespace App\Http\Resources\Tenant\Inventory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockAlertResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'alert_type' => $this->alert_type->value,
            'alert_type_label' => $this->alert_type->label(),
            'current_quantity' => $this->current_quantity,
            'threshold_quantity' => $this->threshold_quantity,
            'is_resolved' => $this->is_resolved,
            'resolved_at' => $this->resolved_at?->toISOString(),
            'notes' => $this->notes,
            'severity' => $this->severity,
            'age_in_days' => $this->age_in_days,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),

            // Relationships
            'store' => [
                'id' => $this->store->id,
                'name' => $this->store->name,
                'code' => $this->store->code,
            ],
            'product' => [
                'id' => $this->product->id,
                'name' => $this->product->name,
                'sku' => $this->product->sku,
                'base_uom' => $this->product->baseUom->code ?? 'units',
                'reorder_level' => $this->product->reorder_level,
                'primary_image' => $this->product->primary_image,
            ],
            'product_variant' => $this->when($this->productVariant, [
                'id' => $this->productVariant?->id,
                'variant_name' => $this->productVariant?->variant_name,
                'sku' => $this->productVariant?->sku,
            ]),
            'resolved_by' => $this->when($this->resolvedBy, [
                'id' => $this->resolvedBy?->id,
                'name' => $this->resolvedBy?->name,
            ]),
        ];
    }
}
