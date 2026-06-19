<?php

namespace App\Services\Central\Sync;

use App\DataTransferObjects\Sync\BundleSyncDTO;
use App\Models\MarketplaceProduct;
use App\Models\SyncQueueInbound;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MarketplaceBundleSyncService
{
    public function __construct(
        private MarketplaceMappingService $mappingService
    ) {}

    /**
     * Create a new marketplace product from bundle
     */
    public function createMarketplaceProduct(BundleSyncDTO $dto, SyncQueueInbound $syncQueue): array
    {
        DB::connection('central')->beginTransaction();

        try {
            // Step 1: Validate
            $syncQueue->markAsValidating();
            $this->validateBundleData($dto);

            // Step 2: Get bundle category (no brand for bundles)
            $syncQueue->markAsMapping();
            $bundleCategoryId = $this->mappingService->getBundleCategoryId();

            // Step 3: Check for existing bundle product
            $existingProduct = MarketplaceProduct::where('tenant_id', $dto->tenantId)
                ->where('tenant_bundle_id', $dto->bundleId)
                ->where('tenant_product_type', 'bundle')
                ->first();

            if ($existingProduct) {
                Log::warning('Bundle already exists in marketplace, updating instead', [
                    'tenant_id' => $dto->tenantId,
                    'bundle_id' => $dto->bundleId,
                    'marketplace_product_id' => $existingProduct->id,
                ]);

                $this->updateExistingMarketplaceProduct($existingProduct, $dto, $bundleCategoryId);

                DB::connection('central')->commit();

                return [
                    'marketplace_product_id' => $existingProduct->id,
                    'action' => 'updated',
                    'bundle_category_id' => $bundleCategoryId,
                ];
            }

            // Step 4: Create marketplace product for bundle
            $syncQueue->markAsSyncing();
            $bundleSlug = Str::slug($dto->bundleName);

            $marketplaceProduct = MarketplaceProduct::create([
                // Source Information
                'tenant_id' => $dto->tenantId,
                'tenant_product_id' => null,
                'tenant_product_type' => 'bundle',
                'tenant_variant_id' => null,
                'tenant_bundle_id' => $dto->bundleId,

                // Product Details
                'name' => $dto->bundleName,
                'slug' => $this->generateUniqueSlug($bundleSlug, $dto->tenantId),
                'description' => $this->buildBundleDescription($dto),
                'online_description' => $dto->onlineDescription,
                'sku' => $dto->bundleSku,

                // Bundles use a generic category, no brand
                'tenant_category_id' => null,
                'tenant_category_name' => 'Bundle',
                'tenant_brand_id' => null,
                'tenant_brand_name' => null,

                // Marketplace Mapped Categorization
                'marketplace_category_id' => $bundleCategoryId,
                'marketplace_brand_id' => null,

                // Pricing & UOM
                'online_price' => $dto->onlinePrice,
                'base_uom_code' => $dto->baseUom->code,
                'base_uom_name' => $dto->baseUom->name,
                'tax_rate' => $dto->taxRate,

                // Inventory (bundles are always in stock if active)
                'available_quantity' => 1,
                'stock_status' => 'in_stock',

                // Media
                'primary_image' => $dto->primaryImage,
                'secondary_images' => $dto->secondaryImages,

                // Metrics (initial values)
                'view_count' => 0,
                'order_count' => 0,
                'average_rating' => 0,
                'rating_count' => 0,

                // Status
                'is_active' => $dto->isActive,
                'is_featured' => false,
                'display_priority' => 0,

                // Sync tracking
                'last_synced_at' => now(),
                'sync_status' => 'synced',
            ]);

            DB::connection('central')->commit();

            Log::info('Marketplace bundle product created successfully', [
                'tenant_id' => $dto->tenantId,
                'tenant_bundle_id' => $dto->bundleId,
                'marketplace_product_id' => $marketplaceProduct->id,
                'bundle_category_id' => $bundleCategoryId,
                'items_count' => count($dto->items),
            ]);

            return [
                'marketplace_product_id' => $marketplaceProduct->id,
                'action' => 'created',
                'bundle_category_id' => $bundleCategoryId,
            ];
        } catch (\Exception $e) {
            DB::connection('central')->rollBack();

            Log::error('Failed to create marketplace bundle product', [
                'tenant_id' => $dto->tenantId,
                'bundle_id' => $dto->bundleId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Update an existing marketplace bundle product
     */
    public function updateMarketplaceProduct(BundleSyncDTO $dto, SyncQueueInbound $syncQueue): array
    {
        DB::connection('central')->beginTransaction();

        try {
            $marketplaceProduct = MarketplaceProduct::where('tenant_id', $dto->tenantId)
                ->where('tenant_bundle_id', $dto->bundleId)
                ->where('tenant_product_type', 'bundle')
                ->first();

            if (!$marketplaceProduct) {
                Log::info('Marketplace bundle product not found for update, creating instead', [
                    'tenant_id' => $dto->tenantId,
                    'tenant_bundle_id' => $dto->bundleId,
                    'action' => 'update->create',
                ]);

                // Commit current transaction before delegating (create manages its own)
                DB::connection('central')->commit();

                return $this->createMarketplaceProduct($dto, $syncQueue);
            }

            // Step 1: Validate
            $syncQueue->markAsValidating();
            $this->validateBundleData($dto);

            // Step 2: Get bundle category
            $syncQueue->markAsMapping();
            $bundleCategoryId = $this->mappingService->getBundleCategoryId();

            // Step 3: Update
            $syncQueue->markAsSyncing();
            $this->updateExistingMarketplaceProduct($marketplaceProduct, $dto, $bundleCategoryId);

            DB::connection('central')->commit();

            Log::info('Marketplace bundle product updated successfully', [
                'tenant_id' => $dto->tenantId,
                'tenant_bundle_id' => $dto->bundleId,
                'marketplace_product_id' => $marketplaceProduct->id,
            ]);

            return [
                'marketplace_product_id' => $marketplaceProduct->id,
                'action' => 'updated',
                'bundle_category_id' => $bundleCategoryId,
            ];
        } catch (\Exception $e) {
            DB::connection('central')->rollBack();

            Log::error('Failed to update marketplace bundle product', [
                'tenant_id' => $dto->tenantId,
                'bundle_id' => $dto->bundleId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Delete (soft delete) a marketplace bundle product
     */
    public function deleteMarketplaceProduct(BundleSyncDTO $dto, SyncQueueInbound $syncQueue): array
    {
        DB::connection('central')->beginTransaction();

        try {
            $marketplaceProduct = MarketplaceProduct::where('tenant_id', $dto->tenantId)
                ->where('tenant_bundle_id', $dto->bundleId)
                ->where('tenant_product_type', 'bundle')
                ->first();

            if (!$marketplaceProduct) {
                Log::warning('Marketplace bundle product not found for deletion', [
                    'tenant_id' => $dto->tenantId,
                    'tenant_bundle_id' => $dto->bundleId,
                ]);

                DB::connection('central')->commit();

                return [
                    'marketplace_product_id' => null,
                    'action' => 'delete_skipped',
                    'reason' => 'bundle_not_found',
                ];
            }

            $syncQueue->markAsSyncing();

            $marketplaceProduct->delete();

            DB::connection('central')->commit();

            Log::info('Marketplace bundle product deleted successfully', [
                'tenant_id' => $dto->tenantId,
                'tenant_bundle_id' => $dto->bundleId,
                'marketplace_product_id' => $marketplaceProduct->id,
            ]);

            return [
                'marketplace_product_id' => $marketplaceProduct->id,
                'action' => 'deleted',
            ];
        } catch (\Exception $e) {
            DB::connection('central')->rollBack();

            Log::error('Failed to delete marketplace bundle product', [
                'tenant_id' => $dto->tenantId,
                'bundle_id' => $dto->bundleId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Activate a marketplace bundle product
     */
    public function activateMarketplaceProduct(BundleSyncDTO $dto, SyncQueueInbound $syncQueue): array
    {
        DB::connection('central')->beginTransaction();

        try {
            $marketplaceProduct = MarketplaceProduct::withTrashed()
                ->where('tenant_id', $dto->tenantId)
                ->where('tenant_bundle_id', $dto->bundleId)
                ->where('tenant_product_type', 'bundle')
                ->first();

            if (!$marketplaceProduct) {
                Log::warning('Marketplace bundle product not found for activation, creating instead', [
                    'tenant_id' => $dto->tenantId,
                    'tenant_bundle_id' => $dto->bundleId,
                ]);

                // Commit current transaction before delegating (create manages its own)
                DB::connection('central')->commit();

                return $this->createMarketplaceProduct($dto, $syncQueue);
            }

            $syncQueue->markAsSyncing();

            // Restore if soft deleted
            if ($marketplaceProduct->trashed()) {
                $marketplaceProduct->restore();

                Log::info('Marketplace bundle product restored from soft delete', [
                    'marketplace_product_id' => $marketplaceProduct->id,
                ]);
            }

            $marketplaceProduct->update([
                'is_active' => true,
                'last_synced_at' => now(),
            ]);

            DB::connection('central')->commit();

            Log::info('Marketplace bundle product activated successfully', [
                'tenant_id' => $dto->tenantId,
                'tenant_bundle_id' => $dto->bundleId,
                'marketplace_product_id' => $marketplaceProduct->id,
            ]);

            return [
                'marketplace_product_id' => $marketplaceProduct->id,
                'action' => 'activated',
            ];
        } catch (\Exception $e) {
            DB::connection('central')->rollBack();

            Log::error('Failed to activate marketplace bundle product', [
                'tenant_id' => $dto->tenantId,
                'bundle_id' => $dto->bundleId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Deactivate a marketplace bundle product
     */
    public function deactivateMarketplaceProduct(BundleSyncDTO $dto, SyncQueueInbound $syncQueue): array
    {
        DB::connection('central')->beginTransaction();

        try {
            $marketplaceProduct = MarketplaceProduct::where('tenant_id', $dto->tenantId)
                ->where('tenant_bundle_id', $dto->bundleId)
                ->where('tenant_product_type', 'bundle')
                ->first();

            if (!$marketplaceProduct) {
                Log::warning('Marketplace bundle product not found for deactivation', [
                    'tenant_id' => $dto->tenantId,
                    'tenant_bundle_id' => $dto->bundleId,
                ]);

                DB::connection('central')->commit();

                return [
                    'marketplace_product_id' => null,
                    'action' => 'deactivate_skipped',
                    'reason' => 'bundle_not_found',
                ];
            }

            $syncQueue->markAsSyncing();

            $marketplaceProduct->update([
                'is_active' => false,
                'is_featured' => false,
                'last_synced_at' => now(),
            ]);

            DB::connection('central')->commit();

            Log::info('Marketplace bundle product deactivated successfully', [
                'tenant_id' => $dto->tenantId,
                'tenant_bundle_id' => $dto->bundleId,
                'marketplace_product_id' => $marketplaceProduct->id,
            ]);

            return [
                'marketplace_product_id' => $marketplaceProduct->id,
                'action' => 'deactivated',
            ];
        } catch (\Exception $e) {
            DB::connection('central')->rollBack();

            Log::error('Failed to deactivate marketplace bundle product', [
                'tenant_id' => $dto->tenantId,
                'bundle_id' => $dto->bundleId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Update existing marketplace bundle product with new data
     */
    protected function updateExistingMarketplaceProduct(
        MarketplaceProduct $marketplaceProduct,
        BundleSyncDTO $dto,
        int $bundleCategoryId
    ): array {
        $bundleSlug = Str::slug($dto->bundleName);

        $updateData = [
            // Product Details
            'name' => $dto->bundleName,
            'slug' => $this->generateUniqueSlug($bundleSlug, $dto->tenantId, $marketplaceProduct->id),
            'description' => $this->buildBundleDescription($dto),
            'online_description' => $dto->onlineDescription,
            'sku' => $dto->bundleSku,

            // Marketplace Mapped Categorization
            'marketplace_category_id' => $bundleCategoryId,

            // Pricing & UOM
            'online_price' => $dto->onlinePrice,
            'base_uom_code' => $dto->baseUom->code,
            'base_uom_name' => $dto->baseUom->name,
            'tax_rate' => $dto->taxRate,

            // Media
            'primary_image' => $dto->primaryImage,
            'secondary_images' => $dto->secondaryImages,

            // Sync tracking
            'last_synced_at' => now(),
            'sync_status' => 'synced',
        ];

        // Track changes for logging
        $changedFields = [];
        foreach ($updateData as $key => $value) {
            if ($marketplaceProduct->$key != $value) {
                $changedFields[$key] = [
                    'old' => $marketplaceProduct->$key,
                    'new' => $value,
                ];
            }
        }

        $marketplaceProduct->update($updateData);

        if (!empty($changedFields)) {
            Log::info('Marketplace bundle product fields updated', [
                'marketplace_product_id' => $marketplaceProduct->id,
                'tenant_id' => $dto->tenantId,
                'tenant_bundle_id' => $dto->bundleId,
                'changed_fields' => array_keys($changedFields),
            ]);
        }

        return [
            'marketplace_product_id' => $marketplaceProduct->id,
            'action' => 'updated',
            'bundle_category_id' => $bundleCategoryId,
            'changed_fields' => array_keys($changedFields),
        ];
    }

    /**
     * Generate unique slug for marketplace bundle product
     */
    protected function generateUniqueSlug(string $baseSlug, string $tenantId, ?int $excludeProductId = null): string
    {
        $slug = $baseSlug . '-' . substr($tenantId, 0, 8);
        $originalSlug = $slug;
        $counter = 1;

        while (true) {
            $query = MarketplaceProduct::where('slug', $slug);

            if ($excludeProductId) {
                $query->where('id', '!=', $excludeProductId);
            }

            if (!$query->exists()) {
                return $slug;
            }

            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }
    }

    /**
     * Build description incorporating bundle items details
     */
    protected function buildBundleDescription(BundleSyncDTO $dto): ?string
    {
        $parts = [];

        if ($dto->description) {
            $parts[] = $dto->description;
        }

        // Build items summary
        if (!empty($dto->items)) {
            $itemLines = [];
            foreach ($dto->items as $item) {
                $itemName = $item->variantName
                    ? "{$item->productName} ({$item->variantName})"
                    : $item->productName;
                $itemLines[] = "- {$item->quantity} x {$itemName} ({$item->uomName})";
            }
            $parts[] = "Bundle Contains:\n" . implode("\n", $itemLines);
        }

        // Pricing info
        if ($dto->discountAmount && $dto->discountAmount > 0) {
            $parts[] = "Save KES " . number_format($dto->discountAmount, 2) . " compared to buying individually";
        }

        if ($dto->savingsPercentage && $dto->savingsPercentage > 0) {
            $parts[] = number_format($dto->savingsPercentage, 1) . "% savings";
        }

        return !empty($parts) ? implode("\n\n", $parts) : null;
    }

    /**
     * Validate bundle data from DTO
     */
    protected function validateBundleData(BundleSyncDTO $dto): void
    {
        if ($dto->onlinePrice <= 0) {
            throw new \InvalidArgumentException('Online price must be greater than 0');
        }

        if ($dto->taxRate < 0 || $dto->taxRate > 100) {
            throw new \InvalidArgumentException('Tax rate must be between 0 and 100');
        }

        if (empty($dto->items)) {
            throw new \InvalidArgumentException('Bundle must contain at least one item');
        }
    }
}
