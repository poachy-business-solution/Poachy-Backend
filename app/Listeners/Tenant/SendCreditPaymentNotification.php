<?php

namespace App\Listeners\Tenant;

use App\Events\Tenant\CreditPaymentReceived;
use App\Jobs\Tenant\SendNotificationJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendCreditPaymentNotification implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * The name of the queue the job should be sent to.
     */
    public string $queue = 'sync-normal';

    /**
     * Handle the event.
     */
    public function handle(CreditPaymentReceived $event): void
    {
        $customer = $event->customer;
        $transaction = $event->transaction;
        $paymentAmount = abs($transaction->amount);

        // Send SMS notification
        if ($customer->phone) {
            $message = "Dear {$customer->name}, your payment of KES " . number_format($paymentAmount, 2) . " has been received. " .
                "Remaining debt: KES " . number_format($transaction->balance_after, 2) . ". " .
                "Thank you!";

            SendNotificationJob::dispatch(
                channel: 'sms',
                recipient: $customer->phone,
                message: $message,
                metadata: [
                    'customer_id' => $customer->id,
                    'transaction_id' => $transaction->id,
                    'notification_type' => 'credit_payment',
                ]
            )->onQueue('sync-normal');
        }

        // Send email receipt
        if ($customer->email) {
            SendNotificationJob::dispatch(
                channel: 'email',
                recipient: $customer->email,
                message: [
                    'subject' => 'Payment Received - Thank You!',
                    'body' => $this->generateEmailBody($customer, $transaction, $paymentAmount),
                ],
                metadata: [
                    'customer_id' => $customer->id,
                    'transaction_id' => $transaction->id,
                    'notification_type' => 'credit_payment',
                ]
            )->onQueue('sync-normal');
        }
    }

    /**
     * Generate email body
     */
    protected function generateEmailBody($customer, $transaction, float $paymentAmount): string
    {
        return "Dear {$customer->name},\n\n" .
            "This is to confirm receipt of your payment:\n\n" .
            "Payment Amount: KES " . number_format($paymentAmount, 2) . "\n" .
            "Payment Method: " . ($transaction->payment_method?->label() ?? 'N/A') . "\n" .
            "Date: " . $transaction->created_at->format('d M Y H:i') . "\n" .
            "Reference: " . ($transaction->payment_reference ?? 'N/A') . "\n\n" .
            "Updated Credit Status:\n" .
            "Remaining Debt: KES " . number_format($transaction->balance_after, 2) . "\n" .
            "Available Credit: KES " . number_format($customer->available_credit, 2) . "\n\n" .
            "Thank you for your prompt payment!";
    }
}
