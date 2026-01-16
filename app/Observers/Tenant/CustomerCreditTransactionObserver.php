<?php

namespace App\Observers\Tenant;

use App\Models\Tenant\CustomerCreditTransaction;
use App\Services\Tenant\AuditService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CustomerCreditTransactionObserver
{
    public function __construct(
        private AuditService $auditService
    ) {}

    /**
     * Handle the CustomerCreditTransaction "created" event.
     *
     * Always audit credit transactions for compliance and tracking.
     */
    public function created(CustomerCreditTransaction $transaction): void
    {
        try {
            $this->auditService->createAudit(
                model: $transaction,
                action: 'created',
                oldValues: null,
                newValues: $transaction->toArray(),
                description: $this->generateCreationDescription($transaction),
                tags: ['credit', 'customer', 'financial', 'transaction']
            );
        } catch (\Exception $e) {
            Log::error('Failed to create customer credit transaction audit log', [
                'tenant_id' => tenant()?->id,
                'transaction_id' => $transaction->id,
                'customer_id' => $transaction->customer_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle the CustomerCreditTransaction "updating" event.
     */
    public function updating(CustomerCreditTransaction $transaction): void
    {
        $transaction->storeOldValuesForAudit();
    }

    /**
     * Handle the CustomerCreditTransaction "updated" event.
     */
    public function updated(CustomerCreditTransaction $transaction): void
    {
        try {
            if ($this->auditService->hasCriticalChanges($transaction)) {
                $oldValues = $transaction->getOldValuesForAudit();
                $criticalChanges = $transaction->getCriticalChanges();

                $this->auditService->createAudit(
                    model: $transaction,
                    action: 'updated',
                    oldValues: array_intersect_key($oldValues, $criticalChanges),
                    newValues: $criticalChanges,
                    description: $this->generateUpdateDescription($transaction, $criticalChanges),
                    tags: ['credit', 'customer', 'financial', 'transaction']
                );
            }
        } catch (\Exception $e) {
            Log::error('Failed to create customer credit transaction update audit log', [
                'tenant_id' => tenant()?->id,
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle the CustomerCreditTransaction "deleted" event.
     */
    public function deleted(CustomerCreditTransaction $transaction): void
    {
        try {
            $this->auditService->createAudit(
                model: $transaction,
                action: 'deleted',
                oldValues: $transaction->toArray(),
                newValues: null,
                description: $this->generateDeletionDescription($transaction),
                tags: ['credit', 'customer', 'financial', 'transaction', 'critical']
            );
        } catch (\Exception $e) {
            Log::error('Failed to create customer credit transaction deletion audit log', [
                'tenant_id' => tenant()?->id,
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    // ========================================
    // Description Generators
    // ========================================

    /**
     * Generate description for transaction creation
     */
    private function generateCreationDescription(CustomerCreditTransaction $transaction): string
    {
        $user = Auth::user()?->name ?? 'System';
        $customerName = $transaction->customer->name ?? "Customer #{$transaction->customer_id}";
        $amount = number_format(abs($transaction->amount), 2);
        $type = $transaction->transaction_type->label();

        if ($transaction->amount > 0) {
            // Debt increase (sale on credit)
            return "{$user} recorded {$type} of KES {$amount} for {$customerName} (New balance: KES " . number_format($transaction->balance_after, 2) . ")";
        } else {
            // Payment (debt decrease)
            return "{$user} recorded {$type} of KES {$amount} from {$customerName} (New balance: KES " . number_format($transaction->balance_after, 2) . ")";
        }
    }

    /**
     * Generate description for transaction update
     */
    private function generateUpdateDescription(CustomerCreditTransaction $transaction, array $changes): string
    {
        $user = Auth::user()?->name ?? 'System';

        // Amount adjustment
        if (isset($changes['amount'])) {
            $oldAmount = number_format(abs($transaction->getOriginal('amount')), 2);
            $newAmount = number_format(abs($changes['amount']), 2);
            return "{$user} adjusted credit transaction amount from KES {$oldAmount} to KES {$newAmount}";
        }

        // Balance adjustment
        if (isset($changes['balance_after'])) {
            $oldBalance = number_format($transaction->getOriginal('balance_after'), 2);
            $newBalance = number_format($changes['balance_after'], 2);
            return "{$user} adjusted credit balance from KES {$oldBalance} to KES {$newBalance}";
        }

        // Generic update
        $changedFields = implode(', ', array_keys($changes));
        return "{$user} updated credit transaction ({$changedFields})";
    }

    /**
     * Generate description for transaction deletion
     */
    private function generateDeletionDescription(CustomerCreditTransaction $transaction): string
    {
        $user = Auth::user()?->name ?? 'System';
        $amount = number_format(abs($transaction->amount), 2);
        $type = $transaction->transaction_type->label();

        return "{$user} deleted {$type} transaction of KES {$amount}";
    }
}
