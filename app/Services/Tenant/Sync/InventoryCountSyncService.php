<?php

namespace App\Services\Tenant\Sync;

use App\Events\Tenant\InventoryCountMarketplaceSyncRequested;
use App\Models\Tenant\Inventory;
use Illuminate\Support\Facades\Log;

class InventoryCountSyncService
{
    /**
     * Trigger a marketplace inventory count sync for the given inventory record.
     */
    public function syncToMarketplace(Inventory $inventory, string $action = 'update', int $priority = 3): void
    {
        try {
            event(new InventoryCountMarketplaceSyncRequested($inventory, $action, $priority));

            Log::info('InventoryCount marketplace sync triggered', [
                'tenant_id' => tenant()->id,
                'product_id' => $inventory->product_id,
                'variant_id' => $inventory->product_variant_id,
                'action' => $action,
                'priority' => $priority,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to trigger InventoryCount marketplace sync', [
                'tenant_id' => tenant()->id,
                'product_id' => $inventory->product_id,
                'variant_id' => $inventory->product_variant_id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
