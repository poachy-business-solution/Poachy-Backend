<?php

namespace App\Observers\Tenant;

use App\Models\Tenant\ProductBundle;
use App\Models\Tenant\ProductPriceHistory;
use App\Services\Tenant\AuditService;
use App\Services\Tenant\Sync\BundleSyncService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProductBundleObserver
{
    public function __construct(
        private AuditService $auditService,
        private BundleSyncService $bundleSyncService
    ) {}

    /**
     * Handle the ProductBundle "creating" event.
     */
    public function creating(ProductBundle $bundle): void
    {
        if (empty($bundle->uuid)) {
            $bundle->uuid = Str::uuid()->toString();
        }
    }

    /**
     * Handle the ProductBundle "created" event.
     */
    public function created(ProductBundle $bundle): void
    {
        $this->clearCache();

        try {
            $this->auditService->createAudit(
                model: $bundle,
                action: 'created',
                oldValues: null,
                newValues: $bundle->toArray(),
                description: $this->generateCreationDescription($bundle),
                tags: ['product_bundle']
            );
        } catch (\Exception $e) {
            Log::error('Failed to create product bundle audit log', [
                'tenant_id' => tenant()?->id,
                'bundle_id' => $bundle->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Trigger marketplace sync if bundle is available online
        if ($bundle->is_available_online) {
            try {
                if ($this->bundleSyncService->isEligibleForSync($bundle)) {
                    $this->bundleSyncService->syncToMarketplace($bundle, 'create', 3);
                }
            } catch (\Exception $e) {
                Log::error('Failed to sync new bundle to marketplace', [
                    'tenant_id' => tenant()?->id,
                    'bundle_id' => $bundle->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Handle the ProductBundle "updating" event.
     */
    public function updating(ProductBundle $bundle): void
    {
        $bundle->storeOldValuesForAudit();

        // Record price history if bundle_price changed
        if ($bundle->isDirty('bundle_price')) {
            $oldPrice = $bundle->getOriginal('bundle_price');
            $newPrice = $bundle->bundle_price;

            if ($oldPrice != $newPrice) {
                // Bundles don't have product_id in the same way, use the bundle's own context
                Log::info('Bundle price change recorded', [
                    'bundle_id' => $bundle->id,
                    'bundle_name' => $bundle->bundle_name,
                    'old_price' => $oldPrice,
                    'new_price' => $newPrice,
                    'changed_by' => Auth::id(),
                    'tenant_id' => tenant()?->id,
                ]);
            }
        }

        // Record price history if online_price changed
        if ($bundle->isDirty('online_price')) {
            $oldPrice = $bundle->getOriginal('online_price');
            $newPrice = $bundle->online_price;

            if ($oldPrice != $newPrice) {
                Log::info('Bundle online price change recorded', [
                    'bundle_id' => $bundle->id,
                    'bundle_name' => $bundle->bundle_name,
                    'old_price' => $oldPrice,
                    'new_price' => $newPrice,
                    'changed_by' => Auth::id(),
                    'tenant_id' => tenant()?->id,
                ]);
            }
        }
    }

    /**
     * Handle the ProductBundle "updated" event.
     */
    public function updated(ProductBundle $bundle): void
    {
        $this->clearCache();

        try {
            if ($this->auditService->hasCriticalChanges($bundle)) {
                $oldValues = $bundle->getOldValuesForAudit();
                $criticalChanges = $bundle->getCriticalChanges();

                $description = $this->generateUpdateDescription($bundle, $criticalChanges);

                $tags = ['product_bundle'];
                if (isset($criticalChanges['bundle_price']) || isset($criticalChanges['online_price'])) {
                    $tags[] = 'price_change';
                    $tags[] = 'critical';
                }
                if (isset($criticalChanges['is_available_online'])) {
                    $tags[] = 'marketplace';
                }

                $this->auditService->createAudit(
                    model: $bundle,
                    action: 'updated',
                    oldValues: array_intersect_key($oldValues, $criticalChanges),
                    newValues: $criticalChanges,
                    description: $description,
                    tags: $tags
                );
            }
        } catch (\Exception $e) {
            Log::error('Failed to create product bundle update audit log', [
                'tenant_id' => tenant()?->id,
                'bundle_id' => $bundle->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Handle marketplace sync based on changes
        $this->handleMarketplaceSync($bundle);
    }

    /**
     * Handle the ProductBundle "deleted" event.
     */
    public function deleted(ProductBundle $bundle): void
    {
        $this->clearCache();

        try {
            $this->auditService->createAudit(
                model: $bundle,
                action: 'deleted',
                oldValues: $bundle->toArray(),
                newValues: null,
                description: $this->generateDeletionDescription($bundle),
                tags: ['product_bundle', 'critical']
            );
        } catch (\Exception $e) {
            Log::error('Failed to create product bundle deletion audit log', [
                'tenant_id' => tenant()?->id,
                'bundle_id' => $bundle->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Remove from marketplace if was available online
        if ($bundle->is_available_online) {
            try {
                $this->bundleSyncService->syncToMarketplace($bundle, 'delete', 3);
            } catch (\Exception $e) {
                Log::error('Failed to remove deleted bundle from marketplace', [
                    'tenant_id' => tenant()?->id,
                    'bundle_id' => $bundle->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Handle the ProductBundle "restored" event.
     */
    public function restored(ProductBundle $bundle): void
    {
        $this->clearCache();

        try {
            $this->auditService->createAudit(
                model: $bundle,
                action: 'restored',
                oldValues: null,
                newValues: $bundle->toArray(),
                description: $this->generateRestorationDescription($bundle),
                tags: ['product_bundle']
            );
        } catch (\Exception $e) {
            Log::error('Failed to create product bundle restoration audit log', [
                'tenant_id' => tenant()?->id,
                'bundle_id' => $bundle->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Re-sync to marketplace if eligible
        if ($this->bundleSyncService->isEligibleForSync($bundle)) {
            try {
                $this->bundleSyncService->syncToMarketplace($bundle, 'activate', 3);
            } catch (\Exception $e) {
                Log::error('Failed to re-sync restored bundle to marketplace', [
                    'tenant_id' => tenant()?->id,
                    'bundle_id' => $bundle->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Handle marketplace sync based on bundle changes
     */
    private function handleMarketplaceSync(ProductBundle $bundle): void
    {
        try {
            // Bundle just made available online
            if ($bundle->wasChanged('is_available_online') && $bundle->is_available_online) {
                if ($this->bundleSyncService->isEligibleForSync($bundle)) {
                    $this->bundleSyncService->syncToMarketplace($bundle, 'create', 3);
                } else {
                    Log::warning('Bundle not eligible for marketplace sync', [
                        'tenant_id' => tenant()->id,
                        'bundle_id' => $bundle->id,
                        'errors' => $this->bundleSyncService->getSyncValidationErrors($bundle),
                    ]);
                }
                return;
            }

            // Bundle removed from marketplace
            if ($bundle->wasChanged('is_available_online') && !$bundle->is_available_online) {
                $this->bundleSyncService->syncToMarketplace($bundle, 'deactivate', 3);
                return;
            }

            // Bundle already online - handle status and content changes
            if ($bundle->is_available_online) {

                // Handle is_active status change
                if ($bundle->wasChanged('is_active')) {
                    $action = $bundle->is_active ? 'activate' : 'deactivate';
                    $this->bundleSyncService->syncToMarketplace($bundle, $action, 3);
                    return;
                }

                // Other marketplace-relevant fields
                $marketplaceRelevantFields = [
                    'bundle_name',
                    'bundle_sku',
                    'online_price',
                    'online_description',
                    'bundle_price',
                    'images',
                    'calculated_individual_price',
                    'discount_amount',
                    'tax_rate_id',
                ];

                $hasRelevantChanges = collect($marketplaceRelevantFields)
                    ->some(fn ($field) => $bundle->wasChanged($field));

                if ($hasRelevantChanges) {
                    $priority = match (true) {
                        $bundle->wasChanged('online_price') => 3,
                        $bundle->wasChanged('bundle_price') => 3,
                        default => 5,
                    };

                    $this->bundleSyncService->syncToMarketplace($bundle, 'update', $priority);
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to handle bundle marketplace sync', [
                'tenant_id' => tenant()?->id,
                'bundle_id' => $bundle->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Clear bundle-related cache
     */
    protected function clearCache(): void
    {
        Cache::tags(['tenant', tenant()->id, 'products'])->flush();
        Cache::tags(['tenant', tenant()->id, 'product_bundles'])->flush();
    }

    /**
     * Generate description for bundle creation
     */
    private function generateCreationDescription(ProductBundle $bundle): string
    {
        $user = Auth::user()?->name ?? 'System';
        $price = number_format($bundle->bundle_price, 2);

        return "{$user} created bundle {$bundle->bundle_name} (SKU: {$bundle->bundle_sku}) - KES {$price}";
    }

    /**
     * Generate description for bundle update
     */
    private function generateUpdateDescription(ProductBundle $bundle, array $changes): string
    {
        $user = Auth::user()?->name ?? 'System';

        if (isset($changes['bundle_price'])) {
            $oldPrice = number_format($bundle->getOriginal('bundle_price'), 2);
            $newPrice = number_format($changes['bundle_price'], 2);
            return "{$user} changed bundle {$bundle->bundle_name} price from KES {$oldPrice} to KES {$newPrice}";
        }

        if (isset($changes['online_price'])) {
            $oldPrice = $bundle->getOriginal('online_price') ? number_format($bundle->getOriginal('online_price'), 2) : 'N/A';
            $newPrice = number_format($changes['online_price'], 2);
            return "{$user} changed bundle {$bundle->bundle_name} online price from KES {$oldPrice} to KES {$newPrice}";
        }

        if (isset($changes['is_available_online'])) {
            $status = $changes['is_available_online'] ? 'enabled' : 'disabled';
            return "{$user} {$status} online availability for bundle {$bundle->bundle_name}";
        }

        if (isset($changes['is_active'])) {
            $status = $changes['is_active'] ? 'activated' : 'deactivated';
            return "{$user} {$status} bundle {$bundle->bundle_name}";
        }

        $changedFields = implode(', ', array_keys($changes));
        return "{$user} updated bundle {$bundle->bundle_name} ({$changedFields})";
    }

    /**
     * Generate description for bundle deletion
     */
    private function generateDeletionDescription(ProductBundle $bundle): string
    {
        $user = Auth::user()?->name ?? 'System';
        $price = number_format($bundle->bundle_price, 2);

        return "{$user} deleted bundle {$bundle->bundle_name} (SKU: {$bundle->bundle_sku}, Price: KES {$price})";
    }

    /**
     * Generate description for bundle restoration
     */
    private function generateRestorationDescription(ProductBundle $bundle): string
    {
        $user = Auth::user()?->name ?? 'System';

        return "{$user} restored bundle {$bundle->bundle_name} (SKU: {$bundle->bundle_sku})";
    }
}
