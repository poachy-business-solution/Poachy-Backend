<?php

namespace App\Listeners\Central\Marketplace;

use App\Events\Central\Marketplace\CartItemAdded;
use App\Events\Central\Marketplace\CartItemRemoved;
use App\Jobs\Central\Analytics\TrackAnalyticsEventJob;
use App\Jobs\Central\Analytics\UpdateProductViewConversionJob;

class TrackCartAnalytics
{
    public function handleCartItemAdded(CartItemAdded $event): void
    {
        TrackAnalyticsEventJob::dispatch([
            'event_type'             => 'add_to_cart',
            'customer_id'            => $event->customer?->id,
            'session_id'             => $event->sessionId,
            'marketplace_product_id' => $event->item->marketplace_product_id,
            'event_properties'       => [
                'quantity'   => $event->item->quantity,
                'unit_price' => $event->item->unit_price,
            ],
        ]);

        UpdateProductViewConversionJob::dispatch(
            sessionId: $event->sessionId,
            productId: $event->item->marketplace_product_id,
            action: 'added_to_cart'
        );
    }

    public function handleCartItemRemoved(CartItemRemoved $event): void
    {
        TrackAnalyticsEventJob::dispatch([
            'event_type'             => 'remove_from_cart',
            'customer_id'            => $event->customer?->id,
            'session_id'             => $event->sessionId,
            'marketplace_product_id' => $event->removedProductId,
        ]);
    }
}
