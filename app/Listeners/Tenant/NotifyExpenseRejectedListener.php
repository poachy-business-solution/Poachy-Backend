<?php

namespace App\Listeners\Tenant;

use App\Events\Tenant\ExpenseRejected;
use App\Jobs\Tenant\SendNotificationJob;
use App\Listeners\Tenant\Concerns\ResolvesNotificationRecipients;
use App\Models\Tenant\Expense;
use App\Models\Tenant\TenantConfiguration;
use Illuminate\Support\Facades\Log;

class NotifyExpenseRejectedListener
{
    use ResolvesNotificationRecipients;

    /**
     * Handle the event.
     */
    public function handle(ExpenseRejected $event): void
    {
        try {
            $expense = $event->expense->load(['category', 'store']);

            $channel = TenantConfiguration::get('expense_notification_channel', 'email');

            $usersToNotify = $this->getSingleUser($expense->created_by);

            if ($usersToNotify->isEmpty()) {
                Log::warning('No submitter to notify for expense rejection', [
                    'expense_id' => $expense->id,
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
                        'user_id' => $user->id,
                    ]
                );
            }

            Log::info('Expense rejected notification dispatched', [
                'expense_id' => $expense->id,
                'channel' => $channel,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send expense rejected notification', [
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
        $amount = number_format($expense->amount, 2);

        $subject = "Expense Rejected: {$categoryName}";

        $body = "Your expense has been rejected:\n\n";
        $body .= "Category: {$categoryName}\n";
        $body .= "Amount: KES {$amount}\n";

        if ($expense->rejection_reason) {
            $body .= "Reason: {$expense->rejection_reason}\n";
        }

        return [
            'subject' => $subject,
            'body' => $body,
        ];
    }
}
