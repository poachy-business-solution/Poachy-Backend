<?php

namespace App\Services\Tenant\Product;

use App\Models\Tenant\Product;
use App\Models\Tenant\ProductUom;
use App\Models\Tenant\UnitOfMeasure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProductUomService
{
    /**
     * Get all UOMs for a product
     */
    public function list(Product $product): Collection
    {
        return $product->productUoms()
            ->with(['uom'])
            ->orderBy('is_base_uom', 'desc')
            ->orderBy('created_at', 'asc')
            ->get();
    }

    /**
     * Create a new product UOM
     */
    public function create(Product $product, array $data): ProductUom
    {
        return DB::transaction(function () use ($product, $data) {
            // Check if UOM is already assigned to this product
            $existing = ProductUom::where('product_id', $product->id)
                ->where('uom_id', $data['uom_id'])
                ->first();

            if ($existing) {
                throw new \InvalidArgumentException('This UOM is already assigned to the product');
            }

            // Verify UOM exists and is active
            $uom = UnitOfMeasure::where('id', $data['uom_id'])
                ->where('is_active', true)
                ->firstOrFail();

            // If this should be the base UOM
            if ($data['is_base_uom'] ?? false) {
                // Remove base flag from any existing base UOM
                ProductUom::where('product_id', $product->id)
                    ->where('is_base_uom', true)
                    ->update(['is_base_uom' => false]);

                // Base UOM must have conversion factor of 1
                $data['conversion_to_base'] = 1;

                // Update product's base_uom_id
                $product->update(['base_uom_id' => $uom->id]);
            }

            // Validate conversion_to_base
            if (isset($data['conversion_to_base'])) {
                if ($data['conversion_to_base'] <= 0) {
                    throw new \InvalidArgumentException('Conversion factor must be greater than 0');
                }
            }

            // Create the product UOM
            $data['product_id'] = $product->id;
            $productUom = ProductUom::create($data);

            // Clear cache
            $this->clearProductUomCache($product->uuid);

            Log::info('Product UOM created', [
                'tenant_id' => tenant()->id,
                'product_id' => $product->id,
                'product_uuid' => $product->uuid,
                'uom_id' => $uom->id,
                'uom_code' => $uom->code,
                'is_base_uom' => $productUom->is_base_uom,
            ]);

            return $productUom->load('uom');
        });
    }

    /**
     * Update a product UOM
     */
    public function update(ProductUom $productUom, array $data): ProductUom
    {
        return DB::transaction(function () use ($productUom, $data) {
            $product = $productUom->product;

            // If changing to base UOM
            if (isset($data['is_base_uom']) && $data['is_base_uom']) {
                // Remove base flag from other UOMs
                ProductUom::where('product_id', $productUom->product_id)
                    ->where('id', '!=', $productUom->id)
                    ->where('is_base_uom', true)
                    ->update(['is_base_uom' => false]);

                // Base UOM must have conversion factor of 1
                $data['conversion_to_base'] = 1;

                // Update product's base_uom_id
                $product->update(['base_uom_id' => $productUom->uom_id]);
            }

            // If removing base UOM flag
            if (isset($data['is_base_uom']) && !$data['is_base_uom'] && $productUom->is_base_uom) {
                // Cannot remove base UOM if it's the only one
                if (ProductUom::where('product_id', $productUom->product_id)->count() === 1) {
                    throw new \InvalidArgumentException('Cannot remove base UOM flag from the only UOM');
                }
            }

            // Validate conversion_to_base
            if (isset($data['conversion_to_base'])) {
                if ($data['conversion_to_base'] <= 0) {
                    throw new \InvalidArgumentException('Conversion factor must be greater than 0');
                }

                // Cannot change base UOM conversion factor to non-1
                if ($productUom->is_base_uom && $data['conversion_to_base'] != 1) {
                    throw new \InvalidArgumentException('Base UOM must have conversion factor of 1');
                }
            }

            // Prevent changing UOM (only configuration can be updated)
            unset($data['uom_id']);
            unset($data['product_id']);

            $productUom->update($data);

            // Clear cache
            $this->clearProductUomCache($product->uuid);

            Log::info('Product UOM updated', [
                'tenant_id' => tenant()->id,
                'product_id' => $product->id,
                'product_uuid' => $product->uuid,
                'product_uom_id' => $productUom->id,
                'changes' => $productUom->getChanges(),
            ]);

            return $productUom->fresh('uom');
        });
    }

    /**
     * Delete a product UOM
     */
    public function delete(ProductUom $productUom): bool
    {
        return DB::transaction(function () use ($productUom) {
            $product = $productUom->product;

            // Cannot delete base UOM
            if ($productUom->is_base_uom) {
                throw new \InvalidArgumentException('Cannot delete the base UOM. Assign a different base UOM first.');
            }

            // Cannot delete if it's the only UOM
            if (ProductUom::where('product_id', $productUom->product_id)->count() === 1) {
                throw new \InvalidArgumentException('Cannot delete the only UOM for this product');
            }

            // TODO: Check if UOM is used in any transactions
            // If used, should prevent deletion or mark as inactive

            $uomCode = $productUom->uom->code;
            $productUom->delete();

            // Clear cache
            $this->clearProductUomCache($product->uuid);

            Log::info('Product UOM deleted', [
                'tenant_id' => tenant()->id,
                'product_id' => $product->id,
                'product_uuid' => $product->uuid,
                'uom_code' => $uomCode,
            ]);

            return true;
        });
    }

    /**
     * Get base product UOM
     */
    public function getBaseUom(Product $product): ?ProductUom
    {
        return ProductUom::where('product_id', $product->id)
            ->where('is_base_uom', true)
            ->with('uom')
            ->first();
    }

    /**
     * Get purchase UOMs for a product
     */
    public function getPurchaseUoms(Product $product): Collection
    {
        return ProductUom::where('product_id', $product->id)
            ->where('is_purchase_uom', true)
            ->with('uom')
            ->get();
    }

    /**
     * Get sales UOMs for a product
     */
    public function getSalesUoms(Product $product): Collection
    {
        return ProductUom::where('product_id', $product->id)
            ->where('is_sales_uom', true)
            ->with('uom')
            ->get();
    }

    /**
     * Convert quantity between UOMs
     */
    public function convertQuantity(
        Product $product,
        float $quantity,
        int $fromUomId,
        int $toUomId
    ): float {
        // Same UOM, no conversion needed
        if ($fromUomId === $toUomId) {
            return $quantity;
        }

        $fromProductUom = ProductUom::where('product_id', $product->id)
            ->where('uom_id', $fromUomId)
            ->firstOrFail();

        $toProductUom = ProductUom::where('product_id', $product->id)
            ->where('uom_id', $toUomId)
            ->firstOrFail();

        // Convert to base units first
        $baseQuantity = $fromProductUom->convertToBase($quantity);

        // Then convert from base to target
        return $toProductUom->convertFromBase($baseQuantity);
    }

    /**
     * Clear product UOM cache
     */
    private function clearProductUomCache(string $productUuid): void
    {
        $tags = ['tenant', tenant()->id, 'products', 'product_uoms'];
        Cache::tags($tags)->forget("product:{$productUuid}:uoms");
        Cache::tags(['tenant', tenant()->id, 'products'])->forget("product:{$productUuid}");
    }
}
