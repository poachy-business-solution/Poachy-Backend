<?php

namespace App\Events\Central\Marketplace;

use App\Models\MarketplaceCustomer;
use App\Models\ShoppingCart;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class CheckoutCompleted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public ShoppingCart $cart,
        public Collection $orders,  // MarketplaceOrder[]
        public MarketplaceCustomer $customer,
        public string $sessionId,
    ) {}
}
