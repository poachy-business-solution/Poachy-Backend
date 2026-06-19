<?php

namespace App\Observers\Tenant;

use App\Models\Tenant\Store;
use App\Services\Tenant\AuditService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class StoreObserver
{
    public function __construct(
        private AuditService $auditService
    ) {}

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

    public function created(Store $store): void
    {
        $this->clearCache($store);

        try {
            $this->auditService->createAudit(
                model: $store,
                action: 'created',
                oldValues: null,
                newValues: $store->toArray(),
                description: $this->generateCreationDescription($store),
                tags: ['store', 'configuration']
            );
        } catch (\Exception $e) {
            Log::error('Failed to create store audit log', [
                'tenant_id' => tenant()?->id,
                'store_id' => $store->id,
                'error' => $e->getMessage(),
            ]);
        }
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
        $this->clearCache($store);

        try {
            // Check if critical fields changed
            $changes = $store->getChanges();
            $criticalFields = $store->getCriticalFields();
            $criticalChanges = array_intersect_key($changes, array_flip($criticalFields));

            if (!empty($criticalChanges)) {
                $oldValues = $store->getOriginal();

                // Generate context-aware description
                $description = $this->generateUpdateDescription($store, $criticalChanges);

                // Add specific tags based on changes
                $tags = ['store', 'configuration'];
                if (isset($criticalChanges['is_active'])) {
                    $tags[] = 'status_change';
                    $tags[] = 'critical';
                }
                if (isset($criticalChanges['is_main_store'])) {
                    $tags[] = 'main_store';
                    $tags[] = 'critical';
                }

                $this->auditService->createAudit(
                    model: $store,
                    action: 'updated',
                    oldValues: array_intersect_key($oldValues, $criticalChanges),
                    newValues: $criticalChanges,
                    description: $description,
                    tags: $tags
                );
            }
        } catch (\Exception $e) {
            Log::error('Failed to create store update audit log', [
                'tenant_id' => tenant()?->id,
                'store_id' => $store->id,
                'error' => $e->getMessage(),
            ]);
        }
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

    /**
     * Clear store-related cache
     */
    protected function clearCache(Store $store): void
    {
        $tenantId = tenant()->id;

        Cache::tags(['tenant', $tenantId, 'stores'])->flush();

        Log::debug('Store cache cleared', [
            'tenant_id' => $tenantId,
            'store_id' => $store->id,
        ]);
    }

    /**
     * Generate description for store creation
     */
    private function generateCreationDescription(Store $store): string
    {
        $user = Auth::user()?->name ?? 'System';
        $storeType = $store->is_main_store ? 'Main Store' : 'Branch';
        $location = $store->city ? " in {$store->city}" : '';

        return "{$user} created {$storeType} {$store->name} ({$store->code}){$location}";
    }

    /**
     * Generate description for store update
     */
    private function generateUpdateDescription(Store $store, array $changes): string
    {
        $user = Auth::user()?->name ?? 'System';

        // Name change
        if (isset($changes['name'])) {
            $oldName = $store->getOriginal('name');
            $newName = $changes['name'];
            return "{$user} changed store name from {$oldName} to {$newName} ({$store->code})";
        }

        // Active status change
        if (isset($changes['is_active'])) {
            $status = $changes['is_active'] ? 'activated' : 'deactivated';
            return "{$user} {$status} store {$store->name} ({$store->code})";
        }

        // Main store flag change
        if (isset($changes['is_main_store'])) {
            if ($changes['is_main_store']) {
                return "{$user} set {$store->name} ({$store->code}) as main store";
            } else {
                return "{$user} removed main store flag from {$store->name} ({$store->code})";
            }
        }

        // Manager change
        if (isset($changes['manager_id'])) {
            $oldManagerId = $store->getOriginal('manager_id');
            $newManagerId = $changes['manager_id'];

            if ($newManagerId) {
                $newManager = \App\Models\Tenant\User::find($newManagerId);
                $managerName = $newManager?->name ?? "User #{$newManagerId}";
                return "{$user} assigned {$managerName} as manager of {$store->name} ({$store->code})";
            } else {
                return "{$user} removed manager from {$store->name} ({$store->code})";
            }
        }

        // Generic update
        $changedFields = implode(', ', array_keys($changes));
        return "{$user} updated store {$store->name} ({$store->code}) - {$changedFields}";
    }
}
