<?php

namespace App\Listeners\Tenant;

use App\Events\Tenant\StockTransferInTransit;
use App\Jobs\Tenant\SendNotificationJob;
use App\Listeners\Tenant\Concerns\ResolvesNotificationRecipients;
use App\Models\Tenant\StockTransfer;
use App\Models\Tenant\TenantConfiguration;
use Illuminate\Support\Facades\Log;

class NotifyStockTransferInTransitListener
{
    use ResolvesNotificationRecipients;

    /**
     * Handle the event.
     */
    public function handle(StockTransferInTransit $event): void
    {
        try {
            $transfer = $event->transfer->load(['fromStore', 'toStore']);

            $channel = TenantConfiguration::get('stock_transfer_notification_channel', 'email');

            // The receiving store should know stock is on its way.
            $usersToNotify = $this->getManagerAndOwners($transfer->to_store_id);

            if ($usersToNotify->isEmpty()) {
                Log::warning('No users to notify for stock transfer in transit', [
                    'transfer_id' => $transfer->id,
                    'to_store_id' => $transfer->to_store_id,
                ]);

                return;
            }

            $message = $this->prepareMessage($transfer);

            foreach ($usersToNotify as $user) {
                SendNotificationJob::dispatch(
                    channel: $channel,
                    recipient: $user->email,
                    message: $message,
                    metadata: [
                        'transfer_id' => $transfer->id,
                        'to_store_id' => $transfer->to_store_id,
                        'user_id' => $user->id,
                    ]
                );
            }

            Log::info('Stock transfer in-transit notifications dispatched', [
                'transfer_id' => $transfer->id,
                'users_notified' => $usersToNotify->count(),
                'channel' => $channel,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send stock transfer in-transit notifications', [
                'transfer_id' => $event->transfer->id ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Prepare notification message
     */
    private function prepareMessage(StockTransfer $transfer): array
    {
        $fromStore = $transfer->fromStore->name ?? 'Unknown';
        $toStore = $transfer->toStore->name ?? 'Unknown';

        $subject = "Stock Transfer In Transit: {$transfer->transfer_number}";

        $body = "A stock transfer is on its way to your store:\n\n";
        $body .= "Transfer: {$transfer->transfer_number}\n";
        $body .= "From: {$fromStore}\n";
        $body .= "To: {$toStore}\n";

        if ($transfer->expected_arrival_date) {
            $body .= 'Expected Arrival: '.$transfer->expected_arrival_date->format('Y-m-d')."\n";
        }

        return [
            'subject' => $subject,
            'body' => $body,
        ];
    }
}
