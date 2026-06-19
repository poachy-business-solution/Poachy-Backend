<?php

namespace App\Events\Tenant;

use App\Models\Tenant\Customer;
use App\Models\Tenant\LoyaltyTransaction;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LoyaltyPointsEarned
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Customer $customer,
        public LoyaltyTransaction $transaction
    ) {}

    /**
     * Get the data to broadcast (if using broadcasting)
     */
    public function broadcastWith(): array
    {
        return [
            'customer_id' => $this->customer->id,
            'customer_name' => $this->customer->name,
            'points_earned' => $this->transaction->points,
            'balance_after' => $this->transaction->balance_after,
            'expires_at' => $this->transaction->expires_at?->toISOString(),
        ];
    }
}
