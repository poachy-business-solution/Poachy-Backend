<?php

namespace App\Services\Tenant\Sync;

use App\Events\Tenant\VariantMarketplaceSyncRequested;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductVariant;
use Illuminate\Support\Facades\Log;

class VariantSyncService
{
    /**
     * Trigger marketplace sync for a variant
     */
    public function syncToMarketplace(ProductVariant $variant, string $action = 'create', int $priority = 3): void
    {
        $variant->loadMissing('product');

        if ($action !== 'delete' && $action !== 'deactivate') {
            if (!$variant->online_price || $variant->online_price <= 0) {
                throw new \InvalidArgumentException(
                    "Variant '{$variant->variant_name}' must have a valid online_price set before syncing."
                );
            }

            if (!$variant->product?->is_available_online) {
                throw new \InvalidArgumentException(
                    "Parent product must be available online before syncing variant '{$variant->variant_name}'."
                );
            }

            if (!$variant->product?->category_id) {
                throw new \InvalidArgumentException(
                    "Parent product must have a category assigned before syncing variant '{$variant->variant_name}'."
                );
            }

            if (!$variant->product?->tax_rate_id) {
                throw new \InvalidArgumentException(
                    "Parent product must have a tax rate assigned before syncing variant '{$variant->variant_name}'."
                );
            }
        }

        try {
            event(new VariantMarketplaceSyncRequested($variant, $action, $priority));

            Log::info('Variant marketplace sync triggered', [
                'tenant_id' => tenant()->id,
                'variant_id' => $variant->id,
                'variant_name' => $variant->variant_name,
                'product_id' => $variant->product_id,
                'action' => $action,
                'priority' => $priority,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to trigger variant marketplace sync', [
                'tenant_id' => tenant()->id,
                'variant_id' => $variant->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Check if variant is eligible for marketplace sync
     */
    public function isEligibleForSync(ProductVariant $variant): bool
    {
        $variant->loadMissing('product');

        return $variant->is_active
            && $variant->online_price !== null
            && $variant->online_price > 0
            && $variant->product?->is_available_online
            && $variant->product?->category_id
            && $variant->product?->tax_rate_id
            && $variant->product?->base_uom_id;
    }

    /**
     * Get validation errors for sync eligibility
     */
    public function getSyncValidationErrors(ProductVariant $variant): array
    {
        $variant->loadMissing('product');
        $errors = [];

        if (!$variant->is_active) {
            $errors[] = 'Variant must be active';
        }

        if ($variant->online_price === null || $variant->online_price <= 0) {
            $errors[] = 'Variant must have a valid online price';
        }

        if (!$variant->product?->is_available_online) {
            $errors[] = 'Parent product must be available online';
        }

        if (!$variant->product?->category_id) {
            $errors[] = 'Parent product must be assigned to a category';
        }

        if (!$variant->product?->tax_rate_id) {
            $errors[] = 'Parent product must have a tax rate assigned';
        }

        if (!$variant->product?->base_uom_id) {
            $errors[] = 'Parent product must have a base unit of measure';
        }

        return $errors;
    }

    /**
     * Bulk sync all eligible variants for a product (used for cascade)
     */
    public function bulkSyncVariantsForProduct(Product $product, string $action = 'create', int $priority = 5): array
    {
        $results = [
            'success' => 0,
            'failed' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        $variants = $product->variants()
            ->where('is_active', true)
            ->whereNotNull('online_price')
            ->where('online_price', '>', 0)
            ->get();

        foreach ($variants as $variant) {
            try {
                if ($action !== 'deactivate' && !$this->isEligibleForSync($variant)) {
                    $results['skipped']++;
                    $results['errors'][$variant->id] = $this->getSyncValidationErrors($variant);
                    continue;
                }

                $this->syncToMarketplace($variant, $action, $priority);
                $results['success']++;
            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][$variant->id] = $e->getMessage();

                Log::error('Bulk variant sync failed', [
                    'variant_id' => $variant->id,
                    'product_id' => $product->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $results;
    }
}
