<?php

namespace App\Http\Resources\Tenant\Trade;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TradeEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'store_id' => $this->store_id,
            'trade_line_id' => $this->trade_line_id,
            'type' => $this->type->value,
            'label' => $this->type->label(),
            'product_id' => $this->product_id,
            'product_name' => $this->whenLoaded('product', fn () => $this->product?->name),
            'quantity' => $this->quantity !== null ? (float) $this->quantity : null,
            'amount' => (float) $this->amount,
            'balance_effect' => round($this->type->balanceSign() * (float) $this->amount, 2),
            'payment_method' => $this->payment_method,
            'payment_reference' => $this->payment_reference,
            'recorded_by' => $this->whenLoaded('recorder', fn () => ['id' => $this->recorder->id, 'name' => $this->recorder->name]),
            'occurred_at' => $this->occurred_at?->toISOString(),
            'note' => $this->note,
        ];
    }
}
