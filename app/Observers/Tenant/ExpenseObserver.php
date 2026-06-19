<?php

namespace App\Observers\Tenant;

use App\Enums\Tenant\ExpenseStatus;
use App\Models\Tenant\Expense;
use App\Services\Tenant\AuditService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ExpenseObserver
{
    public function __construct(
        private AuditService $auditService
    ) {}

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

        try {
            $this->auditService->createAudit(
                model: $expense,
                action: 'created',
                oldValues: null,
                newValues: $expense->toArray(),
                description: $this->generateCreationDescription($expense),
                tags: ['expense', 'financial']
            );
        } catch (\Exception $e) {
            Log::error('Failed to create expense audit log', [
                'tenant_id' => tenant()?->id,
                'expense_id' => $expense->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Fire event if expense needs approval
        if ($expense->approval_status === ExpenseStatus::PENDING) {
            // TODO: Fire ExpenseCreatedPendingApproval event
        }
    }

    /**
     * Handle the Expense "updating" event.
     */
    public function updating(Expense $expense): void
    {
        // Store old values for audit comparison
        $expense->storeOldValuesForAudit();
    }

    /**
     * Handle the Expense "updated" event.
     */
    public function updated(Expense $expense): void
    {
        $this->clearCache();

        try {
            if ($this->auditService->hasCriticalChanges($expense)) {
                $oldValues = $expense->getOldValuesForAudit();
                $criticalChanges = $expense->getCriticalChanges();

                // Generate context-aware description
                $description = $this->generateUpdateDescription($expense, $criticalChanges);

                // Add specific tags based on changes
                $tags = ['expense', 'financial'];
                if (isset($criticalChanges['approval_status'])) {
                    $tags[] = 'approval';
                    $tags[] = 'workflow';
                }
                if (isset($criticalChanges['payment_status'])) {
                    $tags[] = 'payment';
                }

                $this->auditService->createAudit(
                    model: $expense,
                    action: 'updated',
                    oldValues: array_intersect_key($oldValues, $criticalChanges),
                    newValues: $criticalChanges,
                    description: $description,
                    tags: $tags
                );
            }
        } catch (\Exception $e) {
            Log::error('Failed to create expense update audit log', [
                'tenant_id' => tenant()?->id,
                'expense_id' => $expense->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Handle approval status changes
        $changes = $expense->getChanges();
        if (isset($changes['approval_status'])) {
            $this->handleApprovalStatusChange($expense, $changes);
        }
    }

    /**
     * Handle the Expense "deleted" event.
     */
    public function deleted(Expense $expense): void
    {
        $this->clearCache();

        try {
            $this->auditService->createAudit(
                model: $expense,
                action: 'deleted',
                oldValues: $expense->toArray(),
                newValues: null,
                description: $this->generateDeletionDescription($expense),
                tags: ['expense', 'financial', 'critical']
            );
        } catch (\Exception $e) {
            Log::error('Failed to create expense deletion audit log', [
                'tenant_id' => tenant()?->id,
                'expense_id' => $expense->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle the Expense "restored" event.
     */
    public function restored(Expense $expense): void
    {
        $this->clearCache();

        try {
            $this->auditService->createAudit(
                model: $expense,
                action: 'restored',
                oldValues: null,
                newValues: $expense->toArray(),
                description: $this->generateRestorationDescription($expense),
                tags: ['expense', 'financial']
            );
        } catch (\Exception $e) {
            Log::error('Failed to create expense restoration audit log', [
                'tenant_id' => tenant()?->id,
                'expense_id' => $expense->id,
                'error' => $e->getMessage(),
            ]);
        }
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

    private function generateCreationDescription(Expense $expense): string
    {
        $user = Auth::user()?->name ?? 'System';
        $amount = number_format($expense->amount, 2);
        $categoryName = $expense->category->name ?? 'Uncategorized';

        return "{$user} created expense {$expense->expense_number} for {$categoryName} - KES {$amount}";
    }

    private function generateUpdateDescription(Expense $expense, array $changes): string
    {
        $user = Auth::user()?->name ?? 'System';

        // Approval status change
        if (isset($changes['approval_status'])) {
            $oldStatus = $expense->getOriginal('approval_status');
            $newStatus = $changes['approval_status'];

            if ($newStatus === ExpenseStatus::APPROVED) {
                return "{$user} approved expense {$expense->expense_number}";
            } elseif ($newStatus === ExpenseStatus::REJECTED) {
                return "{$user} rejected expense {$expense->expense_number}";
            }

            return "{$user} changed expense {$expense->expense_number} approval status from {$oldStatus} to {$newStatus}";
        }

        // Payment status change
        if (isset($changes['payment_status'])) {
            $oldStatus = $expense->getOriginal('payment_status');
            $newStatus = $changes['payment_status'];
            return "{$user} changed expense {$expense->expense_number} payment status from {$oldStatus} to {$newStatus}";
        }

        // Amount change
        if (isset($changes['amount'])) {
            $oldAmount = number_format($expense->getOriginal('amount'), 2);
            $newAmount = number_format($changes['amount'], 2);
            return "{$user} changed expense {$expense->expense_number} amount from KES {$oldAmount} to KES {$newAmount}";
        }

        // Generic update
        $changedFields = implode(', ', array_keys($changes));
        return "{$user} updated expense {$expense->expense_number} ({$changedFields})";
    }

    /**
     * Generate description for expense deletion
     */
    private function generateDeletionDescription(Expense $expense): string
    {
        $user = Auth::user()?->name ?? 'System';
        $amount = number_format($expense->amount, 2);

        return "{$user} deleted expense {$expense->expense_number} (KES {$amount})";
    }

    /**
     * Generate description for expense restoration
     */
    private function generateRestorationDescription(Expense $expense): string
    {
        $user = Auth::user()?->name ?? 'System';

        return "{$user} restored expense {$expense->expense_number}";
    }
}
