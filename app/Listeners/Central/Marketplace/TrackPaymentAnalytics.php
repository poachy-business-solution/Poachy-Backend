<?php

namespace App\Listeners\Central\Marketplace;

use App\Events\Central\Marketplace\PaymentAttempted;
use App\Events\Central\Marketplace\PaymentCompleted;
use App\Events\Central\Marketplace\PaymentFailed;
use App\Jobs\Central\Analytics\TrackAnalyticsEventJob;

class TrackPaymentAnalytics
{
    public function handlePaymentAttempted(PaymentAttempted $event): void
    {
        TrackAnalyticsEventJob::dispatch([
            'event_type'       => 'payment_attempted',
            'customer_id'      => $event->order->customer_id,
            'event_properties' => [
                'order_id'       => $event->order->id,
                'payment_method' => $event->paymentMethod,
                'amount'         => $event->payment->amount,
            ],
        ]);
    }

    public function handlePaymentCompleted(PaymentCompleted $event): void
    {
        TrackAnalyticsEventJob::dispatch([
            'event_type'       => 'payment_completed',
            'customer_id'      => $event->order->customer_id,
            'event_properties' => [
                'order_id'         => $event->order->id,
                'payment_method'   => $event->payment->payment_method,
                'transaction_ref'  => $event->payment->transaction_reference,
                'amount'           => $event->payment->amount,
            ],
        ]);
    }

    public function handlePaymentFailed(PaymentFailed $event): void
    {
        TrackAnalyticsEventJob::dispatch([
            'event_type'       => 'payment_failed',
            'customer_id'      => $event->order->customer_id,
            'event_properties' => [
                'order_id'       => $event->order->id,
                'payment_method' => $event->payment->payment_method,
                'failure_reason' => $event->failureReason,
            ],
        ]);
    }
}
