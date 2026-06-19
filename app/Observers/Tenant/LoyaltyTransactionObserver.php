<?php

namespace App\Observers\Tenant;

use App\Models\Tenant\LoyaltyTransaction;
use App\Services\Tenant\AuditService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class LoyaltyTransactionObserver
{
    public function __construct(
        private AuditService $auditService
    ) {}

    /**
     * Handle the LoyaltyTransaction "created" event.
     *
     * Use critical-only mode due to high volume.
     */
    public function created(LoyaltyTransaction $transaction): void
    {
        try {
            // Always audit loyalty transactions
            $this->auditService->createAudit(
                model: $transaction,
                action: 'created',
                oldValues: null,
                newValues: $transaction->toArray(),
                description: $this->generateCreationDescription($transaction),
                tags: $this->generateTags($transaction)
            );
        } catch (\Exception $e) {
            Log::error('Failed to create loyalty transaction audit log', [
                'tenant_id' => tenant()?->id,
                'transaction_id' => $transaction->id,
                'customer_id' => $transaction->customer_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle the LoyaltyTransaction "updating" event.
     */
    public function updating(LoyaltyTransaction $transaction): void
    {
        $transaction->storeOldValuesForAudit();
    }

    /**
     * Handle the LoyaltyTransaction "updated" event.
     */
    public function updated(LoyaltyTransaction $transaction): void
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
                    tags: $this->generateTags($transaction)
                );
            }
        } catch (\Exception $e) {
            Log::error('Failed to create loyalty transaction update audit log', [
                'tenant_id' => tenant()?->id,
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle the LoyaltyTransaction "deleted" event.
     */
    public function deleted(LoyaltyTransaction $transaction): void
    {
        try {
            $this->auditService->createAudit(
                model: $transaction,
                action: 'deleted',
                oldValues: $transaction->toArray(),
                newValues: null,
                description: $this->generateDeletionDescription($transaction),
                tags: array_merge($this->generateTags($transaction), ['critical'])
            );
        } catch (\Exception $e) {
            Log::error('Failed to create loyalty transaction deletion audit log', [
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
    private function generateCreationDescription(LoyaltyTransaction $transaction): string
    {
        $user = Auth::user()?->name ?? 'System';
        $customerName = $transaction->customer->name ?? "Customer #{$transaction->customer_id}";
        $points = number_format(abs($transaction->points), 2);
        $type = $transaction->transaction_type->label();

        if ($transaction->points > 0) {
            // Points earned
            return "{$user} awarded {$points} loyalty points to {$customerName} - {$type} (Balance: " . number_format($transaction->balance_after, 2) . " points)";
        } else {
            // Points redeemed
            return "{$user} redeemed {$points} loyalty points for {$customerName} - {$type} (Balance: " . number_format($transaction->balance_after, 2) . " points)";
        }
    }

    /**
     * Generate description for transaction update
     */
    private function generateUpdateDescription(LoyaltyTransaction $transaction, array $changes): string
    {
        $user = Auth::user()?->name ?? 'System';

        // Points adjustment
        if (isset($changes['points'])) {
            $oldPoints = number_format(abs($transaction->getOriginal('points')), 2);
            $newPoints = number_format(abs($changes['points']), 2);
            return "{$user} adjusted loyalty points from {$oldPoints} to {$newPoints}";
        }

        // Balance adjustment
        if (isset($changes['balance_after'])) {
            $oldBalance = number_format($transaction->getOriginal('balance_after'), 2);
            $newBalance = number_format($changes['balance_after'], 2);
            return "{$user} adjusted loyalty balance from {$oldBalance} to {$newBalance}";
        }

        // Transaction type change (rare)
        if (isset($changes['transaction_type'])) {
            $oldType = $transaction->getOriginal('transaction_type');
            $newType = $changes['transaction_type'];
            return "{$user} changed loyalty transaction type from {$oldType} to {$newType}";
        }

        // Generic update
        $changedFields = implode(', ', array_keys($changes));
        return "{$user} updated loyalty transaction ({$changedFields})";
    }

    /**
     * Generate description for transaction deletion
     */
    private function generateDeletionDescription(LoyaltyTransaction $transaction): string
    {
        $user = Auth::user()?->name ?? 'System';
        $points = number_format(abs($transaction->points), 2);
        $type = $transaction->transaction_type->label();

        return "{$user} deleted {$type} loyalty transaction of {$points} points";
    }

    /**
     * Generate context-aware tags
     */
    private function generateTags(LoyaltyTransaction $transaction): array
    {
        $tags = ['loyalty', 'customer'];

        // Add transaction type tag
        if ($transaction->transaction_type) {
            $tags[] = strtolower($transaction->transaction_type->value);
        }

        // Add high-value tag for large points
        if (abs($transaction->points) >= 1000) {
            $tags[] = 'high_value';
        }

        return $tags;
    }
}
