<?php

namespace App\Observers\Tenant;

use App\Models\Tenant\Product;
use App\Models\Tenant\ProductPriceHistory;
use App\Services\Tenant\AuditService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ProductObserver
{
    public function __construct(
        private AuditService $auditService
    ) {}

    /**
     * Handle the Product "creating" event.
     */
    public function creating(Product $product): void
    {
        // Ensure UUID is set
        if (empty($product->uuid)) {
            $product->uuid = \Illuminate\Support\Str::uuid()->toString();
        }
    }

    /**
     * Handle the Product "created" event.
     */
    public function created(Product $product): void
    {
        $this->clearCache($product);

        try {
            $this->auditService->createAudit(
                model: $product,
                action: 'created',
                oldValues: null,
                newValues: $product->toArray(),
                description: $this->generateCreationDescription($product),
                tags: ['product', 'inventory']
            );
        } catch (\Exception $e) {
            Log::error('Failed to create product audit log', [
                'tenant_id' => tenant()?->id,
                'product_id' => $product->id,
                'error' => $e->getMessage(),
            ]);
        }

        // TODO: Dispatch ProductCreated event for sync queue
        if ($product->is_available_online) {
            // Dispatch event to sync with marketplace
        }
    }

    /**
     * Handle the Product "updated" event.
     */
    public function updated(Product $product): void
    {
        $this->clearCache($product);

        try {
            if ($this->auditService->hasCriticalChanges($product)) {
                $oldValues = $product->getOldValuesForAudit();
                $criticalChanges = $product->getCriticalChanges();

                // Generate context-aware description
                $description = $this->generateUpdateDescription($product, $criticalChanges);

                // Add specific tags based on changes
                $tags = ['product', 'inventory'];
                if (isset($criticalChanges['base_selling_price']) || isset($criticalChanges['online_price'])) {
                    $tags[] = 'price_change';
                    $tags[] = 'critical';
                }
                if (isset($criticalChanges['stock_status'])) {
                    $tags[] = 'stock_status';
                }
                if (isset($criticalChanges['is_available_online'])) {
                    $tags[] = 'marketplace';
                }

                $this->auditService->createAudit(
                    model: $product,
                    action: 'updated',
                    oldValues: array_intersect_key($oldValues, $criticalChanges),
                    newValues: $criticalChanges,
                    description: $description,
                    tags: $tags
                );
            }
        } catch (\Exception $e) {
            Log::error('Failed to create product update audit log', [
                'tenant_id' => tenant()?->id,
                'product_id' => $product->id,
                'error' => $e->getMessage(),
            ]);
        }

        // TODO: If is_available_online changed, trigger sync
        if ($product->wasChanged('is_available_online')) {
            // Dispatch event to sync with marketplace
        }

        // TODO: If inventory-related fields changed, trigger sync
        if ($product->wasChanged(['base_selling_price', 'online_price', 'stock_status'])) {
            // Dispatch event to update marketplace
        }
    }

    public function updating(Product $product): void
    {
        // Store old values for audit comparison
        $product->storeOldValuesForAudit();

        // Check if selling price changed
        if ($product->isDirty('base_selling_price')) {
            $oldPrice = $product->getOriginal('base_selling_price');
            $newPrice = $product->base_selling_price;

            // Only record if price actually changed
            if ($oldPrice != $newPrice) {
                ProductPriceHistory::create([
                    'product_id' => $product->id,
                    'product_variant_id' => null,
                    'base_uom_id' => $product->base_uom_id,
                    'old_selling_price' => $oldPrice,
                    'new_selling_price' => $newPrice,
                    'change_reason' => 'manual',
                    'changed_by' => Auth::id() ?? 1, // Default to system user if no auth
                    'effective_from' => now(),
                ]);

                Log::info('Product price change recorded', [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'old_price' => $oldPrice,
                    'new_price' => $newPrice,
                    'changed_by' => Auth::id(),
                    'tenant_id' => tenant()->id,
                ]);
            }
        }

        // Check if online price changed
        if ($product->isDirty('online_price')) {
            $oldPrice = $product->getOriginal('online_price');
            $newPrice = $product->online_price;

            // Only record if price actually changed
            if ($oldPrice != $newPrice) {
                ProductPriceHistory::create([
                    'product_id' => $product->id,
                    'product_variant_id' => null,
                    'base_uom_id' => $product->base_uom_id,
                    'old_selling_price' => $oldPrice,
                    'new_selling_price' => $newPrice,
                    'change_reason' => 'manual',
                    'changed_by' => Auth::id() ?? 1, // Default to system user if no auth
                    'effective_from' => now(),
                ]);

                Log::info('Product price change recorded', [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'old_price' => $oldPrice,
                    'new_price' => $newPrice,
                    'changed_by' => Auth::id(),
                    'tenant_id' => tenant()->id,
                ]);
            }
        }
    }


    /**
     * Handle the Product "deleted" event.
     */
    public function deleted(Product $product): void
    {
        $this->clearCache($product);

        try {
            $this->auditService->createAudit(
                model: $product,
                action: 'deleted',
                oldValues: $product->toArray(),
                newValues: null,
                description: $this->generateDeletionDescription($product),
                tags: ['product', 'inventory', 'critical']
            );
        } catch (\Exception $e) {
            Log::error('Failed to create product deletion audit log', [
                'tenant_id' => tenant()?->id,
                'product_id' => $product->id,
                'error' => $e->getMessage(),
            ]);
        }

        // TODO: If was available online, remove from marketplace
        if ($product->is_available_online) {
            // Dispatch event to remove from marketplace
        }
    }


    /**
     * Handle the Product "restored" event.
     */
    public function restored(Product $product): void
    {
        $this->clearCache($product);

        try {
            $this->auditService->createAudit(
                model: $product,
                action: 'restored',
                oldValues: null,
                newValues: $product->toArray(),
                description: $this->generateRestorationDescription($product),
                tags: ['product', 'inventory']
            );
        } catch (\Exception $e) {
            Log::error('Failed to create product restoration audit log', [
                'tenant_id' => tenant()?->id,
                'product_id' => $product->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle the Product "force deleted" event.
     */
    public function forceDeleted(Product $product): void
    {
        try {
            $this->auditService->createAudit(
                model: $product,
                action: 'force_deleted',
                oldValues: $product->toArray(),
                newValues: null,
                description: $this->generateForceDeletionDescription($product),
                tags: ['product', 'inventory', 'critical', 'permanent']
            );
        } catch (\Exception $e) {
            Log::error('Failed to create product force deletion audit log', [
                'tenant_id' => tenant()?->id,
                'product_id' => $product->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Clear product-related cache
     */
    protected function clearCache(Product $product): void
    {
        Cache::tags(['tenant', tenant()->id, 'products'])->flush();

        // Clear category cache if product has category
        if ($product->category_id) {
            Cache::tags(['tenant', tenant()->id, 'categories'])->flush();
        }

        // Clear brand cache if product has brand
        if ($product->brand_id) {
            Cache::tags(['tenant', tenant()->id, 'brands'])->flush();
        }
    }

    /**
     * Generate description for product creation
     */
    private function generateCreationDescription(Product $product): string
    {
        $user = Auth::user()?->name ?? 'System';
        $price = number_format($product->base_selling_price, 2);
        $categoryName = $product->category->name ?? 'Uncategorized';

        return "{$user} created product {$product->name} (SKU: {$product->sku}) in {$categoryName} - KES {$price}";
    }

    /**
     * Generate description for product update
     */
    private function generateUpdateDescription(Product $product, array $changes): string
    {
        $user = Auth::user()?->name ?? 'System';

        // Price change
        if (isset($changes['base_selling_price'])) {
            $oldPrice = number_format($product->getOriginal('base_selling_price'), 2);
            $newPrice = number_format($changes['base_selling_price'], 2);
            return "{$user} changed product {$product->name} price from KES {$oldPrice} to KES {$newPrice}";
        }

        // Online price change
        if (isset($changes['online_price'])) {
            $oldPrice = $product->getOriginal('online_price') ? number_format($product->getOriginal('online_price'), 2) : 'N/A';
            $newPrice = number_format($changes['online_price'], 2);
            return "{$user} changed product {$product->name} online price from KES {$oldPrice} to KES {$newPrice}";
        }

        // Stock status change
        if (isset($changes['stock_status'])) {
            $oldStatus = $product->getOriginal('stock_status');
            $newStatus = $changes['stock_status'];
            return "{$user} changed product {$product->name} stock status from {$oldStatus} to {$newStatus}";
        }

        // Availability change
        if (isset($changes['is_available_online'])) {
            $status = $changes['is_available_online'] ? 'enabled' : 'disabled';
            return "{$user} {$status} online availability for product {$product->name}";
        }

        // Active status change
        if (isset($changes['is_active'])) {
            $status = $changes['is_active'] ? 'activated' : 'deactivated';
            return "{$user} {$status} product {$product->name}";
        }

        // Product type change
        if (isset($changes['product_type'])) {
            $oldType = $product->getOriginal('product_type');
            $newType = $changes['product_type'];
            return "{$user} changed product {$product->name} type from {$oldType} to {$newType}";
        }

        // Generic update
        $changedFields = implode(', ', array_keys($changes));
        return "{$user} updated product {$product->name} ({$changedFields})";
    }

    /**
     * Generate description for product deletion
     */
    private function generateDeletionDescription(Product $product): string
    {
        $user = Auth::user()?->name ?? 'System';
        $price = number_format($product->base_selling_price, 2);

        return "{$user} deleted product {$product->name} (SKU: {$product->sku}, Price: KES {$price})";
    }

    /**
     * Generate description for product restoration
     */
    private function generateRestorationDescription(Product $product): string
    {
        $user = Auth::user()?->name ?? 'System';

        return "{$user} restored product {$product->name} (SKU: {$product->sku})";
    }

    /**
     * Generate description for product force deletion
     */
    private function generateForceDeletionDescription(Product $product): string
    {
        $user = Auth::user()?->name ?? 'System';

        return "{$user} permanently deleted product {$product->name} (SKU: {$product->sku})";
    }
}
