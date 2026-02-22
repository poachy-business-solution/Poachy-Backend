<?php

namespace App\Events\Central\Marketplace;

use App\Models\MarketplaceCustomer;
use App\Models\ShoppingCart;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CartItemRemoved
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public ShoppingCart $cart,
        public int $removedProductId,
        public ?MarketplaceCustomer $customer,
        public string $sessionId,
    ) {}
}
