<?php

namespace App\Observers\Tenant;

use App\Enums\Tenant\ExpenseStatus;
use App\Models\Tenant\Expense;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ExpenseObserver
{
    /**
     * Handle the Expense "creating" event.
     */
    public function creating(Expense $expense): void
    {
        // Set creator if not already set
        if (!$expense->created_by && Auth::check()) {
            $expense->created_by = Auth::id();
        }

        // Auto-generate expense number if not set
        // if (empty($expense->expense_number)) {
        //     $expense->expense_number = Expense::generateExpenseNumber();
        // }
    }

    /**
     * Handle the Expense "created" event.
     */
    public function created(Expense $expense): void
    {
        $this->clearCache();
        $this->logAction('created', $expense);

        // Fire event if expense needs approval
        if ($expense->approval_status === ExpenseStatus::PENDING) {
            // TODO: Fire ExpenseCreatedPendingApproval event
        }
    }

    /**
     * Handle the Expense "updated" event.
     */
    public function updated(Expense $expense): void
    {
        $this->clearCache();

        $changes = $expense->getDirty();
        $this->logAction('updated', $expense, $changes);

        // Check if approval status changed
        if (isset($changes['approval_status'])) {
            $this->handleApprovalStatusChange($expense, $changes);
        }

        // Check if payment status changed
        if (isset($changes['payment_status'])) {
            $this->logAction('payment_status_changed', $expense, [
                'old_status' => $expense->getOriginal('payment_status'),
                'new_status' => $expense->payment_status,
            ]);
        }
    }

    /**
     * Handle the Expense "deleted" event.
     */
    public function deleted(Expense $expense): void
    {
        $this->clearCache();
        $this->logAction('deleted', $expense);
    }

    /**
     * Handle the Expense "restored" event.
     */
    public function restored(Expense $expense): void
    {
        $this->clearCache();
        $this->logAction('restored', $expense);
    }

    /**
     * Handle approval status changes
     */
    protected function handleApprovalStatusChange(Expense $expense, array $changes): void
    {
        $oldStatus = $changes['approval_status'];
        $newStatus = $expense->approval_status;

        if ($newStatus === ExpenseStatus::APPROVED) {
            // TODO: Fire ExpenseApproved event
            // This will trigger budget recalculation            
        } elseif ($newStatus === ExpenseStatus::REJECTED) {
            // TODO: Fire ExpenseRejected event            
        }
    }

    /**
     * Clear all expense cache
     */
    protected function clearCache(): void
    {
        Cache::tags(['tenant', tenant()->id, 'expenses'])->flush();

        // Also clear budget cache as expenses affect budgets
        Cache::tags(['tenant', tenant()->id, 'budgets'])->flush();
    }

    /**
     * Log action for audit trail
     */
    protected function logAction(string $action, Expense $expense, array $changes = []): void
    {
        Log::info("Expense {$action}", [
            'tenant_id' => tenant()->id,
            'expense_id' => $expense->id,
            'expense_number' => $expense->expense_number,
            'category_id' => $expense->category_id,
            'amount' => $expense->amount,
            'approval_status' => $expense->approval_status?->value,
            'action' => $action,
            'changes' => $changes,
            'user_id' => Auth::id(),
        ]);
    }
}
