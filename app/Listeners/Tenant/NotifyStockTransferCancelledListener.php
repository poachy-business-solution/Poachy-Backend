<?php

namespace App\Listeners\Tenant;

use App\Events\Tenant\StockTransferCancelled;
use App\Jobs\Tenant\SendNotificationJob;
use App\Listeners\Tenant\Concerns\ResolvesNotificationRecipients;
use App\Models\Tenant\StockTransfer;
use App\Models\Tenant\TenantConfiguration;
use Illuminate\Support\Facades\Log;

class NotifyStockTransferCancelledListener
{
    use ResolvesNotificationRecipients;

    /**
     * Handle the event.
     */
    public function handle(StockTransferCancelled $event): void
    {
        try {
            $transfer = $event->transfer->load(['fromStore', 'toStore']);

            $channel = TenantConfiguration::get('stock_transfer_notification_channel', 'email');

            $usersToNotify = $this->getSingleUser($transfer->requested_by);

            if ($usersToNotify->isEmpty()) {
                Log::warning('No requester to notify for stock transfer cancellation', [
                    'transfer_id' => $transfer->id,
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
                        'user_id' => $user->id,
                    ]
                );
            }

            Log::info('Stock transfer cancelled notification dispatched', [
                'transfer_id' => $transfer->id,
                'channel' => $channel,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send stock transfer cancelled notification', [
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

        $subject = "Stock Transfer Cancelled: {$transfer->transfer_number}";

        $body = "A stock transfer has been cancelled:\n\n";
        $body .= "Transfer: {$transfer->transfer_number}\n";
        $body .= "From: {$fromStore}\n";
        $body .= "To: {$toStore}\n";

        if ($transfer->rejection_reason) {
            $body .= "Reason: {$transfer->rejection_reason}\n";
        }

        return [
            'subject' => $subject,
            'body' => $body,
        ];
    }
}
