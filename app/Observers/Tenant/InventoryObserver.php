<?php

namespace App\Observers\Tenant;

use App\Models\Tenant\Inventory;
use App\Services\Tenant\Inventory\InventoryService;
use Illuminate\Support\Facades\Log;

class InventoryObserver
{
    public function __construct(
        private InventoryService $inventoryService
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
        } catch (\Exception $e) {
            Log::error('Failed to clear inventory cache', [
                'inventory_id' => $inventory->id,
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
