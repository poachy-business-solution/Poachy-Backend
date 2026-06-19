<?php

namespace App\Http\Resources\Tenant\Inventory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'po_number' => $this->po_number,
            'supplier' => [
                'id' => $this->supplier->id,
                'name' => $this->supplier->name,
                'contact_person' => $this->supplier->contact_person,
                'phone' => $this->supplier->phone,
            ],
            'store' => [
                'id' => $this->store->id,
                'name' => $this->store->name,
                'code' => $this->store->code,
            ],
            'dates' => [
                'order_date' => $this->order_date->format('Y-m-d'),
                'expected_delivery_date' => $this->expected_delivery_date?->format('Y-m-d'),
            ],
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
                'can_be_edited' => $this->status->canBeEdited(),
                'can_be_sent' => $this->status->canBeSent(),
                'can_be_received' => $this->status->canBeReceived(),
                'can_be_cancelled' => $this->status->canBeCancelled(),
            ],
            'amounts' => [
                'subtotal' => (float) $this->subtotal,
                'tax_amount' => (float) $this->tax_amount,
                'shipping_cost' => (float) $this->shipping_cost,
                'total_amount' => (float) $this->total_amount,
            ],
            'payment' => [
                'status' => [
                    'value' => $this->payment_status->value,
                    'label' => $this->payment_status->label(),
                    'can_accept_payment' => $this->payment_status->canAcceptPayment(),
                ],
                'amount_paid' => (float) $this->amount_paid,
                'amount_due' => (float) $this->amount_due,
                'payment_progress' => round($this->payment_progress, 2),
            ],
            'items' => PurchaseOrderItemResource::collection($this->whenLoaded('items')),
            'created_by' => $this->when($this->createdBy, [
                'id' => $this->createdBy?->id,
                'name' => $this->createdBy?->name,
            ]),
            'approved_by' => $this->when($this->approvedBy, [
                'id' => $this->approvedBy?->id,
                'name' => $this->approvedBy?->name,
            ]),
            'approved_at' => $this->approved_at?->toISOString(),
            'notes' => $this->notes,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
