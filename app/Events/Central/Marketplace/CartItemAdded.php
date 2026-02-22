<?php

namespace App\Events\Central\Marketplace;

use App\Models\MarketplaceCustomer;
use App\Models\ShoppingCart;
use App\Models\ShoppingCartItem;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CartItemAdded
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public ShoppingCart $cart,
        public ShoppingCartItem $item,
        public ?MarketplaceCustomer $customer,
        public string $sessionId,
    ) {}
}
