<?php

namespace App\Observers\Tenant;

use App\Models\Tenant\Budget;
use App\Services\Tenant\AuditService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class BudgetObserver
{
    public function __construct(
        private AuditService $auditService
    ) {}

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

        try {
            $this->auditService->createAudit(
                model: $budget,
                action: 'created',
                oldValues: null,
                newValues: $budget->toArray(),
                description: $this->generateCreationDescription($budget),
                tags: ['budget', 'financial']
            );
        } catch (\Exception $e) {
            Log::error('Failed to create budget audit log', [
                'tenant_id' => tenant()?->id,
                'budget_id' => $budget->id,
                'error' => $e->getMessage(),
            ]);
        }
    }


    /**
     * Handle the Budget "updated" event.
     */
    public function updated(Budget $budget): void
    {
        $this->clearCache();

        $changes = $budget->getDirty();

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

        try {
            $this->auditService->createAudit(
                model: $budget,
                action: 'deleted',
                oldValues: $budget->toArray(),
                newValues: null,
                description: $this->generateDeletionDescription($budget),
                tags: ['budget', 'financial', 'critical']
            );
        } catch (\Exception $e) {
            Log::error('Failed to create budget deletion audit log', [
                'tenant_id' => tenant()?->id,
                'budget_id' => $budget->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle the Budget "restored" event.
     */
    public function restored(Budget $budget): void
    {
        $this->clearCache();

        try {
            $this->auditService->createAudit(
                model: $budget,
                action: 'restored',
                oldValues: null,
                newValues: $budget->toArray(),
                description: $this->generateRestorationDescription($budget),
                tags: ['budget', 'financial']
            );
        } catch (\Exception $e) {
            Log::error('Failed to create budget restoration audit log', [
                'tenant_id' => tenant()?->id,
                'budget_id' => $budget->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Clear all budget cache
     */
    protected function clearCache(): void
    {
        Cache::tags(['tenant', tenant()->id, 'budgets'])->flush();
    }

    /**
     * Generate description for budget creation
     */
    private function generateCreationDescription(Budget $budget): string
    {
        $user = Auth::user()?->name ?? 'System';
        $amount = number_format($budget->budget_amount, 2);
        $period = $budget->period_type->label();
        $categoryName = $budget->category->name ?? 'Unknown Category';
        $storeInfo = $budget->store ? " for {$budget->store->name}" : ' (company-wide)';

        return "{$user} created {$period} budget '{$budget->budget_name}' - KES {$amount} for {$categoryName}{$storeInfo}";
    }

    /**
     * Generate description for budget update
     */
    private function generateUpdateDescription(Budget $budget, array $changes): string
    {
        $user = Auth::user()?->name ?? 'System';

        // Alert triggered
        if (isset($changes['alert_triggered']) && $changes['alert_triggered']) {
            $percentage = number_format($budget->percentage_spent, 2);
            $threshold = number_format($budget->alert_threshold_percentage, 2);
            return "{$user} - Budget '{$budget->budget_name}' alert triggered (spent: {$percentage}%, threshold: {$threshold}%)";
        }

        // Spent amount change (critical)
        if (isset($changes['spent_amount'])) {
            $oldSpent = number_format($budget->getOriginal('spent_amount'), 2);
            $newSpent = number_format($changes['spent_amount'], 2);
            $status = $budget->is_over_budget ? ' - BUDGET EXCEEDED' : '';
            return "{$user} - Budget '{$budget->budget_name}' spent amount changed from KES {$oldSpent} to KES {$newSpent}{$status}";
        }

        // Generic update
        $changedFields = implode(', ', array_keys($changes));
        return "{$user} updated budget '{$budget->budget_name}' - {$changedFields}";
    }

    /**
     * Generate description for budget deletion
     */
    private function generateDeletionDescription(Budget $budget): string
    {
        $user = Auth::user()?->name ?? 'System';
        $amount = number_format($budget->budget_amount, 2);
        $spent = number_format($budget->spent_amount, 2);

        return "{$user} deleted budget '{$budget->budget_name}' - KES {$amount} (spent: KES {$spent})";
    }

    /**
     * Generate description for budget restoration
     */
    private function generateRestorationDescription(Budget $budget): string
    {
        $user = Auth::user()?->name ?? 'System';
        $amount = number_format($budget->budget_amount, 2);

        return "{$user} restored budget '{$budget->budget_name}' - KES {$amount}";
    }
}
