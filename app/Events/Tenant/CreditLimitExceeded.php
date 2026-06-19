<?php

namespace App\Events\Tenant;

use App\Models\Tenant\Customer;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CreditLimitExceeded
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Customer $customer,
        public float $attemptedAmount
    ) {}

    /**
     * Get the data to broadcast (if using broadcasting)
     */
    public function broadcastWith(): array
    {
        return [
            'customer_id' => $this->customer->id,
            'customer_name' => $this->customer->name,
            'attempted_amount' => $this->attemptedAmount,
            'credit_limit' => $this->customer->credit_limit,
            'current_debt' => $this->customer->current_debt,
            'available_credit' => $this->customer->available_credit,
            'would_be_debt' => $this->customer->current_debt + $this->attemptedAmount,
        ];
    }
}
