<?php

namespace App\Listeners\Tenant;

use App\Events\Tenant\LoyaltyPointsEarned;
use App\Jobs\Tenant\SendNotificationJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendLoyaltyEarnedNotification implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * The name of the queue the job should be sent to.
     */
    public string $queue = 'sync-normal';

    /**
     * Handle the event.
     */
    public function handle(LoyaltyPointsEarned $event): void
    {
        $customer = $event->customer;
        $transaction = $event->transaction;

        // Send SMS notification if customer has phone
        if ($customer->phone) {
            $message = "Dear {$customer->name}, you've earned {$transaction->points} loyalty points! " .
                "Your new balance is {$transaction->balance_after} points. " .
                "Thank you for shopping with us!";

            SendNotificationJob::dispatch(
                channel: 'sms',
                recipient: $customer->phone,
                message: $message,
                metadata: [
                    'customer_id' => $customer->id,
                    'transaction_id' => $transaction->id,
                    'notification_type' => 'loyalty_earned',
                ]
            )->onQueue('sync-normal');
        }

        // Send email notification if customer has email
        if ($customer->email) {
            SendNotificationJob::dispatch(
                channel: 'email',
                recipient: $customer->email,
                message: [
                    'subject' => 'Loyalty Points Earned!',
                    'body' => "You've earned {$transaction->points} loyalty points. New balance: {$transaction->balance_after} points.",
                ],
                metadata: [
                    'customer_id' => $customer->id,
                    'transaction_id' => $transaction->id,
                    'notification_type' => 'loyalty_earned',
                ]
            )->onQueue('sync-normal');
        }
    }

    /**
     * Determine whether the listener should be queued.
     */
    public function shouldQueue(LoyaltyPointsEarned $event): bool
    {
        // Only queue if customer wants notifications
        return $event->customer->accepts_marketing ?? true;
    }
}
