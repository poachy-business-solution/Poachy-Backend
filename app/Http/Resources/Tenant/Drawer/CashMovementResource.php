<?php

namespace App\Http\Resources\Tenant\Drawer;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CashMovementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'store_id' => $this->store_id,
            'shift_assignment_id' => $this->shift_assignment_id,
            'direction' => $this->direction,
            'reason' => $this->reason?->value,
            'reason_label' => $this->reason?->label(),
            'amount' => (float) $this->amount,
            'signed_amount' => $this->signedAmount(),
            'note' => $this->note,
            'occurred_at' => $this->occurred_at?->toISOString(),
            'recorded_by' => $this->whenLoaded('recorder', fn () => ['id' => $this->recorder->id, 'name' => $this->recorder->name]),
            'store' => $this->whenLoaded('store', fn () => ['id' => $this->store->id, 'name' => $this->store->name]),
            'supplier' => $this->whenLoaded('supplier', fn () => $this->supplier ? ['id' => $this->supplier->id, 'name' => $this->supplier->name] : null),
        ];
    }
}
