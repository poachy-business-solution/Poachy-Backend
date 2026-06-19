<?php

namespace App\Listeners\Tenant;

use App\Events\Tenant\StockAlertCreated;
use App\Jobs\Tenant\SendNotificationJob;
use App\Models\Tenant\TenantConfiguration;
use App\Models\Tenant\User;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;

class NotifyStockAlertListener
{
    /**
     * Handle the event.
     */
    public function handle(StockAlertCreated $event): void
    {
        try {
            $alert = $event->alert->load(['product', 'store']);

            // Get notification channel from configuration
            $channel = TenantConfiguration::get('stock_alerts_notification_channel', 'email');

            // Get users to notify (store manager and owner)
            $usersToNotify = $this->getUsersToNotify($alert->store_id);

            if ($usersToNotify->isEmpty()) {
                Log::warning('No users to notify for stock alert', [
                    'alert_id' => $alert->id,
                    'store_id' => $alert->store_id,
                ]);
                return;
            }

            // Prepare notification message
            $message = $this->prepareMessage($alert);

            // Send notifications
            foreach ($usersToNotify as $user) {
                SendNotificationJob::dispatch(
                    channel: $channel,
                    recipient: $user->email,
                    message: $message,
                    metadata: [
                        'alert_id' => $alert->id,
                        'alert_type' => $alert->alert_type->value,
                        'store_id' => $alert->store_id,
                        'product_id' => $alert->product_id,
                        'user_id' => $user->id,
                    ]
                );
            }

            // Mark users as notified
            $alert->markAsNotified($usersToNotify->pluck('id')->toArray());

            Log::info('Stock alert notifications dispatched', [
                'alert_id' => $alert->id,
                'users_notified' => $usersToNotify->count(),
                'channel' => $channel,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send stock alert notifications', [
                'alert_id' => $event->alert->id ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get users to notify (store manager and owner)
     */
    private function getUsersToNotify(int $storeId): \Illuminate\Database\Eloquent\Collection
    {
        $userIds = collect();

        // Get store manager
        $store = \App\Models\Tenant\Store::find($storeId);

        if ($store && $store->manager_id) {
            $userIds->push($store->manager_id);
        }

        // Get owner IDs
        $ownerIds = User::role('owner')->pluck('id');
        $userIds = $userIds->merge($ownerIds)->unique();

        // Return Eloquent Collection by querying with all IDs
        return User::whereIn('id', $userIds)->get();
    }

    /**
     * Prepare notification message
     */
    private function prepareMessage(\App\Models\Tenant\StockAlert $alert): array
    {
        $productName = $alert->display_name;
        $storeName = $alert->store->name;
        $alertType = $alert->alert_type->label();
        $currentQty = number_format($alert->current_quantity, 2);
        $baseUom = $alert->product->baseUom->code ?? 'units';

        $subject = "Stock Alert: {$alertType} - {$productName}";

        $body = "A stock alert has been triggered:\n\n";
        $body .= "Product: {$productName}\n";
        $body .= "Store: {$storeName}\n";
        $body .= "Alert Type: {$alertType}\n";
        $body .= "Current Stock: {$currentQty} {$baseUom}\n";

        if ($alert->threshold_quantity) {
            $threshold = number_format($alert->threshold_quantity, 2);
            $body .= "Reorder Level: {$threshold} {$baseUom}\n";
        }

        $body .= "\nPlease take appropriate action to replenish stock.";

        return [
            'subject' => $subject,
            'body' => $body,
        ];
    }
}
