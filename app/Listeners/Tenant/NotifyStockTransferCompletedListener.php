<?php

namespace App\Listeners\Tenant;

use App\Events\Tenant\StockTransferCompleted;
use App\Jobs\Tenant\SendNotificationJob;
use App\Listeners\Tenant\Concerns\ResolvesNotificationRecipients;
use App\Models\Tenant\StockTransfer;
use App\Models\Tenant\TenantConfiguration;
use Illuminate\Support\Facades\Log;

class NotifyStockTransferCompletedListener
{
    use ResolvesNotificationRecipients;

    /**
     * Handle the event.
     */
    public function handle(StockTransferCompleted $event): void
    {
        try {
            $transfer = $event->transfer->load(['fromStore', 'toStore']);

            $channel = TenantConfiguration::get('stock_transfer_notification_channel', 'email');

            $usersToNotify = $this->getSingleUser($transfer->requested_by);

            if ($usersToNotify->isEmpty()) {
                Log::warning('No requester to notify for stock transfer completion', [
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

            Log::info('Stock transfer completed notification dispatched', [
                'transfer_id' => $transfer->id,
                'channel' => $channel,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send stock transfer completed notification', [
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

        $subject = "Stock Transfer Completed: {$transfer->transfer_number}";

        $body = "Your stock transfer has been received and completed:\n\n";
        $body .= "Transfer: {$transfer->transfer_number}\n";
        $body .= "From: {$fromStore}\n";
        $body .= "To: {$toStore}\n";

        return [
            'subject' => $subject,
            'body' => $body,
        ];
    }
}
