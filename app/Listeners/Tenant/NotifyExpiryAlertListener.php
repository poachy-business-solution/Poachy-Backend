<?php

namespace App\Listeners\Tenant;

use App\Events\Tenant\ExpiryAlertCreated;
use App\Jobs\Tenant\SendNotificationJob;
use App\Models\Tenant\TenantConfiguration;
use App\Models\Tenant\User;
use Illuminate\Support\Facades\Log;

class NotifyExpiryAlertListener
{
    /**
     * Handle the event.
     */
    public function handle(ExpiryAlertCreated $event): void
    {
        try {
            $alert = $event->alert->load(['batch.product', 'batch.store']);

            // Get notification channel from configuration
            $channel = TenantConfiguration::get('expiry_alerts_notification_channel', 'email');

            // Get users to notify (store manager and owner)
            $usersToNotify = $this->getUsersToNotify($alert->batch->store_id);

            if ($usersToNotify->isEmpty()) {
                Log::warning('No users to notify for expiry alert', [
                    'alert_id' => $alert->id,
                    'batch_id' => $alert->batch_id,
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
                        'alert_level' => $alert->alert_level->value,
                        'batch_id' => $alert->batch_id,
                        'user_id' => $user->id,
                    ]
                );
            }

            Log::info('Expiry alert notifications dispatched', [
                'alert_id' => $alert->id,
                'users_notified' => $usersToNotify->count(),
                'channel' => $channel,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send expiry alert notifications', [
                'alert_id' => $event->alert->id ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get users to notify (store manager and owner)
     */
    private function getUsersToNotify(int $storeId): \Illuminate\Support\Collection
    {
        // Get store manager
        $store = \App\Models\Tenant\Store::find($storeId);
        $users = collect();

        if ($store && $store->manager_id) {
            $manager = User::find($store->manager_id);
            if ($manager) {
                $users->push($manager);
            }
        }

        // Get owner (users with owner role)
        $owners = User::role('owner')->get();
        $users = $users->merge($owners);

        return $users->unique('id');
    }

    /**
     * Prepare notification message
     */
    private function prepareMessage(\App\Models\Tenant\ExpiryAlert $alert): array
    {
        $batch = $alert->batch;
        $productName = $alert->display_name;
        $storeName = $batch->store->name;
        $alertLevel = $alert->alert_level->label();
        $daysUntilExpiry = $alert->days_until_expiry;
        $expiryDate = $batch->expiry_date->format('Y-m-d');
        $remainingQty = number_format($batch->quantity_remaining_in_base_uom, 2);
        $baseUom = $batch->product->baseUom->code ?? 'units';

        $subject = "Expiry Alert: {$alertLevel} - {$productName}";

        $body = "A product batch expiry alert has been triggered:\n\n";
        $body .= "Product: {$productName}\n";
        $body .= "Batch Number: {$batch->batch_number}\n";
        $body .= "Store: {$storeName}\n";
        $body .= "Alert Level: {$alertLevel}\n";
        $body .= "Expiry Date: {$expiryDate}\n";
        $body .= "Days Until Expiry: {$daysUntilExpiry} days\n";
        $body .= "Remaining Quantity: {$remainingQty} {$baseUom}\n\n";

        if ($alert->alert_level->value === 'expired') {
            $body .= "This batch has EXPIRED and should be removed from inventory immediately.";
        } elseif ($alert->alert_level->value === 'urgent') {
            $body .= "This batch is expiring soon. Consider discounting or returning to supplier.";
        } else {
            $body .= "This batch will expire soon. Plan accordingly to minimize waste.";
        }

        return [
            'subject' => $subject,
            'body' => $body,
        ];
    }
}
