<?php

namespace App\Http\Resources\Tenant\Trade;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TradeLineResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'store_id' => $this->store_id,
            'direction' => $this->direction->value,
            'product_id' => $this->product_id,
            'product_name' => $this->whenLoaded('product', fn () => $this->product?->name),
            'quantity' => (float) $this->quantity,
            'quantity_returned' => (float) $this->quantity_returned,
            'outstanding_quantity' => $this->outstandingQuantity(),
            'unit_price' => (float) $this->unit_price,
            'unit_cost' => $this->unit_cost !== null ? (float) $this->unit_cost : null,
            'amount_paid' => (float) $this->amount_paid,
            'outstanding_amount' => $this->outstandingAmount(),
            'status' => $this->status,
            'opened_at' => $this->opened_at?->toISOString(),
            'days_open' => $this->opened_at ? (int) $this->opened_at->diffInDays(now()) : null,
            'settled_at' => $this->settled_at?->toISOString(),
            'note' => $this->note,
        ];
    }
}
