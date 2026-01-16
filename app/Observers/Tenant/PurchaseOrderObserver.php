<?php

namespace App\Observers\Tenant;

use App\Models\Tenant\PurchaseOrder;
use App\Services\Tenant\AuditService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PurchaseOrderObserver
{
    public function __construct(
        private AuditService $auditService
    ) {}

    /**
     * Handle the PurchaseOrder "created" event.
     *
     * Create aggregated audit including PO items.
     */
    public function created(PurchaseOrder $purchaseOrder): void
    {
        try {
            // Ensure items are loaded
            $purchaseOrder->loadMissing(['items', 'supplier:id,name']);

            // Create aggregated audit
            $aggregatedData = [
                'purchase_order' => $purchaseOrder->toArray(),
                'items' => $purchaseOrder->items->toArray(),
            ];

            $this->auditService->createAggregatedAudit(
                model: $purchaseOrder,
                action: 'created',
                aggregatedData: $aggregatedData,
                description: $this->generateCreationDescription($purchaseOrder),
                tags: ['purchase_order', 'procurement', 'financial', 'aggregated']
            );
        } catch (\Exception $e) {
            Log::error('Failed to create purchase order audit log', [
                'tenant_id' => tenant()?->id,
                'po_id' => $purchaseOrder->id,
                'po_number' => $purchaseOrder->po_number,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle the PurchaseOrder "updating" event.
     */
    public function updating(PurchaseOrder $purchaseOrder): void
    {
        // Store old values for audit comparison
        $purchaseOrder->storeOldValuesForAudit();
    }

    /**
     * Handle the PurchaseOrder "updated" event.
     *
     * Only audit if critical fields changed.
     */
    public function updated(PurchaseOrder $purchaseOrder): void
    {
        try {
            // Check if critical fields changed
            if (!$this->auditService->hasCriticalChanges($purchaseOrder)) {
                return;
            }

            $oldValues = $purchaseOrder->getOldValuesForAudit();
            $criticalChanges = $purchaseOrder->getCriticalChanges();

            // Generate context-aware description
            $description = $this->generateUpdateDescription($purchaseOrder, $criticalChanges);

            // Add specific tags based on changes
            $tags = ['purchase_order', 'procurement', 'financial'];
            if (isset($criticalChanges['status'])) {
                $tags[] = 'status_change';
                $tags[] = 'workflow';
            }
            if (isset($criticalChanges['payment_status'])) {
                $tags[] = 'payment';
            }

            $this->auditService->createAudit(
                model: $purchaseOrder,
                action: 'updated',
                oldValues: array_intersect_key($oldValues, $criticalChanges),
                newValues: $criticalChanges,
                description: $description,
                tags: $tags
            );
        } catch (\Exception $e) {
            Log::error('Failed to create purchase order update audit log', [
                'tenant_id' => tenant()?->id,
                'po_id' => $purchaseOrder->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle the PurchaseOrder "deleted" event.
     */
    public function deleted(PurchaseOrder $purchaseOrder): void
    {
        try {
            $this->auditService->createAudit(
                model: $purchaseOrder,
                action: 'deleted',
                oldValues: $purchaseOrder->toArray(),
                newValues: null,
                description: $this->generateDeletionDescription($purchaseOrder),
                tags: ['purchase_order', 'procurement', 'financial', 'critical']
            );
        } catch (\Exception $e) {
            Log::error('Failed to create purchase order deletion audit log', [
                'tenant_id' => tenant()?->id,
                'po_id' => $purchaseOrder->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle the PurchaseOrder "restored" event.
     */
    public function restored(PurchaseOrder $purchaseOrder): void
    {
        try {
            $this->auditService->createAudit(
                model: $purchaseOrder,
                action: 'restored',
                oldValues: null,
                newValues: $purchaseOrder->toArray(),
                description: $this->generateRestorationDescription($purchaseOrder),
                tags: ['purchase_order', 'procurement', 'financial']
            );
        } catch (\Exception $e) {
            Log::error('Failed to create purchase order restoration audit log', [
                'tenant_id' => tenant()?->id,
                'po_id' => $purchaseOrder->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    // ========================================
    // Description Generators
    // ========================================

    /**
     * Generate description for PO creation
     */
    private function generateCreationDescription(PurchaseOrder $purchaseOrder): string
    {
        $user = Auth::user()?->name ?? 'System';
        $itemCount = $purchaseOrder->items->count();
        $amount = number_format($purchaseOrder->total_amount, 2);
        $supplierName = $purchaseOrder->supplier->name ?? 'Unknown Supplier';

        return "{$user} created purchase order {$purchaseOrder->po_number} for {$supplierName} with {$itemCount} items totaling KES {$amount}";
    }

    /**
     * Generate description for PO update
     */
    private function generateUpdateDescription(PurchaseOrder $purchaseOrder, array $changes): string
    {
        $user = Auth::user()?->name ?? 'System';

        // Status change
        if (isset($changes['status'])) {
            $oldStatus = $purchaseOrder->getOriginal('status');
            $newStatus = $changes['status'];
            return "{$user} changed purchase order {$purchaseOrder->po_number} status from {$oldStatus} to {$newStatus}";
        }

        // Payment status change
        if (isset($changes['payment_status'])) {
            $oldStatus = $purchaseOrder->getOriginal('payment_status');
            $newStatus = $changes['payment_status'];
            return "{$user} changed purchase order {$purchaseOrder->po_number} payment status from {$oldStatus} to {$newStatus}";
        }

        // Total amount change
        if (isset($changes['total_amount'])) {
            $oldAmount = number_format($purchaseOrder->getOriginal('total_amount'), 2);
            $newAmount = number_format($changes['total_amount'], 2);
            return "{$user} changed purchase order {$purchaseOrder->po_number} total from KES {$oldAmount} to KES {$newAmount}";
        }

        // Approval
        if (isset($changes['approved_by']) && $changes['approved_by']) {
            return "{$user} approved purchase order {$purchaseOrder->po_number}";
        }

        // Generic update
        $changedFields = implode(', ', array_keys($changes));
        return "{$user} updated purchase order {$purchaseOrder->po_number} ({$changedFields})";
    }

    /**
     * Generate description for PO deletion
     */
    private function generateDeletionDescription(PurchaseOrder $purchaseOrder): string
    {
        $user = Auth::user()?->name ?? 'System';
        $amount = number_format($purchaseOrder->total_amount, 2);

        return "{$user} deleted purchase order {$purchaseOrder->po_number} (KES {$amount})";
    }

    /**
     * Generate description for PO restoration
     */
    private function generateRestorationDescription(PurchaseOrder $purchaseOrder): string
    {
        $user = Auth::user()?->name ?? 'System';

        return "{$user} restored purchase order {$purchaseOrder->po_number}";
    }
}
