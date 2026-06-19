<?php

namespace App\Services\Tenant\Sync;

use App\Events\Tenant\BundleMarketplaceSyncRequested;
use App\Models\Tenant\ProductBundle;
use Illuminate\Support\Facades\Log;

class BundleSyncService
{
    /**
     * Trigger marketplace sync for a bundle
     */
    public function syncToMarketplace(ProductBundle $bundle, string $action = 'create', int $priority = 3): void
    {
        // Skip validation for delete/deactivate actions
        if (!in_array($action, ['delete', 'deactivate'])) {
            if (!$bundle->is_available_online) {
                throw new \InvalidArgumentException(
                    "Bundle '{$bundle->bundle_name}' is not available online and cannot be synced to marketplace."
                );
            }

            if (!$bundle->online_price || $bundle->online_price <= 0) {
                throw new \InvalidArgumentException(
                    "Bundle '{$bundle->bundle_name}' must have a valid online_price set before syncing."
                );
            }

            if (!$bundle->base_uom_id) {
                throw new \InvalidArgumentException(
                    "Bundle '{$bundle->bundle_name}' must have a base UOM assigned before syncing."
                );
            }

            if (!$bundle->tax_rate_id) {
                throw new \InvalidArgumentException(
                    "Bundle '{$bundle->bundle_name}' must have a tax rate assigned before syncing."
                );
            }

            if (!$bundle->hasMinimumItems()) {
                throw new \InvalidArgumentException(
                    "Bundle '{$bundle->bundle_name}' must have at least 2 items before syncing."
                );
            }

            if (!$bundle->allItemsActive()) {
                throw new \InvalidArgumentException(
                    "Bundle '{$bundle->bundle_name}' must have all component products active before syncing."
                );
            }
        }

        try {
            // Dispatch event
            event(new BundleMarketplaceSyncRequested($bundle, $action, $priority));

            Log::info('Bundle marketplace sync triggered', [
                'tenant_id' => tenant()->id,
                'bundle_id' => $bundle->id,
                'bundle_name' => $bundle->bundle_name,
                'action' => $action,
                'priority' => $priority,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to trigger bundle marketplace sync', [
                'tenant_id' => tenant()->id,
                'bundle_id' => $bundle->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Check if bundle is eligible for marketplace sync
     */
    public function isEligibleForSync(ProductBundle $bundle): bool
    {
        return $bundle->is_available_online
            && $bundle->online_price > 0
            && $bundle->is_active
            && $bundle->base_uom_id
            && $bundle->tax_rate_id
            && $bundle->hasMinimumItems()
            && $bundle->allItemsActive();
    }

    /**
     * Get validation errors for sync eligibility
     */
    public function getSyncValidationErrors(ProductBundle $bundle): array
    {
        $errors = [];

        if (!$bundle->is_available_online) {
            $errors[] = 'Bundle must be marked as available online';
        }

        if (!$bundle->online_price || $bundle->online_price <= 0) {
            $errors[] = 'Bundle must have a valid online price';
        }

        if (!$bundle->is_active) {
            $errors[] = 'Bundle must be active';
        }

        if (!$bundle->base_uom_id) {
            $errors[] = 'Bundle must have a base unit of measure';
        }

        if (!$bundle->tax_rate_id) {
            $errors[] = 'Bundle must have a tax rate assigned';
        }

        if (!$bundle->hasMinimumItems()) {
            $errors[] = 'Bundle must have at least 2 items';
        }

        if (!$bundle->allItemsActive()) {
            $errors[] = 'All component products must be active';
        }

        return $errors;
    }
}
