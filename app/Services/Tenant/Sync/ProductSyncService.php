<?php

namespace App\Services\Tenant\Sync;

use App\DataTransferObjects\Sync\ProductSyncDTO;
use App\Events\Tenant\ProductMarketplaceSyncRequested;
use App\Models\Tenant\Product;
use Illuminate\Support\Facades\Log;

class ProductSyncService
{
    /**
     * Trigger marketplace sync for a product
     */
    public function syncToMarketplace(Product $product, string $action = 'create', int $priority = 3): void
    {
        // Skip validation for delete/deactivate actions
        if (!in_array($action, ['delete', 'deactivate'])) {
            if (!$product->is_available_online) {
                throw new \InvalidArgumentException(
                    "Product '{$product->name}' is not available online and cannot be synced to marketplace."
                );
            }

            if (!$product->online_price || $product->online_price <= 0) {
                throw new \InvalidArgumentException(
                    "Product '{$product->name}' must have a valid online_price set before syncing."
                );
            }

            if (!$product->category_id) {
                throw new \InvalidArgumentException(
                    "Product '{$product->name}' must have a category assigned before syncing."
                );
            }

            if (!$product->base_uom_id) {
                throw new \InvalidArgumentException(
                    "Product '{$product->name}' must have a base UOM assigned before syncing."
                );
            }

            if (!$product->tax_rate_id) {
                throw new \InvalidArgumentException(
                    "Product '{$product->name}' must have a tax rate assigned before syncing."
                );
            }
        }

        try {
            // Dispatch event
            event(new ProductMarketplaceSyncRequested($product, $action, $priority));

            Log::info('Product marketplace sync triggered', [
                'tenant_id' => tenant()->id,
                'product_id' => $product->id,
                'product_name' => $product->name,
                'action' => $action,
                'priority' => $priority,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to trigger product marketplace sync', [
                'tenant_id' => tenant()->id,
                'product_id' => $product->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Check if product is eligible for marketplace sync
     */
    public function isEligibleForSync(Product $product): bool
    {
        return $product->is_available_online
            && $product->online_price > 0
            && $product->category_id
            && $product->base_uom_id
            && $product->tax_rate_id
            && $product->is_active;
    }

    /**
     * Get validation errors for sync eligibility
     */
    public function getSyncValidationErrors(Product $product): array
    {
        $errors = [];

        if (!$product->is_available_online) {
            $errors[] = 'Product must be marked as available online';
        }

        if (!$product->online_price || $product->online_price <= 0) {
            $errors[] = 'Product must have a valid online price';
        }

        if (!$product->category_id) {
            $errors[] = 'Product must be assigned to a category';
        }

        if (!$product->base_uom_id) {
            $errors[] = 'Product must have a base unit of measure';
        }

        if (!$product->tax_rate_id) {
            $errors[] = 'Product must have a tax rate assigned';
        }

        if (!$product->is_active) {
            $errors[] = 'Product must be active';
        }

        return $errors;
    }

    /**
     * Prepare product data for sync (used in DTOs)
     */
    public function prepareProductData(Product $product): array
    {
        // Eager load relationships
        $product->loadMissing([
            'category',
            'brand',
            'baseUom',
            'taxRate',
            'inventories',
        ]);

        return [
            'product' => $product,
            'total_inventory' => $product->inventories()
                ->whereNull('product_variant_id')
                ->sum('quantity_available'),
        ];
    }

    /**
     * Bulk sync multiple products
     */
    public function bulkSyncToMarketplace(array $productIds, int $priority = 5): array
    {
        $results = [
            'success' => 0,
            'failed' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        foreach ($productIds as $productId) {
            try {
                $product = Product::find($productId);

                if (!$product) {
                    $results['skipped']++;
                    $results['errors'][$productId] = 'Product not found';
                    continue;
                }

                if (!$this->isEligibleForSync($product)) {
                    $results['skipped']++;
                    $results['errors'][$productId] = $this->getSyncValidationErrors($product);
                    continue;
                }

                $this->syncToMarketplace($product, 'create', $priority);
                $results['success']++;
            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][$productId] = $e->getMessage();

                Log::error('Bulk sync failed for product', [
                    'product_id' => $productId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $results;
    }
}
