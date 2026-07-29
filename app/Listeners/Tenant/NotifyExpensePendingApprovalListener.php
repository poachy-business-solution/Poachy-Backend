<?php

namespace App\Listeners\Tenant;

use App\Events\Tenant\ExpenseCreatedPendingApproval;
use App\Jobs\Tenant\SendNotificationJob;
use App\Listeners\Tenant\Concerns\ResolvesNotificationRecipients;
use App\Models\Tenant\Expense;
use App\Models\Tenant\TenantConfiguration;
use Illuminate\Support\Facades\Log;

class NotifyExpensePendingApprovalListener
{
    use ResolvesNotificationRecipients;

    /**
     * Handle the event.
     */
    public function handle(ExpenseCreatedPendingApproval $event): void
    {
        try {
            $expense = $event->expense->load(['category', 'store', 'creator']);

            $channel = TenantConfiguration::get('expense_notification_channel', 'email');

            $usersToNotify = $this->getManagerAndOwners($expense->store_id);

            if ($usersToNotify->isEmpty()) {
                Log::warning('No users to notify for expense approval', [
                    'expense_id' => $expense->id,
                    'store_id' => $expense->store_id,
                ]);

                return;
            }

            $message = $this->prepareMessage($expense);

            foreach ($usersToNotify as $user) {
                SendNotificationJob::dispatch(
                    channel: $channel,
                    recipient: $user->email,
                    message: $message,
                    metadata: [
                        'expense_id' => $expense->id,
                        'store_id' => $expense->store_id,
                        'user_id' => $user->id,
                    ]
                );
            }

            Log::info('Expense approval notifications dispatched', [
                'expense_id' => $expense->id,
                'users_notified' => $usersToNotify->count(),
                'channel' => $channel,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send expense approval notifications', [
                'expense_id' => $event->expense->id ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Prepare notification message
     */
    private function prepareMessage(Expense $expense): array
    {
        $categoryName = $expense->category->name ?? 'Uncategorized';
        $storeName = $expense->store->name ?? 'Company-wide';
        $amount = number_format($expense->amount, 2);
        $createdBy = $expense->creator->name ?? 'Unknown';
        $expenseDate = $expense->expense_date->format('Y-m-d');

        $subject = "Expense Approval Required: {$categoryName}";

        $body = "An expense requires your approval:\n\n";
        $body .= "Category: {$categoryName}\n";
        $body .= "Store: {$storeName}\n";
        $body .= "Amount: KES {$amount}\n";
        $body .= "Expense Date: {$expenseDate}\n";
        $body .= "Submitted By: {$createdBy}\n";

        if ($expense->description) {
            $body .= "Description: {$expense->description}\n";
        }

        $body .= "\nPlease review and approve or reject this expense.";

        return [
            'subject' => $subject,
            'body' => $body,
        ];
    }
}
