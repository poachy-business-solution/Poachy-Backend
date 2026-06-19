<?php

namespace App\Jobs\Central\Analytics;

use App\Models\CheckoutSession;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CreateCheckoutSessionJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $cartId,
        public int $customerId,
        public int $orderId,
        public string $sessionId,
    ) {
        $this->onQueue('sync-low');
    }

    public function handle(): void
    {
        CheckoutSession::create([
            'cart_id'             => $this->cartId,
            'customer_id'         => $this->customerId,
            'current_step'        => 'completed',
            'is_completed'        => true,
            'completed_at'        => now(),
            'completed_order_id'  => $this->orderId,
        ]);
    }
}
