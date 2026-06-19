<?php

namespace App\Events\Tenant;

use App\Models\Tenant\Customer;
use App\Models\Tenant\CustomerCreditTransaction;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CreditPaymentReceived
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
            'payment_amount' => abs($this->transaction->amount),
            'payment_method' => $this->transaction->payment_method?->value,
            'balance_after' => $this->transaction->balance_after,
            'remaining_debt' => $this->customer->current_debt,
        ];
    }
}
