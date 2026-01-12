<?php

namespace App\Listeners\Tenant;

use App\Events\Tenant\WasteApprovalRequested;
use App\Jobs\Tenant\SendNotificationJob;
use App\Models\Tenant\TenantConfiguration;
use App\Models\Tenant\User;
use Illuminate\Support\Facades\Log;

class NotifyWasteApprovalListener
{
    /**
     * Handle the event.
     */
    public function handle(WasteApprovalRequested $event): void
    {
        try {
            $waste = $event->waste->load(['product', 'store', 'reportedBy']);

            // Get notification channel from configuration
            $channel = TenantConfiguration::get('waste_notification_channel', 'email');

            // Get users to notify (store manager and owner)
            $usersToNotify = $this->getUsersToNotify($waste->store_id);

            if ($usersToNotify->isEmpty()) {
                Log::warning('No users to notify for waste approval', [
                    'waste_id' => $waste->id,
                    'store_id' => $waste->store_id,
                ]);
                return;
            }

            // Prepare notification message
            $message = $this->prepareMessage($waste);

            // Send notifications
            foreach ($usersToNotify as $user) {
                SendNotificationJob::dispatch(
                    channel: $channel,
                    recipient: $user->email,
                    message: $message,
                    metadata: [
                        'waste_id' => $waste->id,
                        'waste_type' => $waste->waste_type->value,
                        'store_id' => $waste->store_id,
                        'user_id' => $user->id,
                    ]
                );
            }

            Log::info('Waste approval notifications dispatched', [
                'waste_id' => $waste->id,
                'users_notified' => $usersToNotify->count(),
                'channel' => $channel,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send waste approval notifications', [
                'waste_id' => $event->waste->id ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get users to notify (store manager and owner who can approve)
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
    private function prepareMessage(\App\Models\Tenant\InventoryWaste $waste): array
    {
        $productName = $waste->product->name;
        $storeName = $waste->store->name;
        $wasteType = $waste->waste_type->label();
        $quantity = number_format($waste->quantity_wasted, 2);
        $baseUom = $waste->product->baseUom->code ?? 'units';
        $totalLoss = number_format($waste->total_loss, 2);
        $reportedBy = $waste->reportedBy->name;
        $wasteDate = $waste->waste_date->format('Y-m-d');

        $subject = "Waste Approval Required: {$productName}";

        $body = "A waste record requires your approval:\n\n";
        $body .= "Product: {$productName}\n";
        $body .= "Store: {$storeName}\n";
        $body .= "Waste Type: {$wasteType}\n";
        $body .= "Quantity Wasted: {$quantity} {$baseUom}\n";
        $body .= "Financial Loss: KES {$totalLoss}\n";
        $body .= "Waste Date: {$wasteDate}\n";
        $body .= "Reported By: {$reportedBy}\n";

        if ($waste->reason) {
            $body .= "Reason: {$waste->reason}\n";
        }

        $body .= "\nPlease review and approve or reject this waste record.";

        return [
            'subject' => $subject,
            'body' => $body,
        ];
    }
}
