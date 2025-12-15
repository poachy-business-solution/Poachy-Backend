<?php

namespace App\Services\Tenant\Store;

use App\Models\Tenant\Store;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StoreService
{
    // Get paginated list of stores with optional filtering.
    public function getStores(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Store::query()->with(['manager:id,name,email']);

        // Apply filters
        if (!empty($filters['search'])) {
            $query->search($filters['search']);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        if (isset($filters['is_main_store'])) {
            if (filter_var($filters['is_main_store'], FILTER_VALIDATE_BOOLEAN)) {
                $query->mainStore();
            } else {
                $query->branches();
            }
        }

        if (!empty($filters['city'])) {
            $query->byCity($filters['city']);
        }

        if (!empty($filters['region'])) {
            $query->byRegion($filters['region']);
        }

        if (!empty($filters['manager_id'])) {
            $query->where('manager_id', $filters['manager_id']);
        }

        // Apply sorting
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        // Execute query with pagination
        return $query->paginate($perPage);
    }

    // Get all active stores (without pagination).
    public function getActiveStores(): Collection
    {
        return Store::active()
            ->with(['manager:id,name,email'])
            ->orderBy('name')
            ->get();
    }

    // Get the main store.
    public function getMainStore(): ?Store
    {
        return Store::mainStore()->first();
    }

    // Get a single store by ID.
    public function getStoreById(int $id): ?Store
    {
        return Store::with([
            'manager:id,name,email,phone',
            'creator:id,name',
            'updater:id,name'
        ])->find($id);
    }

    // Get a single store by code.
    public function getStoreByCode(string $code): ?Store
    {
        return Store::where('code', $code)
            ->with(['manager:id,name,email'])
            ->first();
    }

    // Create a new store.
    public function createStore(array $data): Store
    {
        return DB::transaction(function () use ($data) {
            $store = Store::create($data);

            $this->clearStoresCache();
            return $store->fresh(['manager', 'creator']);
        });
    }

    // Update an existing store.
    public function updateStoreDetails(Store $store, array $data): Store
    {
        return DB::transaction(function () use ($store, $data) {
            if (Auth::check()) {
                $data['updated_by'] = Auth::id();
            }

            $store->update($data);

            $this->clearStoresCache();
            return $store->fresh(['manager', 'creator', 'updater']);
        });
    }

    // Activate a store
    public function activateStore(Store $store): bool
    {
        return DB::transaction(function () use ($store) {
            $result = $store->activate();

            if ($result) {
                $this->clearStoresCache();
            }

            return $result;
        });
    }

    // Deactivate a store
    public function deactivateStore(Store $store): bool
    {
        return DB::transaction(function () use ($store) {
            try {
                $result = $store->deactivate();

                if ($result) {
                    $this->clearStoresCache();
                }

                return $result;
            } catch (\RuntimeException $e) {
                Log::warning('Failed to deactivate store', [
                    'tenant_id' => tenant()->id,
                    'store_id' => $store->id,
                    'reason' => $e->getMessage(),
                ]);

                throw $e;
            }
        });
    }

    // Set a store as the main store
    public function setStoreAsMain(Store $store): bool
    {
        return DB::transaction(function () use ($store) {
            $result = $store->setAsMainStore();

            if ($result) {
                $this->clearStoresCache();
            }

            return $result;
        });
    }

    // Assign manager to a store
    public function assignManagerToStore(Store $store, int $managerId): bool
    {
        return DB::transaction(function () use ($store, $managerId) {
            $store->manager_id = $managerId;

            if (Auth::check()) {
                $store->updated_by = Auth::id();
            }

            $result = $store->save();

            if ($result) {
                $this->clearStoresCache();
            }

            return $result;
        });
    }


    // Remove manager from a store
    public function removeManagerFromStore(Store $store): bool
    {
        return DB::transaction(function () use ($store) {
            $store->manager_id = null;

            if (Auth::check()) {
                $store->updated_by = Auth::id();
            }

            $result = $store->save();

            if ($result) {
                $this->clearStoresCache();
            }

            return $result;
        });
    }

    // Clear stores cache
    protected function clearStoresCache(): void
    {
        $tenantId = tenant()->id;

        cache()->tags(['tenant', $tenantId, 'stores'])->flush();
    }
}
