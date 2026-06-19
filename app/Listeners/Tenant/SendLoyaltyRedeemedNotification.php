<?php

namespace App\Listeners\Tenant;

use App\Events\Tenant\LoyaltyPointsRedeemed;
use App\Jobs\Tenant\SendNotificationJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendLoyaltyRedeemedNotification implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * The name of the queue the job should be sent to.
     */
    public string $queue = 'sync-normal';

    /**
     * Handle the event.
     */
    public function handle(LoyaltyPointsRedeemed $event): void
    {
        $customer = $event->customer;
        $transaction = $event->transaction;
        $pointsRedeemed = abs($transaction->points);

        // Send SMS notification if customer has phone
        if ($customer->phone) {
            $message = "Dear {$customer->name}, you've redeemed {$pointsRedeemed} loyalty points. " .
                "Your remaining balance is {$transaction->balance_after} points. " .
                "Thank you for being a loyal customer!";

            SendNotificationJob::dispatch(
                channel: 'sms',
                recipient: $customer->phone,
                message: $message,
                metadata: [
                    'customer_id' => $customer->id,
                    'transaction_id' => $transaction->id,
                    'notification_type' => 'loyalty_redeemed',
                ]
            )->onQueue('sync-normal');
        }
    }

    /**
     * Determine whether the listener should be queued.
     */
    public function shouldQueue(LoyaltyPointsRedeemed $event): bool
    {
        return $event->customer->accepts_marketing ?? true;
    }
}
