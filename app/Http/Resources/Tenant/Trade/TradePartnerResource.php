<?php

namespace App\Http\Resources\Tenant\Trade;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class TradePartnerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'note' => $this->note,
            'is_active' => $this->is_active,
            'balance' => $this->balance(),
            'open_lines' => TradeLineResource::collection($this->whenLoaded('openLines')),
            'last_trade_at' => $this->events_max_occurred_at
                ? Carbon::parse($this->events_max_occurred_at)->toISOString()
                : null,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
