<?php

namespace App\Listeners\Tenant;

use App\Events\Tenant\BudgetAlertTriggered;
use App\Jobs\Tenant\SendNotificationJob;
use App\Listeners\Tenant\Concerns\ResolvesNotificationRecipients;
use App\Models\Tenant\Budget;
use App\Models\Tenant\TenantConfiguration;
use Illuminate\Support\Facades\Log;

class NotifyBudgetAlertListener
{
    use ResolvesNotificationRecipients;

    /**
     * Handle the event.
     */
    public function handle(BudgetAlertTriggered $event): void
    {
        try {
            $budget = $event->budget->load(['category', 'store']);

            $channel = TenantConfiguration::get('budget_notification_channel', 'email');

            $usersToNotify = $this->getManagerAndOwners($budget->store_id);

            if ($usersToNotify->isEmpty()) {
                Log::warning('No users to notify for budget alert', [
                    'budget_id' => $budget->id,
                    'store_id' => $budget->store_id,
                ]);

                return;
            }

            $message = $this->prepareMessage($budget);

            foreach ($usersToNotify as $user) {
                SendNotificationJob::dispatch(
                    channel: $channel,
                    recipient: $user->email,
                    message: $message,
                    metadata: [
                        'budget_id' => $budget->id,
                        'store_id' => $budget->store_id,
                        'user_id' => $user->id,
                    ]
                );
            }

            Log::info('Budget alert notifications dispatched', [
                'budget_id' => $budget->id,
                'users_notified' => $usersToNotify->count(),
                'channel' => $channel,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send budget alert notifications', [
                'budget_id' => $event->budget->id ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Prepare notification message
     */
    private function prepareMessage(Budget $budget): array
    {
        $budgetName = $budget->budget_name;
        $storeName = $budget->store->name ?? 'Company-wide';
        $percentageSpent = number_format($budget->percentage_spent, 1);
        $threshold = number_format($budget->alert_threshold_percentage, 1);
        $spent = number_format($budget->spent_amount, 2);
        $total = number_format($budget->budget_amount, 2);

        $subject = "Budget Alert: {$budgetName}";

        $body = "A budget has crossed its alert threshold:\n\n";
        $body .= "Budget: {$budgetName}\n";
        $body .= "Store: {$storeName}\n";
        $body .= "Spent: KES {$spent} of KES {$total} ({$percentageSpent}%)\n";
        $body .= "Alert Threshold: {$threshold}%\n";

        return [
            'subject' => $subject,
            'body' => $body,
        ];
    }
}
