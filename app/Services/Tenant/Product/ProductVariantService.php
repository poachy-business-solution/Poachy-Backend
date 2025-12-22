<?php

namespace App\Services\Tenant\Product;

use App\Models\Tenant\Product;
use App\Models\Tenant\ProductVariant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProductVariantService
{
    public function __construct(
        private SkuGeneratorService $skuGenerator
    ) {}

    /**
     * List variants for a specific product
     */
    public function listForProduct(Product $product, array $filters = []): Collection
    {
        $query = $product->variants()
            ->with(['uom']);

        // Apply filters
        if (!empty($filters['search'])) {
            $query->search($filters['search']);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        if (!empty($filters['status'])) {
            $query->where('stock_status', $filters['status']);
        }

        if (!empty($filters['attribute_key']) && !empty($filters['attribute_value'])) {
            $query->byAttribute($filters['attribute_key'], $filters['attribute_value']);
        }

        // Sorting
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        return $query->get();
    }

    /**
     * List all variants across all products with pagination
     */
    public function list(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = ProductVariant::query()
            ->with(['product:id,name,slug,sku', 'uom']);

        // Search
        if (!empty($filters['search'])) {
            $query->search($filters['search']);
        }

        // Product filter
        if (!empty($filters['product_id'])) {
            $query->where('product_id', $filters['product_id']);
        }

        // Status filter
        if (!empty($filters['status'])) {
            $query->where('stock_status', $filters['status']);
        }

        // Active filter
        if (isset($filters['is_active'])) {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        // Attribute filters
        if (!empty($filters['attribute_key']) && !empty($filters['attribute_value'])) {
            $query->byAttribute($filters['attribute_key'], $filters['attribute_value']);
        }

        // Sorting
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        return $query->paginate($perPage);
    }

    /**
     * Create a new variant for a product
     */
    public function create(Product $product, array $data): ProductVariant
    {
        return DB::transaction(function () use ($product, $data) {
            // Verify product is variable type
            if (!$product->isVariable()) {
                throw new \InvalidArgumentException('Cannot create variants for a simple product. Change product type to variable first.');
            }

            // Generate SKU if not provided
            if (empty($data['sku'])) {
                $data['sku'] = $this->skuGenerator->generateVariantSku($product, $data);
            }

            // Validate SKU uniqueness
            if (ProductVariant::where('sku', $data['sku'])->exists()) {
                throw new \InvalidArgumentException('SKU already exists');
            }

            // Calculate quantity in base UOM if not provided
            if (!isset($data['quantity_in_base_uom']) && isset($data['uom_id']) && isset($data['uom_quantity'])) {
                $data['quantity_in_base_uom'] = $this->calculateBaseUomQuantity(
                    $product,
                    $data['uom_id'],
                    $data['uom_quantity']
                );
            }

            // Calculate variant price if not provided
            if (!isset($data['variant_price']) && isset($data['base_selling_price_adjustment'])) {
                $data['variant_price'] = $product->base_selling_price + $data['base_selling_price_adjustment'];
            }

            $data['product_id'] = $product->id;
            $variant = ProductVariant::create($data);

            // Clear cache
            $this->clearVariantCache($product);

            Log::info('Product variant created', [
                'tenant_id' => tenant()->id,
                'product_id' => $product->id,
                'product_uuid' => $product->uuid,
                'variant_id' => $variant->id,
                'variant_sku' => $variant->sku,
            ]);

            return $variant->load(['product', 'uom']);
        });
    }

    /**
     * Get single variant by ID
     */
    public function getById(int $id): ProductVariant
    {
        return ProductVariant::with(['product', 'uom'])->findOrFail($id);
    }

    /**
     * Update variant
     */
    public function update(ProductVariant $variant, array $data): ProductVariant
    {
        return DB::transaction(function () use ($variant, $data) {
            // Validate SKU uniqueness if changed
            if (isset($data['sku']) && $data['sku'] !== $variant->sku) {
                if (ProductVariant::where('sku', $data['sku'])->where('id', '!=', $variant->id)->exists()) {
                    throw new \InvalidArgumentException('SKU already exists');
                }
            }

            // Recalculate quantity in base UOM if UOM or quantity changed
            if ((isset($data['uom_id']) || isset($data['uom_quantity'])) && !isset($data['quantity_in_base_uom'])) {
                $uomId = $data['uom_id'] ?? $variant->uom_id;
                $uomQuantity = $data['uom_quantity'] ?? $variant->uom_quantity;

                $data['quantity_in_base_uom'] = $this->calculateBaseUomQuantity(
                    $variant->product,
                    $uomId,
                    $uomQuantity
                );
            }

            // Recalculate variant price if adjustment changed
            if (isset($data['base_selling_price_adjustment']) && !isset($data['variant_price'])) {
                $data['variant_price'] = $variant->product->base_selling_price + $data['base_selling_price_adjustment'];
            }

            // Prevent changing product_id
            unset($data['product_id']);

            $variant->update($data);

            // Clear cache
            $this->clearVariantCache($variant->product);

            Log::info('Product variant updated', [
                'tenant_id' => tenant()->id,
                'product_id' => $variant->product_id,
                'variant_id' => $variant->id,
                'changes' => $variant->getChanges(),
            ]);

            return $variant->fresh(['product', 'uom']);
        });
    }

    /**
     * Toggle variant active status
     */
    public function toggleActive(ProductVariant $variant): ProductVariant
    {
        return DB::transaction(function () use ($variant) {
            $newStatus = !$variant->is_active;
            $variant->update(['is_active' => $newStatus]);

            // Clear cache
            $this->clearVariantCache($variant->product);

            Log::info('Product variant active status toggled', [
                'tenant_id' => tenant()->id,
                'variant_id' => $variant->id,
                'variant_sku' => $variant->sku,
                'is_active' => $newStatus,
            ]);

            return $variant->fresh(['product', 'uom']);
        });
    }

    /**
     * Update inventory details
     */
    public function updateInventoryDetails(ProductVariant $variant, array $data): ProductVariant
    {
        return DB::transaction(function () use ($variant, $data) {
            $variant->update($data);

            // Clear cache
            $this->clearVariantCache($variant->product);

            Log::info('Product variant inventory updated', [
                'tenant_id' => tenant()->id,
                'variant_id' => $variant->id,
                'variant_sku' => $variant->sku,
                'changes' => $variant->getChanges(),
            ]);

            return $variant->fresh(['product', 'uom']);
        });
    }

    /**
     * Delete variant
     */
    public function delete(ProductVariant $variant): bool
    {
        return DB::transaction(function () use ($variant) {
            $product = $variant->product;

            // TODO: Check if variant is used in any transactions
            // If used, should prevent deletion or mark as inactive

            $variantSku = $variant->sku;
            $variant->delete();

            // Clear cache
            $this->clearVariantCache($product);

            Log::warning('Product variant deleted', [
                'tenant_id' => tenant()->id,
                'product_id' => $product->id,
                'variant_sku' => $variantSku,
            ]);

            return true;
        });
    }

    /**
     * Calculate quantity in base UOM
     */
    private function calculateBaseUomQuantity(Product $product, int $uomId, float $uomQuantity): float
    {
        $productUom = $product->productUoms()
            ->where('uom_id', $uomId)
            ->first();

        if (!$productUom) {
            throw new \InvalidArgumentException('UOM is not configured for this product');
        }

        return $productUom->convertToBase($uomQuantity);
    }

    /**
     * Clear variant cache
     */
    private function clearVariantCache(Product $product): void
    {
        $tags = ['tenant', tenant()->id, 'products', 'product_variants'];

        Cache::tags($tags)->flush();
        Cache::tags(['tenant', tenant()->id, 'products'])->forget("product:{$product->uuid}");
    }
}
