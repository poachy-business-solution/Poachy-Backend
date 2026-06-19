<?php

namespace App\Events\Central\Marketplace;

use App\Models\MarketplaceOrder;
use App\Models\MarketplaceOrderPayment;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentCompleted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public MarketplaceOrder $order,
        public MarketplaceOrderPayment $payment,
    ) {}
}
