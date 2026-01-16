<?php

namespace App\Services\Central\Sync;

use App\DataTransferObjects\Sync\ProductSyncDTO;
use App\Models\MarketplaceBrand;
use App\Models\MarketplaceCategory;
use App\Models\MarketplaceProduct;
use App\Models\SyncQueueInbound;
use App\Models\TenantBrandMapping;
use App\Models\TenantCategoryMapping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MarketplaceSyncService
{
    /**
     * Create a new marketplace product
     */
    public function createMarketplaceProduct(ProductSyncDTO $dto, SyncQueueInbound $syncQueue): array
    {
        DB::connection('central')->beginTransaction();

        try {
            // Step 1: Validate
            $syncQueue->markAsValidating();
            $this->validateProductData($dto);

            // Step 2: Map category and brand
            $syncQueue->markAsMapping();
            $categoryMapping = $this->mapCategory($dto);
            $brandMapping = $dto->brand ? $this->mapBrand($dto) : null;

            // Step 3: Check for existing product
            $existingProduct = MarketplaceProduct::where('tenant_id', $dto->tenantId)
                ->where('tenant_product_id', $dto->productId)
                ->where('tenant_product_type', $dto->productType)
                ->whereNull('tenant_variant_id')
                ->whereNull('tenant_bundle_id')
                ->first();

            if ($existingProduct) {
                Log::warning('Product already exists in marketplace, updating instead', [
                    'tenant_id' => $dto->tenantId,
                    'product_id' => $dto->productId,
                    'marketplace_product_id' => $existingProduct->id,
                ]);

                // Update instead of create
                $result = $this->updateExistingMarketplaceProduct($existingProduct, $dto, $categoryMapping, $brandMapping);

                DB::connection('central')->commit();

                return [
                    'marketplace_product_id' => $existingProduct->id,
                    'action' => 'updated',
                    'category_mapping' => $categoryMapping,
                    'brand_mapping' => $brandMapping,
                ];
            }

            // Step 4: Create marketplace product
            $syncQueue->markAsSyncing();
            $marketplaceProduct = MarketplaceProduct::create([
                // Source Information
                'tenant_id' => $dto->tenantId,
                'tenant_product_id' => $dto->productId,
                'tenant_product_type' => $dto->productType,
                'tenant_variant_id' => $dto->variantId,
                'tenant_bundle_id' => $dto->bundleId,

                // Product Details
                'name' => $dto->name,
                'slug' => $this->generateUniqueSlug($dto->slug, $dto->tenantId),
                'description' => $dto->description,
                'online_description' => $dto->onlineDescription,
                'sku' => $dto->sku,

                // Tenant Original Categorization
                'tenant_category_id' => $dto->category->id,
                'tenant_category_name' => $dto->category->name,
                'tenant_brand_id' => $dto->brand?->id,
                'tenant_brand_name' => $dto->brand?->name,

                // Marketplace Mapped Categorization
                'marketplace_category_id' => $categoryMapping['category_id'],
                'marketplace_brand_id' => $brandMapping['brand_id'] ?? null,

                // Pricing & UOM
                'online_price' => $dto->onlinePrice,
                'base_uom_code' => $dto->baseUom->code,
                'base_uom_name' => $dto->baseUom->name,
                'tax_rate' => $dto->taxRate,

                // Inventory
                'available_quantity' => $dto->inventory->availableQuantity,
                'stock_status' => $dto->inventory->stockStatus,

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
                'is_featured' => $dto->isFeatured,
                'display_priority' => 0,

                // Sync tracking
                'last_synced_at' => now(),
                'sync_status' => 'synced',
            ]);

            DB::connection('central')->commit();

            Log::info('Marketplace product created successfully', [
                'tenant_id' => $dto->tenantId,
                'tenant_product_id' => $dto->productId,
                'marketplace_product_id' => $marketplaceProduct->id,
                'category_mapping' => [
                    'tenant_category' => $dto->category->name,
                    'marketplace_category_id' => $categoryMapping['category_id'],
                    'confidence' => $categoryMapping['confidence'],
                ],
                'brand_mapping' => $brandMapping ? [
                    'tenant_brand' => $dto->brand->name,
                    'marketplace_brand_id' => $brandMapping['brand_id'],
                    'confidence' => $brandMapping['confidence'],
                ] : null,
            ]);

            return [
                'marketplace_product_id' => $marketplaceProduct->id,
                'action' => 'created',
                'category_mapping' => $categoryMapping,
                'brand_mapping' => $brandMapping,
            ];
        } catch (\Exception $e) {
            DB::connection('central')->rollBack();

            Log::error('Failed to create marketplace product', [
                'tenant_id' => $dto->tenantId,
                'product_id' => $dto->productId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Update an existing marketplace product
     */
    public function updateMarketplaceProduct(ProductSyncDTO $dto, SyncQueueInbound $syncQueue): array
    {
        DB::connection('central')->beginTransaction();

        try {
            // Find existing product
            $marketplaceProduct = MarketplaceProduct::where('tenant_id', $dto->tenantId)
                ->where('tenant_product_id', $dto->productId)
                ->where('tenant_product_type', $dto->productType)
                ->whereNull('tenant_variant_id')
                ->whereNull('tenant_bundle_id')
                ->first();

            // If product doesn't exist, CREATE instead of UPDATE
            if (!$marketplaceProduct) {
                Log::info('Marketplace product not found for update, creating instead', [
                    'tenant_id' => $dto->tenantId,
                    'tenant_product_id' => $dto->productId,
                    'action' => 'update->create',
                ]);

                // Delegate to create method
                $result = $this->createMarketplaceProduct($dto, $syncQueue);

                DB::connection('central')->commit();

                return $result;
            }

            // Product exists - proceed with update
            // Step 1: Validate
            $syncQueue->markAsValidating();
            $this->validateProductData($dto);

            // Step 2: Map category and brand (check if changed)
            $syncQueue->markAsMapping();
            $categoryMapping = $this->mapCategory($dto);
            $brandMapping = $dto->brand ? $this->mapBrand($dto) : null;

            // Step 3: Update marketplace product
            $syncQueue->markAsSyncing();
            $this->updateExistingMarketplaceProduct($marketplaceProduct, $dto, $categoryMapping, $brandMapping);

            DB::connection('central')->commit();

            Log::info('Marketplace product updated successfully', [
                'tenant_id' => $dto->tenantId,
                'tenant_product_id' => $dto->productId,
                'marketplace_product_id' => $marketplaceProduct->id,
            ]);

            return [
                'marketplace_product_id' => $marketplaceProduct->id,
                'action' => 'updated',
                'category_mapping' => $categoryMapping,
                'brand_mapping' => $brandMapping,
            ];
        } catch (\Exception $e) {
            DB::connection('central')->rollBack();

            Log::error('Failed to update marketplace product', [
                'tenant_id' => $dto->tenantId,
                'product_id' => $dto->productId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function deleteMarketplaceProduct(ProductSyncDTO $dto, SyncQueueInbound $syncQueue): array
    {
        DB::connection('central')->beginTransaction();

        try {
            $marketplaceProduct = MarketplaceProduct::where('tenant_id', $dto->tenantId)
                ->where('tenant_product_id', $dto->productId)
                ->where('tenant_product_type', $dto->productType)
                ->whereNull('tenant_variant_id')
                ->whereNull('tenant_bundle_id')
                ->first();

            if (!$marketplaceProduct) {
                Log::warning('Marketplace product not found for deletion', [
                    'tenant_id' => $dto->tenantId,
                    'tenant_product_id' => $dto->productId,
                ]);

                DB::connection('central')->commit();

                return [
                    'marketplace_product_id' => null,
                    'action' => 'delete_skipped',
                    'reason' => 'product_not_found',
                ];
            }

            $syncQueue->markAsSyncing();

            // Soft delete
            $marketplaceProduct->delete();

            DB::connection('central')->commit();

            Log::info('Marketplace product deleted successfully', [
                'tenant_id' => $dto->tenantId,
                'tenant_product_id' => $dto->productId,
                'marketplace_product_id' => $marketplaceProduct->id,
            ]);

            return [
                'marketplace_product_id' => $marketplaceProduct->id,
                'action' => 'deleted',
            ];
        } catch (\Exception $e) {
            DB::connection('central')->rollBack();

            Log::error('Failed to delete marketplace product', [
                'tenant_id' => $dto->tenantId,
                'product_id' => $dto->productId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Activate a marketplace product
     */
    public function activateMarketplaceProduct(ProductSyncDTO $dto, SyncQueueInbound $syncQueue): array
    {
        DB::connection('central')->beginTransaction();

        try {
            $marketplaceProduct = MarketplaceProduct::withTrashed()
                ->where('tenant_id', $dto->tenantId)
                ->where('tenant_product_id', $dto->productId)
                ->where('tenant_product_type', $dto->productType)
                ->whereNull('tenant_variant_id')
                ->whereNull('tenant_bundle_id')
                ->first();

            if (!$marketplaceProduct) {
                Log::warning('Marketplace product not found for activation, creating instead', [
                    'tenant_id' => $dto->tenantId,
                    'tenant_product_id' => $dto->productId,
                ]);

                // Create if doesn't exist
                $result = $this->createMarketplaceProduct($dto, $syncQueue);

                DB::connection('central')->commit();

                return $result;
            }

            $syncQueue->markAsSyncing();

            // Restore if soft deleted
            if ($marketplaceProduct->trashed()) {
                $marketplaceProduct->restore();

                Log::info('Marketplace product restored from soft delete', [
                    'marketplace_product_id' => $marketplaceProduct->id,
                ]);
            }

            // Mark as active 
            $marketplaceProduct->update([
                'is_active' => true,
                'is_featured' => $dto->isFeatured,
                'last_synced_at' => now(),
            ]);

            DB::connection('central')->commit();

            Log::info('Marketplace product activated successfully', [
                'tenant_id' => $dto->tenantId,
                'tenant_product_id' => $dto->productId,
                'marketplace_product_id' => $marketplaceProduct->id,
                'is_featured' => $dto->isFeatured,
            ]);

            return [
                'marketplace_product_id' => $marketplaceProduct->id,
                'action' => 'activated',
            ];
        } catch (\Exception $e) {
            DB::connection('central')->rollBack();

            Log::error('Failed to activate marketplace product', [
                'tenant_id' => $dto->tenantId,
                'product_id' => $dto->productId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Deactivate a marketplace product
     */
    public function deactivateMarketplaceProduct(ProductSyncDTO $dto, SyncQueueInbound $syncQueue): array
    {
        DB::connection('central')->beginTransaction();

        try {
            $marketplaceProduct = MarketplaceProduct::where('tenant_id', $dto->tenantId)
                ->where('tenant_product_id', $dto->productId)
                ->where('tenant_product_type', $dto->productType)
                ->whereNull('tenant_variant_id')
                ->whereNull('tenant_bundle_id')
                ->first();

            if (!$marketplaceProduct) {
                Log::warning('Marketplace product not found for deactivation', [
                    'tenant_id' => $dto->tenantId,
                    'tenant_product_id' => $dto->productId,
                ]);

                DB::connection('central')->commit();

                return [
                    'marketplace_product_id' => null,
                    'action' => 'deactivate_skipped',
                    'reason' => 'product_not_found',
                ];
            }

            $syncQueue->markAsSyncing();

            // Mark as inactive and un-feature
            $marketplaceProduct->update([
                'is_active' => false,
                'is_featured' => false,
                'last_synced_at' => now(),
            ]);

            DB::connection('central')->commit();

            Log::info('Marketplace product deactivated successfully', [
                'tenant_id' => $dto->tenantId,
                'tenant_product_id' => $dto->productId,
                'marketplace_product_id' => $marketplaceProduct->id,
                'note' => 'Product also un-featured automatically',
            ]);

            return [
                'marketplace_product_id' => $marketplaceProduct->id,
                'action' => 'deactivated',
            ];
        } catch (\Exception $e) {
            DB::connection('central')->rollBack();

            Log::error('Failed to deactivate marketplace product', [
                'tenant_id' => $dto->tenantId,
                'product_id' => $dto->productId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Map tenant category to marketplace category
     */
    protected function mapCategory(ProductSyncDTO $dto): array
    {
        // Check if mapping already exists
        $existingMapping = TenantCategoryMapping::where('tenant_id', $dto->tenantId)
            ->where('tenant_category_id', $dto->category->id)
            ->first();

        if ($existingMapping) {
            Log::debug('Using existing category mapping', [
                'tenant_id' => $dto->tenantId,
                'tenant_category_id' => $dto->category->id,
                'marketplace_category_id' => $existingMapping->marketplace_category_id,
                'confidence' => $existingMapping->confidence_score,
            ]);

            return [
                'category_id' => $existingMapping->marketplace_category_id,
                'confidence' => $existingMapping->confidence_score,
                'is_verified' => $existingMapping->is_verified,
            ];
        }

        // Auto-map using slug/name matching
        $matchResult = MarketplaceCategory::findBestMatch(
            $dto->category->slug,
            $dto->category->name
        );

        if (!$matchResult) {
            // No match found - use generic category
            $genericCategory = MarketplaceCategory::where('slug', 'uncategorized')->first();

            if (!$genericCategory) {
                throw new \RuntimeException('No suitable marketplace category found and no generic category available');
            }

            Log::warning('No category match found, using generic category', [
                'tenant_id' => $dto->tenantId,
                'tenant_category' => $dto->category->name,
                'marketplace_category' => $genericCategory->name,
            ]);

            $matchResult = [
                'category' => $genericCategory,
                'confidence' => 0.0,
            ];
        }

        // Create mapping record
        $mapping = TenantCategoryMapping::create([
            'tenant_id' => $dto->tenantId,
            'tenant_category_id' => $dto->category->id,
            'tenant_category_name' => $dto->category->name,
            'tenant_category_slug' => $dto->category->slug,
            'marketplace_category_id' => $matchResult['category']->id,
            'confidence_score' => $matchResult['confidence'],
            'is_auto_mapped' => true,
            'is_verified' => $matchResult['confidence'] >= 90, // Auto-verify if confidence >= 90%
        ]);

        Log::info('Category mapping created', [
            'tenant_id' => $dto->tenantId,
            'tenant_category' => $dto->category->name,
            'marketplace_category' => $matchResult['category']->name,
            'confidence' => $matchResult['confidence'],
            'needs_verification' => $mapping->needsVerification(),
        ]);

        // TODO: If needs verification, trigger notification to merchant
        if ($mapping->needsVerification()) {
            Log::notice('Category mapping needs merchant verification', [
                'tenant_id' => $dto->tenantId,
                'mapping_id' => $mapping->id,
                'confidence' => $mapping->confidence_score,
            ]);
        }

        return [
            'category_id' => $matchResult['category']->id,
            'confidence' => $matchResult['confidence'],
            'is_verified' => $mapping->is_verified,
        ];
    }

    /**
     * Map tenant brand to marketplace brand
     */
    protected function mapBrand(ProductSyncDTO $dto): ?array
    {
        if (!$dto->brand) {
            return null;
        }

        // Check if mapping already exists
        $existingMapping = TenantBrandMapping::where('tenant_id', $dto->tenantId)
            ->where('tenant_brand_id', $dto->brand->id)
            ->first();

        if ($existingMapping) {
            Log::debug('Using existing brand mapping', [
                'tenant_id' => $dto->tenantId,
                'tenant_brand_id' => $dto->brand->id,
                'marketplace_brand_id' => $existingMapping->marketplace_brand_id,
                'confidence' => $existingMapping->confidence_score,
            ]);

            return [
                'brand_id' => $existingMapping->marketplace_brand_id,
                'confidence' => $existingMapping->confidence_score,
                'is_verified' => $existingMapping->is_verified,
            ];
        }

        // Auto-map using slug/name matching
        $matchResult = MarketplaceBrand::findBestMatch(
            $dto->brand->slug,
            $dto->brand->name
        );

        if (!$matchResult) {
            // No match found - use generic brand
            $genericBrand = MarketplaceBrand::where('slug', 'generic')->first();

            if (!$genericBrand) {
                Log::warning('No brand match found and no generic brand available', [
                    'tenant_id' => $dto->tenantId,
                    'tenant_brand' => $dto->brand->name,
                ]);

                return null; // Allow null brand
            }

            Log::warning('No brand match found, using generic brand', [
                'tenant_id' => $dto->tenantId,
                'tenant_brand' => $dto->brand->name,
                'marketplace_brand' => $genericBrand->name,
            ]);

            $matchResult = [
                'brand' => $genericBrand,
                'confidence' => 0.0,
            ];
        }

        // Create mapping record
        $mapping = TenantBrandMapping::create([
            'tenant_id' => $dto->tenantId,
            'tenant_brand_id' => $dto->brand->id,
            'tenant_brand_name' => $dto->brand->name,
            'tenant_brand_slug' => $dto->brand->slug,
            'marketplace_brand_id' => $matchResult['brand']->id,
            'confidence_score' => $matchResult['confidence'],
            'is_auto_mapped' => true,
            'is_verified' => $matchResult['confidence'] >= 90, // Auto-verify if confidence >= 90%
        ]);

        Log::info('Brand mapping created', [
            'tenant_id' => $dto->tenantId,
            'tenant_brand' => $dto->brand->name,
            'marketplace_brand' => $matchResult['brand']->name,
            'confidence' => $matchResult['confidence'],
            'needs_verification' => $mapping->needsVerification(),
        ]);

        // TODO: If needs verification, trigger notification to merchant
        if ($mapping->needsVerification()) {
            Log::notice('Brand mapping needs merchant verification', [
                'tenant_id' => $dto->tenantId,
                'mapping_id' => $mapping->id,
                'confidence' => $mapping->confidence_score,
            ]);
        }

        return [
            'brand_id' => $matchResult['brand']->id,
            'confidence' => $matchResult['confidence'],
            'is_verified' => $mapping->is_verified,
        ];
    }

    /**
     * Update existing marketplace product with new data
     */
    /**
     * Update existing marketplace product with new data
     */
    protected function updateExistingMarketplaceProduct(
        MarketplaceProduct $marketplaceProduct,
        ProductSyncDTO $dto,
        array $categoryMapping,
        ?array $brandMapping
    ): array {
        $updateData = [
            // Product Details
            'name' => $dto->name,
            'slug' => $this->generateUniqueSlug($dto->slug, $dto->tenantId, $marketplaceProduct->id),
            'description' => $dto->description,
            'online_description' => $dto->onlineDescription,
            'sku' => $dto->sku,

            // Tenant Original Categorization
            'tenant_category_id' => $dto->category->id,
            'tenant_category_name' => $dto->category->name,
            'tenant_brand_id' => $dto->brand?->id,
            'tenant_brand_name' => $dto->brand?->name,

            // Marketplace Mapped Categorization
            'marketplace_category_id' => $categoryMapping['category_id'],
            'marketplace_brand_id' => $brandMapping['brand_id'] ?? null,

            // Pricing & UOM
            'online_price' => $dto->onlinePrice,
            'base_uom_code' => $dto->baseUom->code,
            'base_uom_name' => $dto->baseUom->name,
            'tax_rate' => $dto->taxRate,

            // Inventory
            'available_quantity' => $dto->inventory->availableQuantity,
            'stock_status' => $dto->inventory->stockStatus,

            // Media
            'primary_image' => $dto->primaryImage,
            'secondary_images' => $dto->secondaryImages,

            'is_featured' => $dto->isActive ? $dto->isFeatured : false,

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

        // Log what changed
        if (!empty($changedFields)) {
            Log::info('Marketplace product fields updated', [
                'marketplace_product_id' => $marketplaceProduct->id,
                'tenant_id' => $dto->tenantId,
                'changed_fields' => array_keys($changedFields),
            ]);

            // Special log for featured status change
            if (isset($changedFields['is_featured'])) {
                Log::info('Marketplace product featured status changed', [
                    'marketplace_product_id' => $marketplaceProduct->id,
                    'old_featured' => $changedFields['is_featured']['old'],
                    'new_featured' => $changedFields['is_featured']['new'],
                ]);
            }
        }

        return [
            'marketplace_product_id' => $marketplaceProduct->id,
            'action' => 'updated',
            'category_mapping' => $categoryMapping,
            'brand_mapping' => $brandMapping,
            'changed_fields' => array_keys($changedFields),
        ];
    }

    /**
     * Generate unique slug for marketplace product
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
     * Validate product data from DTO
     */
    protected function validateProductData(ProductSyncDTO $dto): void
    {
        if ($dto->onlinePrice <= 0) {
            throw new \InvalidArgumentException('Online price must be greater than 0');
        }

        if ($dto->taxRate < 0 || $dto->taxRate > 100) {
            throw new \InvalidArgumentException('Tax rate must be between 0 and 100');
        }

        if ($dto->inventory->availableQuantity < 0) {
            throw new \InvalidArgumentException('Available quantity cannot be negative');
        }

        if (!in_array($dto->inventory->stockStatus, ['in_stock', 'low_stock', 'out_of_stock'])) {
            throw new \InvalidArgumentException('Invalid stock status');
        }
    }
}
