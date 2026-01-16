<?php

namespace App\Observers\Tenant;

use App\Enums\Tenant\WasteApprovalStatus;
use App\Models\Tenant\InventoryWaste;
use App\Services\Tenant\AuditService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class InventoryWasteObserver
{
    public function __construct(
        private AuditService $auditService
    ) {}

    /**
     * Handle the InventoryWaste "creating" event.
     */
    public function creating(InventoryWaste $waste): void
    {
        // Set reporter if not already set
        if (!$waste->reported_by && Auth::check()) {
            $waste->reported_by = Auth::id();
        }
    }

    /**
     * Handle the InventoryWaste "created" event.
     */
    public function created(InventoryWaste $waste): void
    {
        $this->clearCache($waste);

        try {
            $this->auditService->createAudit(
                model: $waste,
                action: 'created',
                oldValues: null,
                newValues: $waste->toArray(),
                description: $this->generateCreationDescription($waste),
                tags: ['waste', 'inventory', 'loss']
            );
        } catch (\Exception $e) {
            Log::error('Failed to create inventory waste audit log', [
                'tenant_id' => tenant()?->id,
                'waste_id' => $waste->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Fire event if waste needs approval
        if ($waste->approval_status === WasteApprovalStatus::PENDING) {
            // TODO: Fire InventoryWasteCreatedPendingApproval event
            Log::info('Inventory waste pending approval', [
                'tenant_id' => tenant()->id,
                'waste_id' => $waste->id,
                'product_id' => $waste->product_id,
                'quantity' => $waste->quantity_wasted,
                'total_loss' => $waste->total_loss,
            ]);
        }
    }

    /**
     * Handle the InventoryWaste "updating" event.
     */
    public function updating(InventoryWaste $waste): void
    {
        // Store old values for audit comparison
        $waste->storeOldValuesForAudit();
    }

    /**
     * Handle the InventoryWaste "updated" event.
     */
    public function updated(InventoryWaste $waste): void
    {
        $this->clearCache($waste);

        try {
            if ($this->auditService->hasCriticalChanges($waste)) {
                $oldValues = $waste->getOldValuesForAudit();
                $criticalChanges = $waste->getCriticalChanges();

                // Generate context-aware description
                $description = $this->generateUpdateDescription($waste, $criticalChanges);

                // Add specific tags based on changes
                $tags = ['waste', 'inventory', 'loss'];
                if (isset($criticalChanges['approval_status'])) {
                    $tags[] = 'approval';
                    $tags[] = 'workflow';
                    $tags[] = 'critical';
                }
                if (isset($criticalChanges['quantity_wasted']) || isset($criticalChanges['total_loss'])) {
                    $tags[] = 'financial';
                    $tags[] = 'critical';
                }

                $this->auditService->createAudit(
                    model: $waste,
                    action: 'updated',
                    oldValues: array_intersect_key($oldValues, $criticalChanges),
                    newValues: $criticalChanges,
                    description: $description,
                    tags: $tags
                );
            }
        } catch (\Exception $e) {
            Log::error('Failed to create inventory waste update audit log', [
                'tenant_id' => tenant()?->id,
                'waste_id' => $waste->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Handle approval status changes
        $changes = $waste->getChanges();
        if (isset($changes['approval_status'])) {
            $this->handleApprovalStatusChange($waste, $changes);
        }
    }

    /**
     * Handle the InventoryWaste "deleted" event.
     */
    public function deleted(InventoryWaste $waste): void
    {
        $this->clearCache($waste);

        try {
            $this->auditService->createAudit(
                model: $waste,
                action: 'deleted',
                oldValues: $waste->toArray(),
                newValues: null,
                description: $this->generateDeletionDescription($waste),
                tags: ['waste', 'inventory', 'loss', 'critical']
            );
        } catch (\Exception $e) {
            Log::error('Failed to create inventory waste deletion audit log', [
                'tenant_id' => tenant()?->id,
                'waste_id' => $waste->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle the InventoryWaste "restored" event.
     */
    public function restored(InventoryWaste $waste): void
    {
        $this->clearCache($waste);

        try {
            $this->auditService->createAudit(
                model: $waste,
                action: 'restored',
                oldValues: null,
                newValues: $waste->toArray(),
                description: $this->generateRestorationDescription($waste),
                tags: ['waste', 'inventory', 'loss']
            );
        } catch (\Exception $e) {
            Log::error('Failed to create inventory waste restoration audit log', [
                'tenant_id' => tenant()?->id,
                'waste_id' => $waste->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle approval status changes
     */
    protected function handleApprovalStatusChange(InventoryWaste $waste, array $changes): void
    {
        $oldStatus = $changes['approval_status'];
        $newStatus = $waste->approval_status;

        if ($newStatus === WasteApprovalStatus::APPROVED) {
            // TODO: Fire InventoryWasteApproved event
            // This will trigger inventory deduction
            Log::info('Inventory waste approved', [
                'tenant_id' => tenant()->id,
                'waste_id' => $waste->id,
                'product_id' => $waste->product_id,
                'quantity_wasted' => $waste->quantity_wasted,
                'total_loss' => $waste->total_loss,
                'approved_by' => $waste->approved_by,
            ]);
        } elseif ($newStatus === WasteApprovalStatus::REJECTED) {
            // TODO: Fire InventoryWasteRejected event
            Log::info('Inventory waste rejected', [
                'tenant_id' => tenant()->id,
                'waste_id' => $waste->id,
                'product_id' => $waste->product_id,
                'rejected_by' => $waste->approved_by,
                'reason' => $waste->reason,
            ]);
        }
    }

    /**
     * Clear waste-related cache
     */
    protected function clearCache(InventoryWaste $waste): void
    {
        Cache::tags(['tenant', tenant()->id, 'inventory_waste'])->flush();
        Cache::tags(['tenant', tenant()->id, 'inventory'])->flush();

        // Clear product and store cache
        if ($waste->product_id) {
            Cache::tags(['tenant', tenant()->id, 'products'])->flush();
        }
        if ($waste->store_id) {
            Cache::tags(['tenant', tenant()->id, 'stores'])->flush();
        }
    }

    /**
     * Generate description for waste creation
     */
    private function generateCreationDescription(InventoryWaste $waste): string
    {
        $user = Auth::user()?->name ?? 'System';
        $productName = $waste->product?->name ?? 'Unknown Product';
        $quantity = number_format($waste->quantity_wasted, 2);
        $uomCode = $waste->product?->baseUom?->code ?? 'units';
        $loss = number_format($waste->total_loss, 2);
        $wasteType = $waste->waste_type->value ?? $waste->waste_type;

        return "{$user} reported {$wasteType} waste for {$productName} - {$quantity} {$uomCode} (Loss: KES {$loss})";
    }

    /**
     * Generate description for waste update
     */
    private function generateUpdateDescription(InventoryWaste $waste, array $changes): string
    {
        $user = Auth::user()?->name ?? 'System';
        $productName = $waste->product?->name ?? 'Unknown Product';

        // Approval status change
        if (isset($changes['approval_status'])) {
            $oldStatus = $waste->getOriginal('approval_status');
            $newStatus = $changes['approval_status'];

            if ($newStatus === WasteApprovalStatus::APPROVED->value) {
                $quantity = number_format($waste->quantity_wasted, 2);
                $uomCode = $waste->product?->baseUom?->code ?? 'units';
                $loss = number_format($waste->total_loss, 2);
                return "{$user} approved waste record for {$productName} - {$quantity} {$uomCode} (Loss: KES {$loss})";
            } elseif ($newStatus === WasteApprovalStatus::REJECTED->value) {
                return "{$user} rejected waste record for {$productName}";
            }

            return "{$user} changed waste record approval status from {$oldStatus} to {$newStatus} for {$productName}";
        }

        // Quantity change
        if (isset($changes['quantity_wasted'])) {
            $oldQty = number_format($waste->getOriginal('quantity_wasted'), 2);
            $newQty = number_format($changes['quantity_wasted'], 2);
            $uomCode = $waste->product?->baseUom?->code ?? 'units';
            return "{$user} changed waste quantity for {$productName} from {$oldQty} to {$newQty} {$uomCode}";
        }

        // Total loss change
        if (isset($changes['total_loss'])) {
            $oldLoss = number_format($waste->getOriginal('total_loss'), 2);
            $newLoss = number_format($changes['total_loss'], 2);
            return "{$user} changed waste total loss for {$productName} from KES {$oldLoss} to KES {$newLoss}";
        }

        // Generic update
        $changedFields = implode(', ', array_keys($changes));
        return "{$user} updated waste record for {$productName} ({$changedFields})";
    }

    /**
     * Generate description for waste deletion
     */
    private function generateDeletionDescription(InventoryWaste $waste): string
    {
        $user = Auth::user()?->name ?? 'System';
        $productName = $waste->product?->name ?? 'Unknown Product';
        $quantity = number_format($waste->quantity_wasted, 2);
        $uomCode = $waste->product?->baseUom?->code ?? 'units';
        $loss = number_format($waste->total_loss, 2);
        $wasteType = $waste->waste_type->value ?? $waste->waste_type;

        return "{$user} deleted {$wasteType} waste record for {$productName} - {$quantity} {$uomCode} (Loss: KES {$loss})";
    }

    /**
     * Generate description for waste restoration
     */
    private function generateRestorationDescription(InventoryWaste $waste): string
    {
        $user = Auth::user()?->name ?? 'System';
        $productName = $waste->product?->name ?? 'Unknown Product';

        return "{$user} restored waste record for {$productName}";
    }
}
