<?php

namespace App\Http\Resources\Tenant\Inventory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockTransferResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'transfer_number' => $this->transfer_number,
            'from_store' => [
                'id' => $this->fromStore->id,
                'name' => $this->fromStore->name,
                'code' => $this->fromStore->code,
            ],
            'to_store' => [
                'id' => $this->toStore->id,
                'name' => $this->toStore->name,
                'code' => $this->toStore->code,
            ],
            'status' => $this->status,
            'transfer_date' => $this->transfer_date,
            'expected_arrival_date' => $this->expected_arrival_date,
            'actual_arrival_date' => $this->actual_arrival_date,
            'items' => StockTransferItemResource::collection($this->whenLoaded('items')),
            'requested_by' => $this->when($this->requestedBy, [
                'id' => $this->requestedBy?->id,
                'name' => $this->requestedBy?->name,
            ]),
            'approved_by' => $this->when($this->approvedBy, [
                'id' => $this->approvedBy?->id,
                'name' => $this->approvedBy?->name,
            ]),
            'approved_at' => $this->approved_at?->toISOString(),
            'sent_by' => $this->when($this->sentBy, [
                'id' => $this->sentBy?->id,
                'name' => $this->sentBy?->name,
            ]),
            'sent_at' => $this->sent_at?->toISOString(),
            'received_by' => $this->when($this->receivedBy, [
                'id' => $this->receivedBy?->id,
                'name' => $this->receivedBy?->name,
            ]),
            'received_at' => $this->received_at?->toISOString(),
            'notes' => $this->notes,
            'rejection_reason' => $this->rejection_reason,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
