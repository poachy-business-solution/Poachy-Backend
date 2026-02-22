<?php

namespace App\Listeners\Central\Marketplace;

use App\Events\Central\Marketplace\CheckoutCompleted;
use App\Jobs\Central\Analytics\CreateCheckoutSessionJob;
use App\Jobs\Central\Analytics\TrackAnalyticsEventJob;
use App\Jobs\Central\Analytics\UpdateSearchConversionsJob;

class TrackCheckoutAnalytics
{
    public function handle(CheckoutCompleted $event): void
    {
        CreateCheckoutSessionJob::dispatch(
            cartId: $event->cart->id,
            customerId: $event->customer->id,
            orderId: $event->orders->first()->id,
            sessionId: $event->sessionId,
        );

        foreach ($event->orders as $order) {
            TrackAnalyticsEventJob::dispatch([
                'event_type'       => 'purchase',
                'customer_id'      => $event->customer->id,
                'session_id'       => $event->sessionId,
                'tenant_id'        => $order->tenant_id,
                'event_properties' => [
                    'order_id'     => $order->id,
                    'order_number' => $order->order_number,
                    'amount'       => $order->total_amount,
                ],
            ]);
        }

        UpdateSearchConversionsJob::dispatch(
            sessionId: $event->sessionId,
            cartItems: $event->cart->items,
        );
    }
}
