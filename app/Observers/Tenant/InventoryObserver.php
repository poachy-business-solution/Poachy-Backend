<?php

namespace App\Observers\Tenant;

use App\Models\Tenant\Inventory;
use App\Services\Tenant\Inventory\InventoryService;
use App\Services\Tenant\Inventory\StockAlertService;
use App\Services\Tenant\Sync\InventoryCountSyncService;
use Illuminate\Support\Facades\Log;

class InventoryObserver
{
    public function __construct(
        private InventoryService $inventoryService,
        private StockAlertService $stockAlertService,
        private InventoryCountSyncService $inventoryCountSyncService
    ) {}

    /**
     * Handle the Inventory "updated" event.
     * Clear cache when inventory changes
     */
    public function updated(Inventory $inventory): void
    {
        try {
            // Clear inventory cache for this store
            $this->inventoryService->clearCache($inventory->store_id);

            // Check and generate stock alerts (event-driven)
            // This will create/update alerts or auto-resolve them
            $this->stockAlertService->checkAndGenerateAlert($inventory);
        } catch (\Exception $e) {
            Log::error('Failed to clear inventory cache', [
                'inventory_id' => $inventory->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Sync inventory count to central marketplace (independent of cache/alert logic)
        try {
            $inventory->loadMissing('product');

            if ($inventory->product?->is_available_online && $inventory->product?->is_active) {
                $this->inventoryCountSyncService->syncToMarketplace($inventory);
            }
        } catch (\Exception $e) {
            Log::error('Failed to trigger inventory count sync to marketplace', [
                'inventory_id' => $inventory->id,
                'product_id' => $inventory->product_id,
                'variant_id' => $inventory->product_variant_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle the Inventory "created" event.
     */
    public function created(Inventory $inventory): void
    {
        try {
            // Clear cache when new inventory record created
            $this->inventoryService->clearCache($inventory->store_id);

            // Check for stock alerts on new inventory
            $this->stockAlertService->checkAndGenerateAlert($inventory);
        } catch (\Exception $e) {
            Log::error('Failed to process inventory creation', [
                'inventory_id' => $inventory->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle the Inventory "deleted" event.
     */
    public function deleted(Inventory $inventory): void
    {
        try {
            // Clear cache when inventory deleted
            $this->inventoryService->clearCache($inventory->store_id);
        } catch (\Exception $e) {
            Log::error('Failed to process inventory deletion', [
                'inventory_id' => $inventory->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
