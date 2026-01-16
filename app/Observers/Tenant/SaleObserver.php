<?php

namespace App\Observers\Tenant;

use App\Jobs\Tenant\UpdateDailyAggregatesJob;
use App\Jobs\Tenant\UpdateUniqueCustomerCountJob;
use App\Models\Tenant\Sale;
use App\Services\Tenant\AuditService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class SaleObserver
{
    public function __construct(
        private AuditService $auditService
    ) {}

    /**
     * Handle the Sale "creating" event.
     */
    public function creating(Sale $sale): void
    {
        // Set created_by if not set
        if (!$sale->served_by) {
            $sale->served_by = Auth::id();
        }

        // Set sale_date if not set
        if (!$sale->sale_date) {
            $sale->sale_date = now();
        }
    }

    /**
     * Handle the Sale "created" event.
     */
    public function created(Sale $sale): void
    {
        try {
            $tenantId = tenant()->id;

            // Dispatch job to update aggregates
            UpdateDailyAggregatesJob::dispatch($tenantId, $sale->id);

            // Dispatch job to update unique customer count
            $aggregateDate = $sale->sale_date->toDateString();
            UpdateUniqueCustomerCountJob::dispatch($tenantId, $aggregateDate, $sale->store_id)
                ->delay(now()->addSeconds(5)); // Small delay to let aggregates update first

            Log::info('Daily aggregate jobs dispatched', [
                'tenant_id' => $tenantId,
                'sale_id' => $sale->id,
                'sale_number' => $sale->sale_number,
            ]);
        } catch (\Exception $e) {
            // Don't block sale creation if job dispatch fails
            Log::error('Failed to dispatch daily aggregate jobs', [
                'tenant_id' => tenant()->id ?? 'unknown',
                'sale_id' => $sale->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Create aggregated audit (includes items and payments)
        try {
            $aggregatedData = $this->auditService->getAggregatedData($sale);

            $this->auditService->createAggregatedAudit(
                model: $sale,
                action: 'created',
                aggregatedData: $aggregatedData,
                description: $this->generateCreationDescription($sale),
                tags: ['sale', 'transaction', 'financial']
            );
        } catch (\Exception $e) {
            Log::error('Failed to create sale audit log', [
                'tenant_id' => tenant()?->id,
                'sale_id' => $sale->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle the Sale "updated" event.
     */
    public function updated(Sale $sale): void
    {
        try {
            // Full audit mode - always log updates
            if ($sale->wasChanged()) {
                $oldValues = $sale->getOriginal();
                $changes = $sale->getChanges();

                // Generate context-aware description
                $description = $this->generateUpdateDescription($sale, $changes);

                // Generate tags based on what changed
                $tags = ['sale', 'transaction', 'financial'];
                if (isset($changes['payment_status'])) {
                    $tags[] = 'payment_status';
                    $tags[] = 'critical';
                }
                if (isset($changes['total_amount'])) {
                    $tags[] = 'amount_change';
                    $tags[] = 'critical';
                }

                $this->auditService->createAudit(
                    model: $sale,
                    action: 'updated',
                    oldValues: $oldValues,
                    newValues: $changes,
                    description: $description,
                    tags: $tags
                );
            }
        } catch (\Exception $e) {
            Log::error('Failed to create sale update audit log', [
                'tenant_id' => tenant()?->id,
                'sale_id' => $sale->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle the Sale "deleting" event.
     */
    public function deleting(Sale $sale): void {}

    /**
     * Handle the Sale "deleted" event.
     */
    public function deleted(Sale $sale): void
    {
        try {
            // Include aggregated data in deletion audit
            $aggregatedData = $this->auditService->getAggregatedData($sale);

            $this->auditService->createAggregatedAudit(
                model: $sale,
                action: 'deleted',
                aggregatedData: $aggregatedData,
                description: $this->generateDeletionDescription($sale),
                tags: ['sale', 'transaction', 'financial', 'critical']
            );
        } catch (\Exception $e) {
            Log::error('Failed to create sale deletion audit log', [
                'tenant_id' => tenant()?->id,
                'sale_id' => $sale->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle the Sale "restored" event.
     */
    public function restored(Sale $sale): void
    {
        try {
            $aggregatedData = $this->auditService->getAggregatedData($sale);

            $this->auditService->createAggregatedAudit(
                model: $sale,
                action: 'restored',
                aggregatedData: $aggregatedData,
                description: $this->generateRestorationDescription($sale),
                tags: ['sale', 'transaction', 'financial']
            );
        } catch (\Exception $e) {
            Log::error('Failed to create sale restoration audit log', [
                'tenant_id' => tenant()?->id,
                'sale_id' => $sale->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle the Sale "forceDeleted" event.
     */
    public function forceDeleted(Sale $sale): void
    {
        try {
            $this->auditService->createAudit(
                model: $sale,
                action: 'force_deleted',
                oldValues: $sale->toArray(),
                newValues: null,
                description: $this->generateForceDeletionDescription($sale),
                tags: ['sale', 'transaction', 'financial', 'critical', 'permanent']
            );
        } catch (\Exception $e) {
            Log::error('Failed to create sale force deletion audit log', [
                'tenant_id' => tenant()?->id,
                'sale_id' => $sale->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Generate description for sale creation
     */
    private function generateCreationDescription(Sale $sale): string
    {
        $user = Auth::user()?->name ?? 'System';
        $amount = number_format($sale->total_amount, 2);
        $customerInfo = $sale->customer ? " for {$sale->customer->name}" : '';
        $itemCount = $sale->items()->count();
        $paymentMethod = $sale->payment_method->label();

        return "{$user} created sale {$sale->sale_number}{$customerInfo} - KES {$amount} ({$itemCount} items, {$paymentMethod})";
    }

    /**
     * Generate description for sale update
     */
    private function generateUpdateDescription(Sale $sale, array $changes): string
    {
        $user = Auth::user()?->name ?? 'System';

        // Payment status change
        if (isset($changes['payment_status'])) {
            $oldStatus = $sale->getOriginal('payment_status');
            $newStatus = $changes['payment_status'];
            return "{$user} changed sale {$sale->sale_number} payment status from {$oldStatus} to {$newStatus}";
        }

        // Total amount change
        if (isset($changes['total_amount'])) {
            $oldAmount = number_format($sale->getOriginal('total_amount'), 2);
            $newAmount = number_format($changes['total_amount'], 2);
            return "{$user} changed sale {$sale->sale_number} total amount from KES {$oldAmount} to KES {$newAmount}";
        }

        // Payment method change
        if (isset($changes['payment_method'])) {
            $oldMethod = $sale->getOriginal('payment_method');
            $newMethod = $changes['payment_method'];
            return "{$user} changed sale {$sale->sale_number} payment method from {$oldMethod} to {$newMethod}";
        }

        // Amount paid change
        if (isset($changes['amount_paid'])) {
            $oldPaid = number_format($sale->getOriginal('amount_paid'), 2);
            $newPaid = number_format($changes['amount_paid'], 2);
            return "{$user} updated payment for sale {$sale->sale_number} from KES {$oldPaid} to KES {$newPaid}";
        }

        // Generic update
        $changedFields = implode(', ', array_keys($changes));
        return "{$user} updated sale {$sale->sale_number} ({$changedFields})";
    }

    /**
     * Generate description for sale deletion
     */
    private function generateDeletionDescription(Sale $sale): string
    {
        $user = Auth::user()?->name ?? 'System';
        $amount = number_format($sale->total_amount, 2);
        $customerInfo = $sale->customer ? " for {$sale->customer->name}" : '';
        $itemCount = $sale->items()->count();

        return "{$user} deleted sale {$sale->sale_number}{$customerInfo} - KES {$amount} ({$itemCount} items)";
    }

    /**
     * Generate description for sale restoration
     */
    private function generateRestorationDescription(Sale $sale): string
    {
        $user = Auth::user()?->name ?? 'System';
        $amount = number_format($sale->total_amount, 2);

        return "{$user} restored sale {$sale->sale_number} - KES {$amount}";
    }

    /**
     * Generate description for sale force deletion
     */
    private function generateForceDeletionDescription(Sale $sale): string
    {
        $user = Auth::user()?->name ?? 'System';
        $amount = number_format($sale->total_amount, 2);

        return "{$user} permanently deleted sale {$sale->sale_number} - KES {$amount}";
    }
}
