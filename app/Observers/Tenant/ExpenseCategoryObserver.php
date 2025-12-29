<?php

namespace App\Observers\Tenant;

use App\Models\Tenant\ExpenseCategory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ExpenseCategoryObserver
{
    /**
     * Handle the ExpenseCategory "creating" event.
     */
    public function creating(ExpenseCategory $expenseCategory): void
    {
        // Auto-set display_order if not provided
        if (!isset($expenseCategory->display_order)) {
            $maxOrder = ExpenseCategory::where('parent_id', $expenseCategory->parent_id)
                ->max('display_order') ?? -1;

            $expenseCategory->display_order = $maxOrder + 1;
        }
    }

    /**
     * Handle the ExpenseCategory "created" event.
     */
    public function created(ExpenseCategory $expenseCategory): void
    {
        $this->clearCache();
        $this->logAction('created', $expenseCategory);
    }

    /**
     * Handle the ExpenseCategory "updated" event.
     */
    public function updated(ExpenseCategory $expenseCategory): void
    {
        $this->clearCache();
        $this->logAction('updated', $expenseCategory, $expenseCategory->getDirty());
    }

    /**
     * Handle the ExpenseCategory "deleted" event.
     */
    public function deleted(ExpenseCategory $expenseCategory): void
    {
        $this->clearCache();
        $this->logAction('deleted', $expenseCategory);
    }

    /**
     * Handle the ExpenseCategory "restored" event.
     */
    public function restored(ExpenseCategory $expenseCategory): void
    {
        $this->clearCache();
        $this->logAction('restored', $expenseCategory);
    }

    /**
     * Clear all expense category cache
     */
    protected function clearCache(): void
    {
        Cache::tags(['tenant', tenant()->id, 'expense_categories'])->flush();
    }

    /**
     * Log action for audit trail
     */
    protected function logAction(string $action, ExpenseCategory $category, array $changes = []): void
    {
        Log::info("Expense category {$action}", [
            'tenant_id' => tenant()->id,
            'category_id' => $category->id,
            'category_code' => $category->code,
            'category_name' => $category->name,
            'action' => $action,
            'changes' => $changes,
            'user_id' => Auth::id(),
        ]);
    }
}
