<?php

namespace App\Services\Tenant\Product;

use App\Enums\Tenant\ProductType;
use App\Models\Tenant\Inventory;
use App\Models\Tenant\MarketplaceSaleItem;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductBatch;
use App\Models\Tenant\ProductVariant;
use App\Models\Tenant\PurchaseOrderItem;
use App\Models\Tenant\SaleItem;
use App\Models\Tenant\StockTransferItem;
use App\Models\Tenant\StoreProduct;
use App\Services\Tenant\Inventory\InventoryMovementService;
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
        if (! empty($filters['search'])) {
            $query->search($filters['search']);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        if (! empty($filters['status'])) {
            $query->where('stock_status', $filters['status']);
        }

        if (! empty($filters['attribute_key']) && ! empty($filters['attribute_value'])) {
            $query->byAttribute($filters['attribute_key'], $filters['attribute_value']);
        }

        if (isset($filters['available_online'])) {
            if ($filters['available_online']) {
                $query->whereNotNull('online_price');
            } else {
                $query->whereNull('online_price');
            }
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
        if (! empty($filters['search'])) {
            $query->search($filters['search']);
        }

        // Product filter
        if (! empty($filters['product_id'])) {
            $query->where('product_id', $filters['product_id']);
        }

        // Status filter
        if (! empty($filters['status'])) {
            $query->where('stock_status', $filters['status']);
        }

        // Active filter
        if (isset($filters['is_active'])) {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        // Attribute filters
        if (! empty($filters['attribute_key']) && ! empty($filters['attribute_value'])) {
            $query->byAttribute($filters['attribute_key'], $filters['attribute_value']);
        }

        // Available online filter
        if (isset($filters['available_online'])) {
            if ($filters['available_online']) {
                $query->whereNotNull('online_price');
            } else {
                $query->whereNull('online_price');
            }
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
            if (! $product->isVariable()) {
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
            if (! isset($data['quantity_in_base_uom']) && isset($data['uom_id']) && isset($data['uom_quantity'])) {
                $data['quantity_in_base_uom'] = $this->calculateBaseUomQuantity(
                    $product,
                    $data['uom_id'],
                    $data['uom_quantity']
                );
            }

            // Calculate variant price if not provided
            if (! isset($data['variant_price']) && isset($data['base_selling_price_adjustment'])) {
                $data['variant_price'] = $product->base_selling_price + $data['base_selling_price_adjustment'];
            }

            // Handle online_price: if not provided but product has online_price, calculate it
            if (! isset($data['online_price']) && $product->online_price !== null) {
                $adjustment = $data['base_selling_price_adjustment'] ?? 0;
                $data['online_price'] = $product->online_price + $adjustment;
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
     * Options (Colour: black, red; Size: 38, 39) → one variant per combination, in one
     * transaction. A simple product becomes variable. Combinations that already exist
     * (same option values) are left alone, so the call can be repeated safely.
     *
     * @param  array{
     *     options: list<array{name: string, values: list<string>}>,
     *     variant_price?: float|null, cost_price?: float|null, reorder_level?: float|null,
     *     store_id?: int|null, opening_stock?: float|null,
     *     variants?: list<array{attributes: array<string, string>, variant_price?: float|null, cost_price?: float|null, reorder_level?: float|null, opening_stock?: float|null}>
     * }  $data
     * @return Collection<int, ProductVariant> every variant of the product, in option order
     */
    public function createFromOptions(Product $product, array $data): Collection
    {
        return DB::transaction(function () use ($product, $data) {
            if (! $product->isVariable()) {
                $product->forceFill(['product_type' => ProductType::VARIABLE])->save();
            }

            $overrides = collect($data['variants'] ?? [])->keyBy(fn (array $row) => $this->attributesKey($row['attributes']));
            $existing = $product->variants()->get()->keyBy(fn (ProductVariant $variant) => $this->attributesKey($variant->attributes ?? ['name' => $variant->variant_name]));
            $created = new Collection;

            foreach ($this->combinations($data['options']) as $attributes) {
                $key = $this->attributesKey($attributes);
                $override = $overrides->get($key, []);

                if ($existing->has($key)) {
                    $created->push($existing->get($key));

                    continue;
                }

                $variant = $this->create($product, [
                    'variant_name' => implode(' / ', array_values($attributes)),
                    'attributes' => $attributes,
                    'uom_id' => $product->base_uom_id,
                    'uom_quantity' => 1,
                    'quantity_in_base_uom' => 1,
                    'variant_price' => $override['variant_price'] ?? $data['variant_price'] ?? null,
                    'cost_price' => $override['cost_price'] ?? $data['cost_price'] ?? $product->cost_price,
                    'reorder_level' => $override['reorder_level'] ?? $data['reorder_level'] ?? $product->reorder_level ?? 0,
                    'is_active' => true,
                ]);

                $openingStock = (float) ($override['opening_stock'] ?? $data['opening_stock'] ?? 0);
                if (! empty($data['store_id'])) {
                    StoreProduct::query()->firstOrCreate(
                        ['store_id' => $data['store_id'], 'product_id' => $product->id, 'product_variant_id' => $variant->id],
                        ['store_selling_price' => null, 'min_stock_level' => (int) $variant->reorder_level, 'is_available' => true],
                    );
                }
                if ($openingStock > 0 && ! empty($data['store_id'])) {
                    app(InventoryMovementService::class)->recordAdjustment([
                        'store_id' => $data['store_id'],
                        'product_id' => $product->id,
                        'variant_id' => $variant->id,
                        'uom_id' => $product->base_uom_id,
                        'quantity' => $openingStock,
                        'adjustment_type' => 'increase',
                        'unit_cost' => $variant->cost_price,
                        'reason' => 'Opening stock',
                        'notes' => "Opening stock for {$product->name} ({$variant->variant_name})",
                    ]);
                }

                $created->push($variant);
            }

            $this->clearVariantCache($product);

            return $created->map(fn (ProductVariant $variant) => $variant->fresh(['product', 'uom']));
        });
    }

    /**
     * Every combination of the option values, first option varying slowest.
     *
     * @param  list<array{name: string, values: list<string>}>  $options
     * @return list<array<string, string>>
     */
    protected function combinations(array $options): array
    {
        $combinations = [[]];
        foreach ($options as $option) {
            $next = [];
            foreach ($combinations as $partial) {
                foreach ($option['values'] as $value) {
                    $next[] = $partial + [trim($option['name']) => trim($value)];
                }
            }
            $combinations = $next;
        }

        return $combinations;
    }

    /**
     * @param  array<string, string>  $attributes
     */
    protected function attributesKey(array $attributes): string
    {
        $normalised = [];
        foreach ($attributes as $name => $value) {
            $normalised[mb_strtolower(trim((string) $name))] = mb_strtolower(trim((string) $value));
        }
        ksort($normalised);

        return json_encode($normalised);
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
            if ((isset($data['uom_id']) || isset($data['uom_quantity'])) && ! isset($data['quantity_in_base_uom'])) {
                $uomId = $data['uom_id'] ?? $variant->uom_id;
                $uomQuantity = $data['uom_quantity'] ?? $variant->uom_quantity;

                $data['quantity_in_base_uom'] = $this->calculateBaseUomQuantity(
                    $variant->product,
                    $uomId,
                    $uomQuantity
                );
            }

            // Recalculate variant price if adjustment changed
            if (isset($data['base_selling_price_adjustment']) && ! isset($data['variant_price'])) {
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
            $newStatus = ! $variant->is_active;
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

            // Cannot delete if this variant is referenced in existing transactions. ProductVariant
            // uses SoftDeletes, so the DB-level onDelete('restrict') FKs on these tables never
            // actually fire for this call (restrict only triggers on a real DELETE) — this guard
            // is the only thing actually protecting against it.
            $hasTransactions = SaleItem::where('product_variant_id', $variant->id)->exists()
                || PurchaseOrderItem::where('product_variant_id', $variant->id)->exists()
                || StockTransferItem::where('product_variant_id', $variant->id)->exists()
                || ProductBatch::where('product_variant_id', $variant->id)->exists()
                || Inventory::where('product_variant_id', $variant->id)->exists()
                || MarketplaceSaleItem::where('product_variant_id', $variant->id)->exists();

            if ($hasTransactions) {
                throw new \InvalidArgumentException('Cannot delete this variant: it is referenced in existing transactions.');
            }

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

        if (! $productUom) {
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
