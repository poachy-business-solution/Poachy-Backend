<?php

namespace App\Observers\Tenant;

use App\Models\Tenant\StockTransfer;
use App\Services\Tenant\AuditService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class StockTransferObserver
{
    public function __construct(
        private AuditService $auditService
    ) {}

    /**
     * Handle the StockTransfer "creating" event.
     */
    public function creating(StockTransfer $transfer): void
    {
        // Set requester if not already set
        if (!$transfer->requested_by && Auth::check()) {
            $transfer->requested_by = Auth::id();
        }
    }

    /**
     * Handle the StockTransfer "created" event.
     */
    public function created(StockTransfer $transfer): void
    {
        $this->clearCache($transfer);

        try {
            // Use aggregated audit to include transfer items
            $aggregatedData = $this->auditService->getAggregatedData($transfer);

            $this->auditService->createAggregatedAudit(
                model: $transfer,
                action: 'created',
                aggregatedData: $aggregatedData,
                description: $this->generateCreationDescription($transfer),
                tags: ['stock_transfer', 'inventory']
            );
        } catch (\Exception $e) {
            Log::error('Failed to create stock transfer audit log', [
                'tenant_id' => tenant()?->id,
                'transfer_id' => $transfer->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Fire event for pending transfer
        if ($transfer->status === 'pending') {
            // TODO: Fire StockTransferCreatedPendingApproval event
            Log::info('Stock transfer created pending approval', [
                'tenant_id' => tenant()->id,
                'transfer_id' => $transfer->id,
                'transfer_number' => $transfer->transfer_number,
                'from_store_id' => $transfer->from_store_id,
                'to_store_id' => $transfer->to_store_id,
            ]);
        }
    }

    /**
     * Handle the StockTransfer "updating" event.
     */
    public function updating(StockTransfer $transfer): void
    {
        // Store old values for audit comparison
        $transfer->storeOldValuesForAudit();
    }

    /**
     * Handle the StockTransfer "updated" event.
     */
    public function updated(StockTransfer $transfer): void
    {
        $this->clearCache($transfer);

        try {
            // Stock transfer uses full audit mode - always log updates
            if ($transfer->wasChanged()) {
                $oldValues = $transfer->getOldValuesForAudit();
                $changes = $transfer->getChanges();

                // For status changes, include aggregated data
                if (isset($changes['status'])) {
                    $aggregatedData = $this->auditService->getAggregatedData($transfer);

                    $this->auditService->createAggregatedAudit(
                        model: $transfer,
                        action: 'updated',
                        aggregatedData: array_merge(
                            ['old_status' => $oldValues['status'] ?? null],
                            $aggregatedData
                        ),
                        description: $this->generateUpdateDescription($transfer, $changes),
                        tags: $this->generateUpdateTags($changes)
                    );
                } else {
                    // Regular update without aggregation
                    $this->auditService->createAudit(
                        model: $transfer,
                        action: 'updated',
                        oldValues: $oldValues,
                        newValues: $changes,
                        description: $this->generateUpdateDescription($transfer, $changes),
                        tags: $this->generateUpdateTags($changes)
                    );
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to create stock transfer update audit log', [
                'tenant_id' => tenant()?->id,
                'transfer_id' => $transfer->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Handle status changes
        $changes = $transfer->getChanges();
        if (isset($changes['status'])) {
            $this->handleStatusChange($transfer, $changes);
        }
    }

    /**
     * Handle the StockTransfer "deleted" event.
     */
    public function deleted(StockTransfer $transfer): void
    {
        $this->clearCache($transfer);

        try {
            // Include aggregated data in deletion audit
            $aggregatedData = $this->auditService->getAggregatedData($transfer);

            $this->auditService->createAggregatedAudit(
                model: $transfer,
                action: 'deleted',
                aggregatedData: $aggregatedData,
                description: $this->generateDeletionDescription($transfer),
                tags: ['stock_transfer', 'inventory', 'critical']
            );
        } catch (\Exception $e) {
            Log::error('Failed to create stock transfer deletion audit log', [
                'tenant_id' => tenant()?->id,
                'transfer_id' => $transfer->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle the StockTransfer "restored" event.
     */
    public function restored(StockTransfer $transfer): void
    {
        $this->clearCache($transfer);

        try {
            $aggregatedData = $this->auditService->getAggregatedData($transfer);

            $this->auditService->createAggregatedAudit(
                model: $transfer,
                action: 'restored',
                aggregatedData: $aggregatedData,
                description: $this->generateRestorationDescription($transfer),
                tags: ['stock_transfer', 'inventory']
            );
        } catch (\Exception $e) {
            Log::error('Failed to create stock transfer restoration audit log', [
                'tenant_id' => tenant()?->id,
                'transfer_id' => $transfer->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle status changes
     */
    protected function handleStatusChange(StockTransfer $transfer, array $changes): void
    {
        $oldStatus = $changes['status'];
        $newStatus = $transfer->status;

        Log::info('Stock transfer status changed', [
            'tenant_id' => tenant()->id,
            'transfer_id' => $transfer->id,
            'transfer_number' => $transfer->transfer_number,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'from_store_id' => $transfer->from_store_id,
            'to_store_id' => $transfer->to_store_id,
        ]);

        switch ($newStatus) {
            case 'approved':
                // TODO: Fire StockTransferApproved event
                break;
            case 'in_transit':
                // TODO: Fire StockTransferInTransit event
                break;
            case 'completed':
                // TODO: Fire StockTransferCompleted event
                // This will trigger inventory updates
                break;
            case 'cancelled':
                // TODO: Fire StockTransferCancelled event
                break;
        }
    }

    /**
     * Generate tags based on changes
     */
    protected function generateUpdateTags(array $changes): array
    {
        $tags = ['stock_transfer', 'inventory'];

        if (isset($changes['status'])) {
            $tags[] = 'status_change';
            $tags[] = 'workflow';
            $tags[] = 'critical';
        }

        if (isset($changes['approved_at']) || isset($changes['sent_at']) || isset($changes['received_at'])) {
            $tags[] = 'milestone';
        }

        return $tags;
    }

    /**
     * Clear transfer-related cache
     */
    protected function clearCache(StockTransfer $transfer): void
    {
        Cache::tags(['tenant', tenant()->id, 'stock_transfers'])->flush();
        Cache::tags(['tenant', tenant()->id, 'inventory'])->flush();

        // Clear store cache for both stores
        if ($transfer->from_store_id) {
            Cache::tags(['tenant', tenant()->id, 'stores'])->flush();
        }
        if ($transfer->to_store_id) {
            Cache::tags(['tenant', tenant()->id, 'stores'])->flush();
        }
    }

    /**
     * Generate description for transfer creation
     */
    private function generateCreationDescription(StockTransfer $transfer): string
    {
        $user = Auth::user()?->name ?? 'System';
        $fromStore = $transfer->fromStore?->name ?? "Store #{$transfer->from_store_id}";
        $toStore = $transfer->toStore?->name ?? "Store #{$transfer->to_store_id}";
        $itemCount = $transfer->items()->count();

        return "{$user} created stock transfer {$transfer->transfer_number} from {$fromStore} to {$toStore} ({$itemCount} items)";
    }

    /**
     * Generate description for transfer update
     */
    private function generateUpdateDescription(StockTransfer $transfer, array $changes): string
    {
        $user = Auth::user()?->name ?? 'System';
        $fromStore = $transfer->fromStore?->name ?? "Store #{$transfer->from_store_id}";
        $toStore = $transfer->toStore?->name ?? "Store #{$transfer->to_store_id}";

        // Status change
        if (isset($changes['status'])) {
            $oldStatus = $transfer->getOriginal('status');
            $newStatus = $changes['status'];

            $statusMessages = [
                'approved' => "{$user} approved stock transfer {$transfer->transfer_number} from {$fromStore} to {$toStore}",
                'in_transit' => "{$user} marked stock transfer {$transfer->transfer_number} as in transit from {$fromStore} to {$toStore}",
                'completed' => "{$user} completed stock transfer {$transfer->transfer_number} from {$fromStore} to {$toStore}",
                'cancelled' => "{$user} cancelled stock transfer {$transfer->transfer_number} from {$fromStore} to {$toStore}",
            ];

            return $statusMessages[$newStatus] ?? "{$user} changed stock transfer {$transfer->transfer_number} status from {$oldStatus} to {$newStatus}";
        }

        // Expected arrival date change
        if (isset($changes['expected_arrival_date'])) {
            $oldDate = $transfer->getOriginal('expected_arrival_date');
            $newDate = $changes['expected_arrival_date'];
            return "{$user} changed expected arrival date for transfer {$transfer->transfer_number} from {$oldDate} to {$newDate}";
        }

        // Actual arrival date
        if (isset($changes['actual_arrival_date'])) {
            $date = $changes['actual_arrival_date'];
            return "{$user} recorded actual arrival date as {$date} for transfer {$transfer->transfer_number}";
        }

        // Generic update
        $changedFields = implode(', ', array_keys($changes));
        return "{$user} updated stock transfer {$transfer->transfer_number} ({$changedFields})";
    }

    /**
     * Generate description for transfer deletion
     */
    private function generateDeletionDescription(StockTransfer $transfer): string
    {
        $user = Auth::user()?->name ?? 'System';
        $fromStore = $transfer->fromStore?->name ?? "Store #{$transfer->from_store_id}";
        $toStore = $transfer->toStore?->name ?? "Store #{$transfer->to_store_id}";
        $itemCount = $transfer->items()->count();

        return "{$user} deleted stock transfer {$transfer->transfer_number} from {$fromStore} to {$toStore} (Status: {$transfer->status}, Items: {$itemCount})";
    }

    /**
     * Generate description for transfer restoration
     */
    private function generateRestorationDescription(StockTransfer $transfer): string
    {
        $user = Auth::user()?->name ?? 'System';
        $fromStore = $transfer->fromStore?->name ?? "Store #{$transfer->from_store_id}";
        $toStore = $transfer->toStore?->name ?? "Store #{$transfer->to_store_id}";

        return "{$user} restored stock transfer {$transfer->transfer_number} from {$fromStore} to {$toStore}";
    }
}
