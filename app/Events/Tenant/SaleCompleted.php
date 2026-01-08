<?php

namespace App\Events\Tenant;

use App\Models\Tenant\Sale;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SaleCompleted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Sale $sale
    ) {}

    /**
     * Get the data to broadcast (if using broadcasting)
     */
    public function broadcastWith(): array
    {
        return [
            'sale_id' => $this->sale->id,
            'sale_number' => $this->sale->sale_number,
            'store_id' => $this->sale->store_id,
            'customer_id' => $this->sale->customer_id,
            'total_amount' => $this->sale->total_amount,
            'payment_status' => $this->sale->payment_status->value,
            'payment_method' => $this->sale->payment_method->value,
            'items_count' => $this->sale->items()->count(),
        ];
    }
}
