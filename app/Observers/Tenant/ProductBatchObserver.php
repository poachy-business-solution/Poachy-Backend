<?php

namespace App\Observers\Tenant;

use App\Models\Tenant\ProductBatch;
use App\Services\Tenant\AuditService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ProductBatchObserver
{
    public function __construct(
        private AuditService $auditService
    ) {}

    /**
     * Handle the ProductBatch "created" event.
     */
    public function created(ProductBatch $batch): void
    {
        $this->clearCache($batch);

        try {
            $this->auditService->createAudit(
                model: $batch,
                action: 'created',
                oldValues: null,
                newValues: $batch->toArray(),
                description: $this->generateCreationDescription($batch),
                tags: ['batch', 'inventory']
            );
        } catch (\Exception $e) {
            Log::error('Failed to create product batch audit log', [
                'tenant_id' => tenant()?->id,
                'batch_id' => $batch->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle the ProductBatch "updating" event.
     */
    public function updating(ProductBatch $batch): void
    {
        // Store old values for audit comparison
        $batch->storeOldValuesForAudit();
    }

    /**
     * Handle the ProductBatch "updated" event.
     */
    public function updated(ProductBatch $batch): void
    {
        $this->clearCache($batch);

        try {
            if ($this->auditService->hasCriticalChanges($batch)) {
                $oldValues = $batch->getOldValuesForAudit();
                $criticalChanges = $batch->getCriticalChanges();

                // Generate context-aware description
                $description = $this->generateUpdateDescription($batch, $criticalChanges);

                // Add specific tags based on changes
                $tags = ['batch', 'inventory'];
                if (isset($criticalChanges['is_expired'])) {
                    $tags[] = 'expiry';
                    $tags[] = 'critical';
                }
                if (isset($criticalChanges['quantity_remaining_in_base_uom'])) {
                    $tags[] = 'quantity_change';
                }

                $this->auditService->createAudit(
                    model: $batch,
                    action: 'updated',
                    oldValues: array_intersect_key($oldValues, $criticalChanges),
                    newValues: $criticalChanges,
                    description: $description,
                    tags: $tags
                );
            }
        } catch (\Exception $e) {
            Log::error('Failed to create product batch update audit log', [
                'tenant_id' => tenant()?->id,
                'batch_id' => $batch->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Handle expiry status changes
        if ($batch->wasChanged('is_expired') && $batch->is_expired) {
            $this->handleBatchExpiration($batch);
        }
    }

    /**
     * Handle the ProductBatch "deleted" event.
     */
    public function deleted(ProductBatch $batch): void
    {
        $this->clearCache($batch);

        try {
            $this->auditService->createAudit(
                model: $batch,
                action: 'deleted',
                oldValues: $batch->toArray(),
                newValues: null,
                description: $this->generateDeletionDescription($batch),
                tags: ['batch', 'inventory', 'critical']
            );
        } catch (\Exception $e) {
            Log::error('Failed to create product batch deletion audit log', [
                'tenant_id' => tenant()?->id,
                'batch_id' => $batch->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle the ProductBatch "restored" event.
     */
    public function restored(ProductBatch $batch): void
    {
        $this->clearCache($batch);

        try {
            $this->auditService->createAudit(
                model: $batch,
                action: 'restored',
                oldValues: null,
                newValues: $batch->toArray(),
                description: $this->generateRestorationDescription($batch),
                tags: ['batch', 'inventory']
            );
        } catch (\Exception $e) {
            Log::error('Failed to create product batch restoration audit log', [
                'tenant_id' => tenant()?->id,
                'batch_id' => $batch->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle batch expiration
     */
    protected function handleBatchExpiration(ProductBatch $batch): void
    {
        Log::warning('Product batch expired', [
            'tenant_id' => tenant()->id,
            'batch_id' => $batch->id,
            'batch_number' => $batch->batch_number,
            'product_id' => $batch->product_id,
            'quantity_remaining' => $batch->quantity_remaining_in_base_uom,
            'expiry_date' => $batch->expiry_date?->toDateString(),
        ]);

        // TODO: Fire BatchExpired event for notifications
        // TODO: Create expiry alert if quantity > 0
    }

    /**
     * Clear batch-related cache
     */
    protected function clearCache(ProductBatch $batch): void
    {
        Cache::tags(['tenant', tenant()->id, 'batches'])->flush();
        Cache::tags(['tenant', tenant()->id, 'inventory'])->flush();

        // Clear product cache
        if ($batch->product_id) {
            Cache::tags(['tenant', tenant()->id, 'products'])->flush();
        }
    }

    /**
     * Generate description for batch creation
     */
    private function generateCreationDescription(ProductBatch $batch): string
    {
        $user = Auth::user()?->name ?? 'System';
        $productName = $batch->product?->name ?? 'Unknown Product';
        $quantity = number_format($batch->quantity_received_in_base_uom, 2);
        $uomCode = $batch->product?->baseUom?->code ?? 'units';
        $cost = number_format($batch->total_cost, 2);

        return "{$user} received batch {$batch->batch_number} for {$productName} - {$quantity} {$uomCode} (Total: KES {$cost})";
    }

    /**
     * Generate description for batch update
     */
    private function generateUpdateDescription(ProductBatch $batch, array $changes): string
    {
        $user = Auth::user()?->name ?? 'System';
        $productName = $batch->product?->name ?? 'Unknown Product';

        // Expiry status change
        if (isset($changes['is_expired'])) {
            if ($changes['is_expired']) {
                $remaining = number_format($batch->quantity_remaining_in_base_uom, 2);
                $uomCode = $batch->product?->baseUom?->code ?? 'units';
                return "{$user} marked batch {$batch->batch_number} ({$productName}) as expired - {$remaining} {$uomCode} remaining";
            } else {
                return "{$user} unmarked batch {$batch->batch_number} ({$productName}) as expired";
            }
        }

        // Quantity change
        if (isset($changes['quantity_remaining_in_base_uom'])) {
            $oldQty = number_format($batch->getOriginal('quantity_remaining_in_base_uom'), 2);
            $newQty = number_format($changes['quantity_remaining_in_base_uom'], 2);
            $uomCode = $batch->product?->baseUom?->code ?? 'units';
            $difference = $newQty - $oldQty;
            $action = $difference > 0 ? 'increased' : 'decreased';
            $absDiff = abs($difference);

            return "{$user} {$action} batch {$batch->batch_number} ({$productName}) quantity by {$absDiff} {$uomCode} (from {$oldQty} to {$newQty})";
        }

        // Generic update
        $changedFields = implode(', ', array_keys($changes));
        return "{$user} updated batch {$batch->batch_number} ({$productName}) - {$changedFields}";
    }

    /**
     * Generate description for batch deletion
     */
    private function generateDeletionDescription(ProductBatch $batch): string
    {
        $user = Auth::user()?->name ?? 'System';
        $productName = $batch->product?->name ?? 'Unknown Product';
        $remaining = number_format($batch->quantity_remaining_in_base_uom, 2);
        $uomCode = $batch->product?->baseUom?->code ?? 'units';

        return "{$user} deleted batch {$batch->batch_number} ({$productName}) - {$remaining} {$uomCode} remaining";
    }

    /**
     * Generate description for batch restoration
     */
    private function generateRestorationDescription(ProductBatch $batch): string
    {
        $user = Auth::user()?->name ?? 'System';
        $productName = $batch->product?->name ?? 'Unknown Product';

        return "{$user} restored batch {$batch->batch_number} ({$productName})";
    }
}
