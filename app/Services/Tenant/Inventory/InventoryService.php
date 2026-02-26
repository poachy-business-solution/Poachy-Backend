<?php

namespace App\Services\Tenant\Inventory;

use App\Models\Tenant\Inventory;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductUom;
use App\Models\Tenant\Store;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    /**
     * Cache TTL in seconds (5 minutes)
     */
    private const CACHE_TTL = 300;

    /**
     * Get inventory for a store with optional filters
     *
     * @param int $storeId
     * @param array $filters [
     *   'product_id' => int,
     *   'category_id' => int,
     *   'brand_id' => int,
     *   'stock_status' => string (low_stock|out_of_stock|in_stock),
     *   'search' => string,
     *   'per_page' => int
     * ]
     * @return LengthAwarePaginator
     */
    public function getInventoryForStore(int $storeId, array $filters = []): LengthAwarePaginator
    {
        $query = Inventory::with(['product', 'product.baseUom', 'productVariant', 'store'])
            ->where('store_id', $storeId);

        // Apply filters
        if (!empty($filters['product_id'])) {
            $query->where('product_id', $filters['product_id']);
        }

        if (!empty($filters['category_id'])) {
            $query->whereHas('product', function ($q) use ($filters) {
                $q->where('category_id', $filters['category_id']);
            });
        }

        if (!empty($filters['brand_id'])) {
            $query->whereHas('product', function ($q) use ($filters) {
                $q->where('brand_id', $filters['brand_id']);
            });
        }

        if (!empty($filters['stock_status'])) {
            match ($filters['stock_status']) {
                'low_stock' => $query->lowStock(),
                'out_of_stock' => $query->outOfStock(),
                'in_stock' => $query->inStock(),
                default => null,
            };
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->whereHas('product', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        $perPage = $filters['per_page'] ?? 20;

        return $query->orderBy('product_id')
            ->paginate($perPage);
    }

    /**
     * Get inventory for a specific product across all stores or specific store
     *
     * @param int $productId
     * @param int|null $storeId
     * @param int|null $variantId
     * @return Collection|Inventory|null
     */
    public function getInventoryForProduct(
        int $productId,
        ?int $storeId = null,
        ?int $variantId = null
    ): Collection|Inventory|null {
        $query = Inventory::with(['store', 'product', 'product.baseUom', 'productVariant'])
            ->where('product_id', $productId)
            ->where('product_variant_id', $variantId);

        if ($storeId) {
            return $query->where('store_id', $storeId)->first();
        }

        return $query->get();
    }

    /**
     * Check if stock is available for a product in a store
     *
     * @param int $productId
     * @param float $quantity Quantity in the specified UOM
     * @param int $storeId
     * @param int $uomId UOM of the requested quantity
     * @param int|null $variantId
     * @return array [
     *   'available' => bool,
     *   'requested_quantity' => float,
     *   'available_quantity' => float,
     *   'requested_in_base_uom' => float,
     *   'available_in_base_uom' => float,
     *   'base_uom' => string
     * ]
     */
    public function checkAvailability(
        int $productId,
        float $quantity,
        int $storeId,
        int $uomId,
        ?int $variantId = null
    ): array {
        // Get inventory record
        $inventory = Inventory::getForProduct($productId, $storeId, $variantId);

        if (!$inventory) {
            return [
                'available' => false,
                'requested_quantity' => $quantity,
                'available_quantity' => 0,
                'requested_in_base_uom' => 0,
                'available_in_base_uom' => 0,
                'base_uom' => $this->getBaseUomCode($productId),
                'message' => 'Product not found in store inventory',
            ];
        }

        // Convert requested quantity to base UOM
        $quantityInBaseUom = $this->convertToBaseUom($quantity, $uomId, $productId);

        // Get base UOM code
        $baseUom = $inventory->product->baseUom->code ?? 'units';

        // Check availability
        $available = $inventory->quantity_available >= $quantityInBaseUom;

        return [
            'available' => $available,
            'requested_quantity' => $quantity,
            'available_quantity' => $available
                ? $quantity
                : $this->convertFromBaseUom($inventory->quantity_available, $uomId, $productId),
            'requested_in_base_uom' => $quantityInBaseUom,
            'available_in_base_uom' => $inventory->quantity_available,
            'base_uom' => $baseUom,
            'message' => $available ? 'Stock available' : 'Insufficient stock',
        ];
    }

    /**
     * Get low stock products for a store
     *
     * @param int $storeId
     * @param float|null $customThreshold Optional custom threshold
     * @return Collection
     */
    public function getLowStockProducts(int $storeId, ?float $customThreshold = null): Collection
    {
        $cacheKey = $this->getCacheKey("low_stock:{$storeId}:{$customThreshold}");

        return Cache::tags($this->getCacheTags($storeId))->remember(
            $cacheKey,
            self::CACHE_TTL,
            function () use ($storeId, $customThreshold) {
                $query = Inventory::withDetails()
                    ->where('store_id', $storeId)
                    ->where('quantity_available', '>', 0); // Not out of stock

                if ($customThreshold !== null) {
                    $query->where('quantity_available', '<=', $customThreshold);
                } else {
                    $query->lowStock();
                }

                return $query->orderBy('quantity_available', 'asc')->get();
            }
        );
    }

    /**
     * Get out of stock products for a store
     *
     * @param int $storeId
     * @return Collection
     */
    public function getOutOfStockProducts(int $storeId): Collection
    {
        $cacheKey = $this->getCacheKey("out_of_stock:{$storeId}");

        return Cache::tags($this->getCacheTags($storeId))->remember(
            $cacheKey,
            self::CACHE_TTL,
            function () use ($storeId) {
                return Inventory::withDetails()
                    ->where('store_id', $storeId)
                    ->outOfStock()
                    ->get();
            }
        );
    }

    /**
     * Calculate total inventory value for a store
     *
     * @param int $storeId
     * @param int|null $productId Optional - calculate for specific product
     * @return array [
     *   'total_value' => float,
     *   'total_quantity' => float,
     *   'product_count' => int,
     *   'currency' => string
     * ]
     */
    public function getInventoryValue(int $storeId, ?int $productId = null): array
    {
        $query = Inventory::join('products', 'inventory.product_id', '=', 'products.id')
            ->where('inventory.store_id', $storeId);

        if ($productId) {
            $query->where('inventory.product_id', $productId);
        }

        $result = $query->selectRaw('
            SUM(inventory.quantity_on_hand * products.base_selling_price) as total_value,
            SUM(inventory.quantity_on_hand) as total_quantity,
            COUNT(DISTINCT inventory.product_id) as product_count
        ')->first();

        return [
            'total_value' => round($result->total_value ?? 0, 2),
            'total_quantity' => round($result->total_quantity ?? 0, 4),
            'product_count' => $result->product_count ?? 0,
            'currency' => 'KES',
        ];
    }

    /**
     * Get inventory summary for a store (dashboard)
     *
     * @param int $storeId
     * @return array [
     *   'total_products' => int,
     *   'in_stock_count' => int,
     *   'low_stock_count' => int,
     *   'out_of_stock_count' => int,
     *   'total_quantity_on_hand' => float,
     *   'total_quantity_reserved' => float,
     *   'total_quantity_available' => float,
     *   'total_value' => float
     * ]
     */
    public function getInventorySummary(int $storeId): array
    {
        $cacheKey = $this->getCacheKey("summary:{$storeId}");

        return Cache::tags($this->getCacheTags($storeId))->remember(
            $cacheKey,
            self::CACHE_TTL,
            function () use ($storeId) {
                $store = Store::findOrFail($storeId);
                $summary = $store->getInventorySummary();
                $value = $this->getInventoryValue($storeId);

                return array_merge($summary, [
                    'total_value' => $value['total_value'],
                    'currency' => $value['currency'],
                ]);
            }
        );
    }

    /**
     * Get inventory movements for a product/store
     *
     * @param array $filters
     * @return LengthAwarePaginator
     */
    public function getInventoryMovements(array $filters = []): LengthAwarePaginator
    {
        $query = \App\Models\Tenant\InventoryMovement::withDetails();

        if (!empty($filters['store_id'])) {
            $query->byStore($filters['store_id']);
        }

        if (!empty($filters['product_id'])) {
            $query->byProduct($filters['product_id']);
        }

        if (!empty($filters['movement_type'])) {
            $query->byType($filters['movement_type']);
        }

        $fromDate = $filters['from_date'] ?? null;
        $toDate = $filters['to_date'] ?? null;

        if ($fromDate === null && $toDate === null) {
            $fromDate = now()->subDays(6)->toDateString();
            $toDate = now()->toDateString();
        }

        $query->byDateRange($fromDate, $toDate);

        $perPage = $filters['per_page'] ?? 20;

        return $query->recent()->paginate($perPage);
    }

    /**
     * Get inventory reservations with optional filters.
     *
     * @param array<string, mixed> $filters
     */
    public function getInventoryReservations(array $filters = []): LengthAwarePaginator
    {
        $query = \App\Models\Tenant\InventoryReservation::withDetails()
            ->with('cancelledBy:id,name');

        if (!empty($filters['store_id'])) {
            $query->byStore($filters['store_id']);
        }

        if (!empty($filters['product_id'])) {
            $query->byProduct($filters['product_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $fromDate = $filters['from_date'] ?? null;
        $toDate = $filters['to_date'] ?? null;

        if ($fromDate === null && $toDate === null) {
            $fromDate = now()->subDays(6)->toDateString();
            $toDate = now()->toDateString();
        }

        if ($fromDate) {
            $query->whereDate('created_at', '>=', $fromDate);
        }

        if ($toDate) {
            $query->whereDate('created_at', '<=', $toDate);
        }

        $perPage = $filters['per_page'] ?? 20;

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * Clear inventory cache for a store
     *
     * @param int $storeId
     * @return void
     */
    public function clearCache(int $storeId): void
    {
        Cache::tags($this->getCacheTags($storeId))->flush();
    }

    /**
     * Convert quantity from given UOM to product's base UOM
     *
     * @param float $quantity
     * @param int $uomId
     * @param int $productId
     * @return float
     */
    private function convertToBaseUom(float $quantity, int $uomId, int $productId): float
    {
        $product = Product::findOrFail($productId);

        // If already in base UOM, return as is
        if ($uomId === $product->base_uom_id) {
            return $quantity;
        }

        // Get conversion factor
        $productUom = ProductUom::where('product_id', $productId)
            ->where('uom_id', $uomId)
            ->firstOrFail();

        return $quantity * $productUom->conversion_to_base;
    }

    /**
     * Convert quantity from base UOM to given UOM
     *
     * @param float $quantity
     * @param int $uomId
     * @param int $productId
     * @return float
     */
    private function convertFromBaseUom(float $quantity, int $uomId, int $productId): float
    {
        $product = Product::findOrFail($productId);

        // If already in base UOM, return as is
        if ($uomId === $product->base_uom_id) {
            return $quantity;
        }

        // Get conversion factor
        $productUom = ProductUom::where('product_id', $productId)
            ->where('uom_id', $uomId)
            ->firstOrFail();

        if ($productUom->conversion_to_base == 0) {
            throw new \RuntimeException('Invalid conversion factor: cannot divide by zero');
        }

        return $quantity / $productUom->conversion_to_base;
    }

    /**
     * Get base UOM code for a product
     *
     * @param int $productId
     * @return string
     */
    private function getBaseUomCode(int $productId): string
    {
        $product = Product::with('baseUom')->find($productId);
        return $product?->baseUom?->code ?? 'units';
    }

    /**
     * Get cache key with tenant prefix
     *
     * @param string $key
     * @return string
     */
    private function getCacheKey(string $key): string
    {
        $tenantId = tenant()->id ?? 'global';
        return "inventory:{$tenantId}:{$key}";
    }

    /**
     * Get cache tags for inventory
     *
     * @param int $storeId
     * @return array
     */
    private function getCacheTags(int $storeId): array
    {
        $tenantId = tenant()->id ?? 'global';
        return [
            "tenant:{$tenantId}",
            "inventory:{$tenantId}",
            "store:{$storeId}:inventory",
        ];
    }
}
