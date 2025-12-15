<?php

namespace App\Observers\Tenant;

use App\Models\Tenant\Store;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class StoreObserver
{
    // Handle store creation event
    public function creating(Store $store): void
    {
        if (empty($store->code)) {
            $store->code = Store::generateUniqueCode();
        }

        if (Auth::check() && empty($store->created_by)) {
            $store->created_by = Auth::id();
        }

        if (!Store::withTrashed()->exists()) {
            $store->is_main_store = true;
        }

        Log::info('Creating store', [
            'tenant_id' => tenant()->id,
            'store_code' => $store->code,
            'store_name' => $store->name,
            'created_by' => $store->created_by,
        ]);
    }

    // Handle store update event
    public function updating(Store $store): void
    {
        if (Auth::check()) {
            $store->updated_by = Auth::id();
        }

        // Prevent removing main store flag if it's the only store
        if ($store->isDirty('is_main_store') && !$store->is_main_store) {
            $mainStoreCount = Store::where('is_main_store', true)
                ->where('id', '!=', $store->id)
                ->count();

            if ($mainStoreCount === 0) {
                Log::warning('Attempted to remove main store flag from only store', [
                    'tenant_id' => tenant()->id,
                    'store_id' => $store->id,
                ]);
                // Revert the change
                $store->is_main_store = true;
            }
        }

        Log::info('Updating store', [
            'tenant_id' => tenant()->id,
            'store_id' => $store->id,
            'store_code' => $store->code,
            'updated_by' => $store->updated_by,
            'changes' => $store->getDirty(),
        ]);
    }

    // Handle store update event
    public function updated(Store $store): void
    {
        Log::info('Store updated successfully', [
            'tenant_id' => tenant()->id,
            'store_id' => $store->id,
            'store_code' => $store->code,
        ]);

        // Clear tenant cache for stores
        $this->clearStoreCache($store);
    }

    // Clear tenant-specific store cache
    protected function clearStoreCache(Store $store): void
    {
        $tenantId = tenant()->id;

        cache()->tags(['tenant', $tenantId, 'stores'])->flush();

        Log::debug('Store cache cleared', [
            'tenant_id' => $tenantId,
            'store_id' => $store->id,
        ]);
    }
}
