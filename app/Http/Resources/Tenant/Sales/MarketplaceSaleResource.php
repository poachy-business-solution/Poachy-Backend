<?php

namespace App\Http\Resources\Tenant\Sales;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MarketplaceSaleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sale_number' => $this->sale_number,
            'central_order_id' => $this->central_order_id,
            'store' => $this->when($this->store, [
                'id' => $this->store?->id,
                'name' => $this->store?->name,
            ]),
            'fulfillment_type' => $this->fulfillment_type,
            'fulfillment_status' => [
                'value' => $this->fulfillment_status->value,
                'label' => $this->fulfillment_status->label(),
                'is_terminal' => $this->fulfillment_status->isTerminal(),
            ],
            'payment_status' => $this->payment_status->value,
            'amounts' => [
                'subtotal' => (float) $this->subtotal,
                'tax_amount' => (float) $this->tax_amount,
                'total_amount' => (float) $this->total_amount,
                'amount_paid' => (float) $this->amount_paid,
            ],
            'items_count' => $this->whenLoaded('items', fn () => $this->items->count()),
            'notes' => $this->notes,
            'sale_date' => $this->sale_date?->toISOString(),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
