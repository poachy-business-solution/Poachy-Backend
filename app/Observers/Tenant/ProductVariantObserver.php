<?php

namespace App\Observers\Tenant;

use App\Models\Tenant\ProductPriceHistory;
use App\Models\Tenant\ProductVariant;
use App\Services\Tenant\AuditService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ProductVariantObserver
{
    public function __construct(
        private AuditService $auditService
    ) {}

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

        // TODO: Sync to marketplace if product is available online
        if ($variant->is_available_online) {
            // Trigger marketplace sync
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

                // Generate context-aware description
                $description = $this->generateUpdateDescription($variant, $criticalChanges);

                // Add specific tags based on changes
                $tags = ['product_variant', 'inventory'];
                if (isset($criticalChanges['variant_price']) || isset($criticalChanges['base_selling_price_adjustment'])) {
                    $tags[] = 'price_change';
                    $tags[] = 'critical';
                }
                if (isset($criticalChanges['stock_status'])) {
                    $tags[] = 'stock_status';
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

        // TODO: Sync changes to marketplace
        if ($variant->wasChanged(['variant_price', 'stock_status', 'is_active'])) {
            // Trigger marketplace sync
        }
    }

    public function updating(ProductVariant $variant): void
    {
        $variant->storeOldValuesForAudit();

        // Check if variant price changed
        if ($variant->isDirty('variant_price')) {
            $oldPrice = $variant->getOriginal('variant_price');
            $newPrice = $variant->variant_price;

            // Only record if price actually changed
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

            // Only record if price actually changed
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

        // TODO: Remove from marketplace if was available online
        if ($variant->is_available_online) {
            // Trigger marketplace removal
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

        // Variant price change
        if (isset($changes['variant_price'])) {
            $oldPrice = $variant->getOriginal('variant_price') ? number_format($variant->getOriginal('variant_price'), 2) : 'N/A';
            $newPrice = number_format($changes['variant_price'], 2);
            return "{$user} changed variant {$displayName} price from KES {$oldPrice} to KES {$newPrice}";
        }

        // Base selling price adjustment change
        if (isset($changes['base_selling_price_adjustment'])) {
            $oldAdj = number_format($variant->getOriginal('base_selling_price_adjustment'), 2);
            $newAdj = number_format($changes['base_selling_price_adjustment'], 2);
            return "{$user} changed variant {$displayName} price adjustment from KES {$oldAdj} to KES {$newAdj}";
        }

        // Stock status change
        if (isset($changes['stock_status'])) {
            $oldStatus = $variant->getOriginal('stock_status');
            $newStatus = $changes['stock_status'];
            return "{$user} changed variant {$displayName} stock status from {$oldStatus} to {$newStatus}";
        }

        // Active status change
        if (isset($changes['is_active'])) {
            $status = $changes['is_active'] ? 'activated' : 'deactivated';
            return "{$user} {$status} variant {$displayName}";
        }

        // Generic update
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
