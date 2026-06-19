<?php

namespace App\Http\Resources\Tenant\Inventory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryWasteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'waste_type' => $this->waste_type->value,
            'waste_type_label' => $this->waste_type->label(),
            'quantity_wasted' => $this->quantity_wasted,
            'cost_per_base_uom' => $this->cost_per_base_uom,
            'total_loss' => $this->total_loss,
            'waste_date' => $this->waste_date->toDateString(),
            'reason' => $this->reason,
            'approval_status' => $this->approval_status->value,
            'approval_status_label' => $this->approval_status->label(),
            'approved_at' => $this->approved_at?->toISOString(),
            'is_pending' => $this->is_pending,
            'is_approved' => $this->is_approved,
            'is_rejected' => $this->is_rejected,
            'can_be_approved' => $this->can_be_approved,
            'can_be_rejected' => $this->can_be_rejected,
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
                'primary_image' => $this->product->primary_image,
            ],
            'batch' => $this->when($this->batch, [
                'id' => $this->batch?->id,
                'batch_number' => $this->batch?->batch_number,
                'expiry_date' => $this->batch?->expiry_date?->toDateString(),
            ]),
            'reported_by' => [
                'id' => $this->reportedBy->id,
                'name' => $this->reportedBy->name,
                'email' => $this->reportedBy->email,
            ],
            'approved_by' => $this->when($this->approvedBy, [
                'id' => $this->approvedBy?->id,
                'name' => $this->approvedBy?->name,
            ]),
        ];
    }
}
