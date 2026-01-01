<?php

namespace App\Observers\Tenant;

use App\Models\Tenant\Budget;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class BudgetObserver
{
    /**
     * Handle the Budget "creating" event.
     */
    public function creating(Budget $budget): void
    {
        // Set creator if not already set
        if (!$budget->created_by && Auth::check()) {
            $budget->created_by = Auth::id();
        }

        // Initialize calculated fields if not set
        if (!isset($budget->spent_amount)) {
            $budget->spent_amount = 0;
        }

        if (!isset($budget->remaining_amount)) {
            $budget->remaining_amount = $budget->budget_amount;
        }

        if (!isset($budget->committed_amount)) {
            $budget->committed_amount = 0;
        }
    }

    /**
     * Handle the Budget "created" event.
     */
    public function created(Budget $budget): void
    {
        $this->clearCache();
        $this->logAction('created', $budget);
    }

    /**
     * Handle the Budget "updated" event.
     */
    public function updated(Budget $budget): void
    {
        $this->clearCache();

        $changes = $budget->getDirty();
        $this->logAction('updated', $budget, $changes);

        // Check if alert was triggered
        if (isset($changes['alert_triggered']) && $changes['alert_triggered'] === true) {
            // TODO: Fire BudgetAlertTriggered event
            Log::warning('Budget alert triggered', [
                'tenant_id' => tenant()->id,
                'budget_id' => $budget->id,
                'budget_name' => $budget->budget_name,
                'percentage_spent' => $budget->percentage_spent,
                'threshold' => $budget->alert_threshold_percentage,
            ]);
        }

        // Check if budget exceeded
        if ($budget->is_over_budget && (!isset($changes['spent_amount']) || $budget->getOriginal('spent_amount') <= $budget->budget_amount)) {
            // TODO: Fire BudgetExceeded event
            Log::error('Budget exceeded', [
                'tenant_id' => tenant()->id,
                'budget_id' => $budget->id,
                'budget_name' => $budget->budget_name,
                'budget_amount' => $budget->budget_amount,
                'spent_amount' => $budget->spent_amount,
                'overage' => $budget->spent_amount - $budget->budget_amount,
            ]);
        }
    }

    /**
     * Handle the Budget "deleted" event.
     */
    public function deleted(Budget $budget): void
    {
        $this->clearCache();
        $this->logAction('deleted', $budget);
    }

    /**
     * Handle the Budget "restored" event.
     */
    public function restored(Budget $budget): void
    {
        $this->clearCache();
        $this->logAction('restored', $budget);
    }

    /**
     * Clear all budget cache
     */
    protected function clearCache(): void
    {
        Cache::tags(['tenant', tenant()->id, 'budgets'])->flush();
    }

    /**
     * Log action for audit trail
     */
    protected function logAction(string $action, Budget $budget, array $changes = []): void
    {
        Log::info("Budget {$action}", [
            'tenant_id' => tenant()->id,
            'budget_id' => $budget->id,
            'budget_name' => $budget->budget_name,
            'category_id' => $budget->category_id,
            'store_id' => $budget->store_id,
            'budget_amount' => $budget->budget_amount,
            'spent_amount' => $budget->spent_amount,
            'action' => $action,
            'changes' => $changes,
            'user_id' => Auth::id(),
        ]);
    }
}
