<?php

namespace App\Listeners\Tenant;

use App\Events\Tenant\BatchExpired;
use App\Jobs\Tenant\SendNotificationJob;
use App\Listeners\Tenant\Concerns\ResolvesNotificationRecipients;
use App\Models\Tenant\ProductBatch;
use App\Models\Tenant\TenantConfiguration;
use Illuminate\Support\Facades\Log;

class NotifyBatchExpiredListener
{
    use ResolvesNotificationRecipients;

    /**
     * Handle the event.
     */
    public function handle(BatchExpired $event): void
    {
        try {
            $batch = $event->batch->load(['product', 'store']);

            $channel = TenantConfiguration::get('expiry_notification_channel', 'email');

            $usersToNotify = $this->getManagerAndOwners($batch->store_id);

            if ($usersToNotify->isEmpty()) {
                Log::warning('No users to notify for batch expiry', [
                    'batch_id' => $batch->id,
                    'store_id' => $batch->store_id,
                ]);

                return;
            }

            $message = $this->prepareMessage($batch);

            foreach ($usersToNotify as $user) {
                SendNotificationJob::dispatch(
                    channel: $channel,
                    recipient: $user->email,
                    message: $message,
                    metadata: [
                        'batch_id' => $batch->id,
                        'store_id' => $batch->store_id,
                        'user_id' => $user->id,
                    ]
                );
            }

            Log::info('Batch expired notifications dispatched', [
                'batch_id' => $batch->id,
                'users_notified' => $usersToNotify->count(),
                'channel' => $channel,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send batch expired notifications', [
                'batch_id' => $event->batch->id ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Prepare notification message
     */
    private function prepareMessage(ProductBatch $batch): array
    {
        $productName = $batch->product->name ?? 'Unknown product';
        $storeName = $batch->store->name ?? 'Unknown store';
        $quantity = number_format($batch->quantity_remaining_in_base_uom, 2);

        $subject = "Product Batch Expired: {$productName}";

        $body = "A product batch has expired:\n\n";
        $body .= "Product: {$productName}\n";
        $body .= "Store: {$storeName}\n";
        $body .= "Batch: {$batch->batch_number}\n";
        $body .= "Quantity Remaining: {$quantity}\n";
        $body .= 'Expiry Date: '.($batch->expiry_date?->toDateString() ?? 'Unknown')."\n";

        $body .= "\nPlease review and dispose of or discount this stock.";

        return [
            'subject' => $subject,
            'body' => $body,
        ];
    }
}
