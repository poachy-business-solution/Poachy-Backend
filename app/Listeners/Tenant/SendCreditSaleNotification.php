<?php

namespace App\Listeners\Tenant;

use App\Events\Tenant\CreditSaleCreated;
use App\Jobs\Tenant\SendNotificationJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendCreditSaleNotification implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * The name of the queue the job should be sent to.
     */
    public string $queue = 'sync-normal';

    /**
     * Handle the event.
     */
    public function handle(CreditSaleCreated $event): void
    {
        $customer = $event->customer;
        $transaction = $event->transaction;

        // Send SMS notification
        if ($customer->phone) {
            $message = "Dear {$customer->name}, a credit sale of KES " . number_format($transaction->amount, 2) . " has been recorded. " .
                "Your total debt is now KES " . number_format($transaction->balance_after, 2) . ". " .
                "Available credit: KES " . number_format($customer->available_credit, 2);

            SendNotificationJob::dispatch(
                channel: 'sms',
                recipient: $customer->phone,
                message: $message,
                metadata: [
                    'customer_id' => $customer->id,
                    'transaction_id' => $transaction->id,
                    'notification_type' => 'credit_sale',
                ]
            )->onQueue('sync-normal');
        }

        // Send email notification with more details
        if ($customer->email) {
            SendNotificationJob::dispatch(
                channel: 'email',
                recipient: $customer->email,
                message: [
                    'subject' => 'Credit Sale Notification',
                    'body' => $this->generateEmailBody($customer, $transaction),
                ],
                metadata: [
                    'customer_id' => $customer->id,
                    'transaction_id' => $transaction->id,
                    'notification_type' => 'credit_sale',
                ]
            )->onQueue('sync-normal');
        }
    }

    /**
     * Generate email body
     */
    protected function generateEmailBody($customer, $transaction): string
    {
        return "Dear {$customer->name},\n\n" .
            "This is to confirm a credit sale transaction:\n" .
            "Amount: KES " . number_format($transaction->amount, 2) . "\n" .
            "Date: " . $transaction->created_at->format('d M Y H:i') . "\n\n" .
            "Credit Summary:\n" .
            "Total Debt: KES " . number_format($transaction->balance_after, 2) . "\n" .
            "Credit Limit: KES " . number_format($customer->credit_limit, 2) . "\n" .
            "Available Credit: KES " . number_format($customer->available_credit, 2) . "\n\n" .
            "Please ensure timely payment.\n\n" .
            "Thank you for your business!";
    }
}
