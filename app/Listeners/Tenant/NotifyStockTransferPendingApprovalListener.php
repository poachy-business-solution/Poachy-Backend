<?php

namespace App\Listeners\Tenant;

use App\Events\Tenant\StockTransferCreatedPendingApproval;
use App\Jobs\Tenant\SendNotificationJob;
use App\Listeners\Tenant\Concerns\ResolvesNotificationRecipients;
use App\Models\Tenant\StockTransfer;
use App\Models\Tenant\TenantConfiguration;
use Illuminate\Support\Facades\Log;

class NotifyStockTransferPendingApprovalListener
{
    use ResolvesNotificationRecipients;

    /**
     * Handle the event.
     */
    public function handle(StockTransferCreatedPendingApproval $event): void
    {
        try {
            $transfer = $event->transfer->load(['fromStore', 'toStore', 'requester']);

            $channel = TenantConfiguration::get('stock_transfer_notification_channel', 'email');

            // The receiving store approves an incoming transfer.
            $usersToNotify = $this->getManagerAndOwners($transfer->to_store_id);

            if ($usersToNotify->isEmpty()) {
                Log::warning('No users to notify for stock transfer approval', [
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

            Log::info('Stock transfer approval notifications dispatched', [
                'transfer_id' => $transfer->id,
                'users_notified' => $usersToNotify->count(),
                'channel' => $channel,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send stock transfer approval notifications', [
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
        $requestedBy = $transfer->requester->name ?? 'Unknown';

        $subject = "Stock Transfer Approval Required: {$transfer->transfer_number}";

        $body = "A stock transfer requires your approval:\n\n";
        $body .= "Transfer: {$transfer->transfer_number}\n";
        $body .= "From: {$fromStore}\n";
        $body .= "To: {$toStore}\n";
        $body .= "Requested By: {$requestedBy}\n";

        $body .= "\nPlease review and approve or reject this transfer.";

        return [
            'subject' => $subject,
            'body' => $body,
        ];
    }
}
