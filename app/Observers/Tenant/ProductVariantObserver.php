<?php

namespace App\Observers\Tenant;

use App\Models\Tenant\ProductPriceHistory;
use App\Models\Tenant\ProductVariant;
use App\Services\Tenant\AuditService;
use App\Services\Tenant\Sync\VariantSyncService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProductVariantObserver
{
    public function __construct(
        private AuditService $auditService,
        private VariantSyncService $variantSyncService
    ) {}

    /**
     * Handle the ProductVariant "creating" event.
     */
    public function creating(ProductVariant $variant): void
    {
        if (empty($variant->uuid)) {
            $variant->uuid = Str::uuid()->toString();
        }
    }

    /**
     * Handle the ProductVariant "created" event.
     */
    public function created(ProductVariant $variant): void
    {
        $this->clearCache($variant);

        try {
            $this->auditService->createAudit(
                model: $variant,
                action: 'created',
                oldValues: null,
                newValues: $variant->toArray(),
                description: $this->generateCreationDescription($variant),
                tags: ['product_variant', 'inventory']
            );
        } catch (\Exception $e) {
            Log::error('Failed to create product variant audit log', [
                'tenant_id' => tenant()?->id,
                'variant_id' => $variant->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Sync to marketplace if variant is eligible
        if ($variant->isAvailableOnline() && $variant->product?->is_available_online) {
            try {
                if ($this->variantSyncService->isEligibleForSync($variant)) {
                    $this->variantSyncService->syncToMarketplace($variant, 'create', 3);
                }
            } catch (\Exception $e) {
                Log::error('Failed to sync new variant to marketplace', [
                    'tenant_id' => tenant()?->id,
                    'variant_id' => $variant->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Handle the ProductVariant "updating" event.
     */
    public function updating(ProductVariant $variant): void
    {
        $variant->storeOldValuesForAudit();

        // Check if variant price changed
        if ($variant->isDirty('variant_price')) {
            $oldPrice = $variant->getOriginal('variant_price');
            $newPrice = $variant->variant_price;

            if ($oldPrice != $newPrice) {
                ProductPriceHistory::create([
                    'product_id' => $variant->product_id,
                    'product_variant_id' => $variant->id,
                    'old_selling_price' => $oldPrice,
                    'new_selling_price' => $newPrice,
                    'base_uom_id' => $variant->uom_id,
                    'change_reason' => 'manual',
                    'changed_by' => Auth::id() ?? 1,
                    'effective_from' => now(),
                ]);
            }
        }

        // Check if variant online price changed
        if ($variant->isDirty('online_price')) {
            $oldPrice = $variant->getOriginal('online_price');
            $newPrice = $variant->online_price;

            if ($oldPrice != $newPrice) {
                ProductPriceHistory::create([
                    'product_id' => $variant->product_id,
                    'product_variant_id' => $variant->id,
                    'old_selling_price' => $oldPrice,
                    'new_selling_price' => $newPrice,
                    'base_uom_id' => $variant->uom_id,
                    'change_reason' => 'manual',
                    'changed_by' => Auth::id() ?? 1,
                    'effective_from' => now(),
                ]);
            }
        }
    }

    /**
     * Handle the ProductVariant "updated" event.
     */
    public function updated(ProductVariant $variant): void
    {
        $this->clearCache($variant);

        try {
            if ($this->auditService->hasCriticalChanges($variant)) {
                $oldValues = $variant->getOldValuesForAudit();
                $criticalChanges = $variant->getCriticalChanges();

                $description = $this->generateUpdateDescription($variant, $criticalChanges);

                $tags = ['product_variant', 'inventory'];
                if (isset($criticalChanges['variant_price']) || isset($criticalChanges['base_selling_price_adjustment'])) {
                    $tags[] = 'price_change';
                    $tags[] = 'critical';
                }
                if (isset($criticalChanges['stock_status'])) {
                    $tags[] = 'stock_status';
                }
                if (isset($criticalChanges['online_price'])) {
                    $tags[] = 'marketplace';
                }

                $this->auditService->createAudit(
                    model: $variant,
                    action: 'updated',
                    oldValues: array_intersect_key($oldValues, $criticalChanges),
                    newValues: $criticalChanges,
                    description: $description,
                    tags: $tags
                );
            }
        } catch (\Exception $e) {
            Log::error('Failed to create product variant update audit log', [
                'tenant_id' => tenant()?->id,
                'variant_id' => $variant->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Handle marketplace sync based on changes
        $this->handleMarketplaceSync($variant);
    }

    /**
     * Handle the ProductVariant "deleted" event.
     */
    public function deleted(ProductVariant $variant): void
    {
        $this->clearCache($variant);

        try {
            $this->auditService->createAudit(
                model: $variant,
                action: 'deleted',
                oldValues: $variant->toArray(),
                newValues: null,
                description: $this->generateDeletionDescription($variant),
                tags: ['product_variant', 'inventory', 'critical']
            );
        } catch (\Exception $e) {
            Log::error('Failed to create product variant deletion audit log', [
                'tenant_id' => tenant()?->id,
                'variant_id' => $variant->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Remove from marketplace if was available online
        if ($variant->getOriginal('online_price') && $variant->product?->is_available_online) {
            try {
                $this->variantSyncService->syncToMarketplace($variant, 'delete', 3);
            } catch (\Exception $e) {
                Log::error('Failed to remove deleted variant from marketplace', [
                    'tenant_id' => tenant()?->id,
                    'variant_id' => $variant->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Handle the ProductVariant "restored" event.
     */
    public function restored(ProductVariant $variant): void
    {
        $this->clearCache($variant);

        try {
            $this->auditService->createAudit(
                model: $variant,
                action: 'restored',
                oldValues: null,
                newValues: $variant->toArray(),
                description: $this->generateRestorationDescription($variant),
                tags: ['product_variant', 'inventory']
            );
        } catch (\Exception $e) {
            Log::error('Failed to create product variant restoration audit log', [
                'tenant_id' => tenant()?->id,
                'variant_id' => $variant->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Re-sync to marketplace if eligible
        if ($this->variantSyncService->isEligibleForSync($variant)) {
            try {
                $this->variantSyncService->syncToMarketplace($variant, 'activate', 3);
            } catch (\Exception $e) {
                Log::error('Failed to re-sync restored variant to marketplace', [
                    'tenant_id' => tenant()?->id,
                    'variant_id' => $variant->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Handle marketplace sync based on variant changes
     */
    private function handleMarketplaceSync(ProductVariant $variant): void
    {
        try {
            $variant->loadMissing('product');
            $parentOnline = $variant->product?->is_available_online;

            // Variant became eligible (online_price set while active and parent online)
            $onlinePriceAdded = $variant->wasChanged('online_price')
                && $variant->online_price !== null
                && $variant->getOriginal('online_price') === null;

            if ($onlinePriceAdded && $variant->is_active && $parentOnline) {
                if ($this->variantSyncService->isEligibleForSync($variant)) {
                    $this->variantSyncService->syncToMarketplace($variant, 'create', 3);
                }
                return;
            }

            // Variant online_price removed → deactivate
            $onlinePriceRemoved = $variant->wasChanged('online_price')
                && $variant->online_price === null
                && $variant->getOriginal('online_price') !== null;

            if ($onlinePriceRemoved && $parentOnline) {
                $this->variantSyncService->syncToMarketplace($variant, 'deactivate', 3);
                return;
            }

            // is_active changed
            if ($variant->wasChanged('is_active') && $parentOnline && $variant->online_price !== null) {
                $action = $variant->is_active ? 'activate' : 'deactivate';
                $this->variantSyncService->syncToMarketplace($variant, $action, 3);
                return;
            }

            // Other marketplace-relevant field changes while eligible
            if ($parentOnline && $variant->isAvailableOnline()) {
                $marketplaceRelevantFields = [
                    'variant_name',
                    'sku',
                    'online_price',
                    'variant_price',
                    'stock_status',
                    'attributes',
                    'base_selling_price_adjustment',
                ];

                $hasRelevantChanges = collect($marketplaceRelevantFields)
                    ->some(fn ($field) => $variant->wasChanged($field));

                if ($hasRelevantChanges) {
                    $priority = match (true) {
                        $variant->wasChanged('online_price') => 3,
                        $variant->wasChanged('variant_price') => 3,
                        default => 5,
                    };

                    $this->variantSyncService->syncToMarketplace($variant, 'update', $priority);
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to handle variant marketplace sync', [
                'tenant_id' => tenant()?->id,
                'variant_id' => $variant->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Clear variant-related cache
     */
    protected function clearCache(ProductVariant $variant): void
    {
        Cache::tags(['tenant', tenant()->id, 'products'])->flush();
        Cache::tags(['tenant', tenant()->id, 'product_variants'])->flush();
    }

    /**
     * Generate description for variant creation
     */
    private function generateCreationDescription(ProductVariant $variant): string
    {
        $user = Auth::user()?->name ?? 'System';
        $productName = $variant->product?->name ?? 'Unknown Product';
        $price = $variant->variant_price ? number_format($variant->variant_price, 2) : 'N/A';

        return "{$user} created variant {$variant->variant_name} for product {$productName} (SKU: {$variant->sku}) - KES {$price}";
    }

    /**
     * Generate description for variant update
     */
    private function generateUpdateDescription(ProductVariant $variant, array $changes): string
    {
        $user = Auth::user()?->name ?? 'System';
        $displayName = $variant->display_name;

        if (isset($changes['variant_price'])) {
            $oldPrice = $variant->getOriginal('variant_price') ? number_format($variant->getOriginal('variant_price'), 2) : 'N/A';
            $newPrice = number_format($changes['variant_price'], 2);
            return "{$user} changed variant {$displayName} price from KES {$oldPrice} to KES {$newPrice}";
        }

        if (isset($changes['base_selling_price_adjustment'])) {
            $oldAdj = number_format($variant->getOriginal('base_selling_price_adjustment'), 2);
            $newAdj = number_format($changes['base_selling_price_adjustment'], 2);
            return "{$user} changed variant {$displayName} price adjustment from KES {$oldAdj} to KES {$newAdj}";
        }

        if (isset($changes['stock_status'])) {
            $oldStatus = $variant->getOriginal('stock_status');
            $newStatus = $changes['stock_status'];
            return "{$user} changed variant {$displayName} stock status from {$oldStatus} to {$newStatus}";
        }

        if (isset($changes['is_active'])) {
            $status = $changes['is_active'] ? 'activated' : 'deactivated';
            return "{$user} {$status} variant {$displayName}";
        }

        $changedFields = implode(', ', array_keys($changes));
        return "{$user} updated variant {$displayName} ({$changedFields})";
    }

    /**
     * Generate description for variant deletion
     */
    private function generateDeletionDescription(ProductVariant $variant): string
    {
        $user = Auth::user()?->name ?? 'System';
        $displayName = $variant->display_name;
        $price = $variant->variant_price ? number_format($variant->variant_price, 2) : 'N/A';

        return "{$user} deleted variant {$displayName} (SKU: {$variant->sku}, Price: KES {$price})";
    }

    /**
     * Generate description for variant restoration
     */
    private function generateRestorationDescription(ProductVariant $variant): string
    {
        $user = Auth::user()?->name ?? 'System';
        $displayName = $variant->display_name;

        return "{$user} restored variant {$displayName} (SKU: {$variant->sku})";
    }
}
