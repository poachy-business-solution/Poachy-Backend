<?php

namespace App\Services\Tenant\Inventory;

use App\Enums\Tenant\InventoryMovementType;
use App\Models\Tenant\InventoryMovement;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductBatch;
use App\Models\Tenant\ProductSerial;
use App\Models\Tenant\ProductVariant;

/**
 * What one base unit of a product cost the shop — the figure profit is
 * worked out from.
 *
 * Batch- and serial-tracked stock carries its own cost. Most general-trade
 * stock is neither, so it falls back to the latest cost the stock arrived at
 * (a stock receipt, goods borrowed from a neighbour shop at an agreed price),
 * first at this branch, then at any branch. Without this, untracked products
 * sold at a cost of 0 and profit equalled sales.
 */
class ProductCostService
{
    /**
     * Movements that bring stock in at a known price.
     */
    private const INCOMING_TYPES = [
        InventoryMovementType::PURCHASE,
        InventoryMovementType::TRANSFER_IN,
        InventoryMovementType::ADJUSTMENT,
        InventoryMovementType::STOCK_TAKE,
    ];

    public function unitCost(Product $product, int $storeId, ?int $variantId = null): ?float
    {
        if ($product->requires_serial_tracking) {
            $serialCost = ProductSerial::where('store_id', $storeId)
                ->where('product_id', $product->id)
                ->where('product_variant_id', $variantId)
                ->available()
                ->avg('cost');

            if ($serialCost !== null) {
                return (float) $serialCost;
            }
        }

        $batchCost = ProductBatch::where('store_id', $storeId)
            ->where('product_id', $product->id)
            ->where('product_variant_id', $variantId)
            ->where('quantity_remaining_in_base_uom', '>', 0)
            ->where('is_expired', false)
            ->avg('cost_per_base_uom');

        if ($batchCost !== null) {
            return (float) $batchCost;
        }

        return $this->latestIncomingCost($product->id, $variantId, $storeId)
            ?? $this->latestIncomingCost($product->id, $variantId)
            ?? $this->enteredBuyingPrice($product, $variantId);
    }

    /** The buying price typed in when the product (or its colour/size) was created. */
    protected function enteredBuyingPrice(Product $product, ?int $variantId): ?float
    {
        $variantCost = $variantId === null ? null : ProductVariant::query()->whereKey($variantId)->value('cost_price');
        $cost = $variantCost ?? $product->cost_price;

        return $cost === null ? null : (float) $cost;
    }

    protected function latestIncomingCost(int $productId, ?int $variantId, ?int $storeId = null): ?float
    {
        $cost = InventoryMovement::query()
            ->where('product_id', $productId)
            ->where('product_variant_id', $variantId)
            ->whereIn('movement_type', self::INCOMING_TYPES)
            ->where('quantity_in_base_uom', '>', 0)
            ->whereNotNull('unit_cost_in_base_uom')
            ->when($storeId !== null, fn ($query) => $query->where('store_id', $storeId))
            ->orderByDesc('id')
            ->value('unit_cost_in_base_uom');

        return $cost === null ? null : (float) $cost;
    }
}
