<?php

namespace App\Services\Tenant\Store;

use App\Models\Tenant\Store;
use App\Models\Tenant\StoreProduct;
use App\Repositories\Tenant\StoreProductRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class StoreProductService
{
    public function __construct(
        protected StoreProductRepository $repository
    ) {}

    /**
     * Get tenant cache prefix
     */
    protected function getTenantCachePrefix(): string
    {
        return 'tenant_' . tenant()->id . '_';
    }

    /**
     * Get cache tags for store products
     */
    protected function getCacheTags(int $storeId): array
    {
        return [
            'tenant:' . tenant()->id,
            'store_products',
            'store:' . $storeId,
        ];
    }

    /**
     * Resolve store ID (auto-detect if only one store exists)
     */
    public function resolveStoreId(?int $storeId = null): int
    {
        // If store ID provided, validate and return
        if ($storeId) {
            $store = Store::where('is_active', true)->find($storeId);

            if (!$store) {
                throw new InvalidArgumentException('Store not found or inactive.');
            }

            return $storeId;
        }

        // Auto-detect if only one store exists
        $activeStores = Store::where('is_active', true)->get(['id', 'name']);

        if ($activeStores->isEmpty()) {
            throw new InvalidArgumentException('No active stores found.');
        }

        if ($activeStores->count() === 1) {
            return $activeStores->first()->id;
        }

        // Multiple stores exist, store ID is required
        throw new InvalidArgumentException(
            'Multiple stores exist. Please specify store_id. Available stores: ' .
                $activeStores->pluck('name', 'id')->toJson()
        );
    }

    /**
     * List store products with pagination
     */
    public function listStoreProducts(
        int $storeId,
        array $filters = [],
        int $perPage = 15
    ): LengthAwarePaginator {
        $cacheKey = $this->getTenantCachePrefix() . "store_{$storeId}_products_" .
            md5(json_encode($filters) . $perPage);

        return Cache::tags($this->getCacheTags($storeId))
            ->remember($cacheKey, 300, function () use ($storeId, $filters, $perPage) {
                return $this->repository->getStoreProducts($storeId, $filters, $perPage);
            });
    }

    /**
     * Get all store products (no pagination)
     */
    public function getAllStoreProducts(int $storeId, array $filters = []): Collection
    {
        return $this->repository->getAllStoreProducts($storeId, $filters);
    }

    /**
     * Get store product by ID
     */
    public function getStoreProduct(int $id, int $storeId): ?StoreProduct
    {
        $cacheKey = $this->getTenantCachePrefix() . "store_product_{$id}";

        return Cache::tags($this->getCacheTags($storeId))
            ->remember($cacheKey, 600, function () use ($id, $storeId) {
                return $this->repository->findStoreProduct($id, $storeId);
            });
    }

    /**
     * Assign products to store
     */
    public function assignProductsToStore(
        int $storeId,
        array $productIds,
        array $options = []
    ): array {
        $autoAssignVariants = $options['auto_assign_variants'] ?? true;
        $autoAssignBundles = $options['auto_assign_bundles'] ?? false;

        $defaults = [
            'store_selling_price' => $options['store_selling_price'] ?? null,
            'min_stock_level' => $options['min_stock_level'] ?? 0,
            'is_available' => $options['is_available'] ?? true,
        ];

        $results = [
            'assigned' => [],
            'updated' => [],
            'skipped' => [],
            'variants_assigned' => [],
            'bundles_assigned' => [],
        ];

        DB::transaction(function () use (
            $storeId,
            $productIds,
            $autoAssignVariants,
            $autoAssignBundles,
            $defaults,
            &$results
        ) {
            foreach ($productIds as $productId) {
                try {
                    // Assign main product
                    $exists = $this->repository->isProductAssignedToStore($storeId, $productId);

                    if ($exists) {
                        // Update existing assignment
                        $storeProduct = $this->repository->updateOrCreate($storeId, $productId, $defaults);
                        $results['updated'][] = $productId;

                        Log::info("Updated product assignment", [
                            'tenant_id' => tenant()->id,
                            'store_id' => $storeId,
                            'product_id' => $productId,
                        ]);
                    } else {
                        // Create new assignment
                        $storeProduct = $this->repository->assignProduct($storeId, $productId, $defaults);
                        $results['assigned'][] = $productId;

                        Log::info("Assigned product to store", [
                            'tenant_id' => tenant()->id,
                            'store_id' => $storeId,
                            'product_id' => $productId,
                        ]);
                    }

                    // Auto-assign variants if enabled
                    if ($autoAssignVariants) {
                        $variantResults = $this->assignProductVariants($storeId, $productId, $defaults);
                        $results['variants_assigned'] = array_merge(
                            $results['variants_assigned'],
                            $variantResults
                        );
                    }

                    // Auto-assign bundles if enabled
                    if ($autoAssignBundles) {
                        $bundleResults = $this->assignProductBundles($storeId, $productId, $defaults);
                        $results['bundles_assigned'] = array_merge(
                            $results['bundles_assigned'],
                            $bundleResults
                        );
                    }
                } catch (\Exception $e) {
                    $results['skipped'][] = [
                        'product_id' => $productId,
                        'reason' => $e->getMessage(),
                    ];

                    Log::error("Failed to assign product to store", [
                        'tenant_id' => tenant()->id,
                        'store_id' => $storeId,
                        'product_id' => $productId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        });

        // Clear cache
        $this->clearStoreProductsCache($storeId);

        return $results;
    }

    /**
     * Auto-assign product variants to store
     */
    protected function assignProductVariants(int $storeId, int $productId, array $defaults): array
    {
        $variants = $this->repository->getProductVariants($productId);
        $assigned = [];

        foreach ($variants as $variant) {
            try {
                // Check if variant is already assigned
                // Use product_id (parent) + product_variant_id (this variant)
                if (!$this->repository->isProductAssignedToStore($storeId, $productId, $variant->id)) {
                    $this->repository->assignProduct($storeId, $productId, [
                        'product_variant_id' => $variant->id,
                        'store_selling_price' => $defaults['store_selling_price'] ?? null,
                        'min_stock_level' => $defaults['min_stock_level'] ?? 0,
                        'is_available' => $defaults['is_available'] ?? true,
                    ]);

                    $assigned[] = [
                        'variant_id' => $variant->id,
                        'variant_name' => $variant->variant_name,
                        'sku' => $variant->sku,
                    ];
                }
            } catch (\Exception $e) {
                Log::warning("Failed to assign variant", [
                    'tenant_id' => tenant()->id,
                    'store_id' => $storeId,
                    'product_id' => $productId,
                    'variant_id' => $variant->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $assigned;
    }

    /**
     * Auto-assign bundles containing product to store
     */
    protected function assignProductBundles(int $storeId, int $productId, array $defaults): array
    {
        $bundles = $this->repository->getBundlesContainingProduct($productId);
        $assigned = [];

        foreach ($bundles as $bundle) {
            try {
                $bundleId = $bundle->bundle_id;

                if (!$this->repository->isProductAssignedToStore($storeId, $bundleId)) {
                    $this->repository->assignProduct($storeId, $bundleId, $defaults);
                    $assigned[] = [
                        'bundle_id' => $bundleId,
                        'bundle_name' => $bundle->bundle_name,
                    ];
                }
            } catch (\Exception $e) {
                Log::warning("Failed to assign bundle", [
                    'tenant_id' => tenant()->id,
                    'store_id' => $storeId,
                    'bundle_id' => $bundle->bundle_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $assigned;
    }

    /**
     * Update store product configuration
     */
    public function updateStoreProduct(StoreProduct $storeProduct, array $data): bool
    {
        $updated = DB::transaction(function () use ($storeProduct, $data) {
            $result = $this->repository->updateStoreProduct($storeProduct, $data);

            return $result;
        });

        if ($updated) {
            $this->clearStoreProductsCache($storeProduct->store_id);
        }

        return $updated;
    }

    /**
     * Toggle product availability
     */
    public function toggleAvailability(StoreProduct $storeProduct, bool $isAvailable): bool
    {
        return $this->updateStoreProduct($storeProduct, [
            'is_available' => $isAvailable,
        ]);
    }

    /**
     * Remove product from store (unassign)
     */
    public function removeProductFromStore(StoreProduct $storeProduct): bool
    {
        $deleted = DB::transaction(function () use ($storeProduct) {
            $storeId = $storeProduct->store_id;
            $productId = $storeProduct->product_id;

            $result = $this->repository->deleteStoreProduct($storeProduct);

            Log::info("Removed product from store", [
                'tenant_id' => tenant()->id,
                'store_id' => $storeId,
                'product_id' => $productId,
            ]);

            return $result;
        });

        if ($deleted) {
            $this->clearStoreProductsCache($storeProduct->store_id);
        }

        return $deleted;
    }

    /**
     * Get store products statistics
     */
    public function getStoreProductsStats(int $storeId): array
    {
        $cacheKey = $this->getTenantCachePrefix() . "store_{$storeId}_stats";

        return Cache::tags($this->getCacheTags($storeId))
            ->remember($cacheKey, 600, function () use ($storeId) {
                $allProducts = $this->repository->getAllStoreProducts($storeId);

                return [
                    'total_products' => $allProducts->count(),
                    'available_products' => $allProducts->where('is_available', true)->count(),
                    'unavailable_products' => $allProducts->where('is_available', false)->count(),
                    'price_overrides' => $allProducts->whereNotNull('store_selling_price')->count(),
                    'low_stock_products' => $allProducts->filter(fn($sp) => $sp->is_low_stock)->count(),
                    'out_of_stock_products' => $allProducts->filter(fn($sp) => $sp->is_out_of_stock)->count(),
                ];
            });
    }

    /**
     * Clear store products cache
     */
    public function clearStoreProductsCache(int $storeId): void
    {
        Cache::tags($this->getCacheTags($storeId))->flush();
    }
}
