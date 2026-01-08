<?php

namespace App\Events\Tenant;

use App\Models\Tenant\Customer;
use App\Models\Tenant\CustomerCreditTransaction;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CreditSaleCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Customer $customer,
        public CustomerCreditTransaction $transaction
    ) {}

    /**
     * Get the data to broadcast (if using broadcasting)
     */
    public function broadcastWith(): array
    {
        return [
            'customer_id' => $this->customer->id,
            'customer_name' => $this->customer->name,
            'credit_amount' => $this->transaction->amount,
            'balance_after' => $this->transaction->balance_after,
            'credit_limit' => $this->customer->credit_limit,
            'available_credit' => $this->customer->available_credit,
        ];
    }
}
