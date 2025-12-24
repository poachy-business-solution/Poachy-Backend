<?php

namespace App\Repositories\Tenant;

use App\Models\Tenant\Product;
use App\Models\Tenant\ProductBundle;
use App\Models\Tenant\ProductVariant;
use App\Models\Tenant\Store;
use App\Models\Tenant\StoreProduct;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class StoreProductRepository
{
    /**
     * Get store products with filters and pagination
     */
    public function getStoreProducts(
        int $storeId,
        array $filters = [],
        int $perPage = 15
    ): LengthAwarePaginator {
        $query = StoreProduct::query()
            ->forStore($storeId)
            ->withDetails();
        // ->withInventory();

        // Apply filters
        $this->applyFilters($query, $filters);

        // Apply sorting
        $sortBy = $filters['sort_by'] ?? 'product.name';
        $sortOrder = $filters['sort_order'] ?? 'asc';

        $this->applySorting($query, $sortBy, $sortOrder);

        return $query->paginate($perPage);
    }

    /**
     * Get all store products (no pagination)
     */
    public function getAllStoreProducts(int $storeId, array $filters = []): Collection
    {
        $query = StoreProduct::query()
            ->forStore($storeId)
            ->withDetails();
        // ->withInventory();

        $this->applyFilters($query, $filters);

        return $query->get();
    }

    /**
     * Find store product by ID
     */
    public function findStoreProduct(int $id, int $storeId): ?StoreProduct
    {
        return StoreProduct::query()
            ->forStore($storeId)
            ->withDetails()
            // ->withInventory()
            ->find($id);
    }

    /**
     * Find store product by store and product IDs
     */
    public function findByStoreAndProduct(int $storeId, int $productId): ?StoreProduct
    {
        return StoreProduct::query()
            ->forStore($storeId)
            ->forProduct($productId)
            ->withDetails()
            ->first();
    }

    /**
     * Check if product is already assigned to store
     */
    public function isProductAssignedToStore(int $storeId, int $productId, ?int $variantId = null): bool
    {
        return StoreProduct::query()
            ->forStore($storeId)
            ->forProduct($productId)
            ->where('product_variant_id', $variantId)
            ->exists();
    }

    /**
     * Assign product to store
     */
    public function assignProduct(int $storeId, int $productId, array $data = []): StoreProduct
    {
        return StoreProduct::create([
            'store_id' => $storeId,
            'product_id' => $productId,
            'product_variant_id' => $data['product_variant_id'] ?? null,
            'store_selling_price' => $data['store_selling_price'] ?? null,
            'min_stock_level' => $data['min_stock_level'] ?? 0,
            'is_available' => $data['is_available'] ?? true,
        ]);
    }

    /**
     * Update or create store product
     */
    public function updateOrCreate(int $storeId, int $productId, array $data = []): StoreProduct
    {
        return StoreProduct::updateOrCreate(
            [
                'store_id' => $storeId,
                'product_id' => $productId,
                'product_variant_id' => $data['product_variant_id'] ?? null,
            ],
            [
                'store_selling_price' => $data['store_selling_price'] ?? null,
                'min_stock_level' => $data['min_stock_level'] ?? 0,
                'is_available' => $data['is_available'] ?? true,
            ]
        );
    }

    /**
     * Update store product
     */
    public function updateStoreProduct(StoreProduct $storeProduct, array $data): bool
    {
        return $storeProduct->update($data);
    }

    /**
     * Delete store product (unassign)
     */
    public function deleteStoreProduct(StoreProduct $storeProduct): bool
    {
        return $storeProduct->delete();
    }

    /**
     * Bulk assign products to store
     */
    public function bulkAssignProducts(int $storeId, array $productIds, array $defaults = []): int
    {
        $timestamp = now();
        $records = [];

        foreach ($productIds as $productId) {
            $records[] = [
                'store_id' => $storeId,
                'product_id' => $productId,
                'store_selling_price' => $defaults['store_selling_price'] ?? null,
                'min_stock_level' => $defaults['min_stock_level'] ?? 0,
                'is_available' => $defaults['is_available'] ?? true,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];
        }

        // Use insert ignore to skip duplicates
        return DB::connection('tenant')
            ->table('store_products')
            ->insertOrIgnore($records);
    }

    /**
     * Get product variants with proper structure
     */
    public function getProductVariants(int $productId): Collection
    {
        return ProductVariant::where('product_id', $productId)
            ->where('is_active', true)
            ->select(['id', 'product_id', 'variant_name', 'sku'])
            ->get();
    }

    /**
     * Get bundles containing product
     */
    public function getBundlesContainingProduct(int $productId): Collection
    {
        return ProductBundle::query()
            ->join('product_bundle_items', 'product_bundles.id', '=', 'product_bundle_items.bundle_id')
            ->where('product_bundle_items.product_id', $productId)
            ->where('product_bundles.is_active', true)
            ->distinct()
            ->select(['product_bundles.id as bundle_id', 'product_bundles.bundle_name'])
            ->get();
    }

    /**
     * Get store products count
     */
    public function getStoreProductsCount(int $storeId, array $filters = []): int
    {
        $query = StoreProduct::query()->forStore($storeId);
        $this->applyFilters($query, $filters);
        return $query->count();
    }

    /**
     * Get available products count
     */
    public function getAvailableProductsCount(int $storeId): int
    {
        return StoreProduct::query()
            ->forStore($storeId)
            ->available()
            ->count();
    }

    /**
     * Get low stock products
     */
    public function getLowStockProducts(int $storeId, int $limit = 10): Collection
    {
        return StoreProduct::query()
            ->forStore($storeId)
            ->available()
            ->withDetails()
            // ->withInventory()
            ->get()
            ->filter(fn($sp) => $sp->is_low_stock)
            ->take($limit);
    }

    /**
     * Apply filters to query
     */
    protected function applyFilters($query, array $filters): void
    {
        // Filter by availability
        if (isset($filters['is_available'])) {
            $query->where('is_available', $filters['is_available']);
        }

        // Filter by price override
        if (isset($filters['has_price_override'])) {
            $filters['has_price_override']
                ? $query->withPriceOverride()
                : $query->withoutPriceOverride();
        }

        // Filter by stock status
        if (isset($filters['stock_status'])) {
            // This needs to be applied after loading inventory
            // Will be handled in service layer
        }

        // Search by product name or SKU
        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->whereHas('product', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        // Filter by category
        if (isset($filters['category_id'])) {
            $query->whereHas('product', function ($q) use ($filters) {
                $q->where('category_id', $filters['category_id']);
            });
        }

        // Filter by brand
        if (isset($filters['brand_id'])) {
            $query->whereHas('product', function ($q) use ($filters) {
                $q->where('brand_id', $filters['brand_id']);
            });
        }
    }

    /**
     * Apply sorting to query
     */
    protected function applySorting($query, string $sortBy, string $sortOrder): void
    {
        $validOrders = ['asc', 'desc'];
        $order = in_array(strtolower($sortOrder), $validOrders) ? $sortOrder : 'asc';

        switch ($sortBy) {
            case 'product.name':
                $query->join('products', 'store_products.product_id', '=', 'products.id')
                    ->orderBy('products.name', $order)
                    ->select('store_products.*');
                break;

            case 'product.sku':
                $query->join('products', 'store_products.product_id', '=', 'products.id')
                    ->orderBy('products.sku', $order)
                    ->select('store_products.*');
                break;

            case 'is_available':
                $query->orderBy('is_available', $order);
                break;

            case 'created_at':
                $query->orderBy('created_at', $order);
                break;

            case 'updated_at':
                $query->orderBy('updated_at', $order);
                break;

            default:
                $query->orderBy('id', $order);
        }
    }
}
