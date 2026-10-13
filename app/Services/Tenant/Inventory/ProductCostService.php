<?php

namespace App\Services\Tenant\Inventory;

use App\Enums\Tenant\InventoryMovementType;
use App\Models\Tenant\Inventory;
use App\Models\Tenant\InventoryMovement;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductBatch;
use App\Models\Tenant\ProductSerial;
use App\Models\Tenant\ProductVariant;
use Illuminate\Database\Eloquent\Builder;

/**
 * What one base unit of a product cost the shop — the figure profit is
 * worked out from.
 *
 * Batch- and serial-tracked stock carries its own cost. Most general-trade
 * stock is neither, so it uses the branch's moving weighted average cost
 * (kept on the inventory row by every costed receipt — see
 * InventoryMovementService). Stock from before averages were kept falls back
 * to the latest cost it arrived at, first at this branch then at any branch,
 * and finally to the buying price typed in when the product was created.
 * Without this, untracked products sold at a cost of 0 and profit equalled sales.
 */
class ProductCostService
{
    /**
     * Movements that bring stock in at a known price (a price of 0 counts as "not given").
     */
    public const INCOMING_TYPES = [
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

        $averageCost = Inventory::query()
            ->where('store_id', $storeId)
            ->where('product_id', $product->id)
            ->where('product_variant_id', $variantId)
            ->value('average_cost');

        if ($averageCost !== null) {
            return (float) $averageCost;
        }

        return $this->latestIncomingCost($product->id, $variantId, $storeId)
            ?? $this->latestIncomingCost($product->id, $variantId)
            ?? $this->enteredBuyingPrice($product, $variantId);
    }

    /**
     * The cost to blend a first averaged receipt with, for stock that predates averaging:
     * the latest costed receipt at this branch, else anywhere, else the entered buying price.
     */
    public function costBeforeAveraging(Inventory $inventory): ?float
    {
        $product = $inventory->product ?? Product::query()->find($inventory->product_id);

        return $this->latestIncomingCost($inventory->product_id, $inventory->product_variant_id, $inventory->store_id)
            ?? $this->latestIncomingCost($inventory->product_id, $inventory->product_variant_id)
            ?? ($product ? $this->enteredBuyingPrice($product, $inventory->product_variant_id) : null);
    }

    /**
     * What the owner sees on the product: the average cost of everything on hand across
     * branches, the buying price they typed in, and the last few buying prices.
     *
     * @return array{
     *     average_cost: float|null,
     *     entered_cost: float|null,
     *     recent: list<array{date: string, quantity: float, unit_cost: float, store_id: int, store_name: string|null}>
     * }
     */
    public function costSummary(Product $product, ?int $variantId = null, int $recentLimit = 5): array
    {
        $rows = Inventory::query()
            ->where('product_id', $product->id)
            ->where('product_variant_id', $variantId)
            ->where('quantity_on_hand', '>', 0)
            ->whereNotNull('average_cost')
            ->get(['quantity_on_hand', 'average_cost']);

        $onHand = (float) $rows->sum('quantity_on_hand');
        $average = $onHand > 0
            ? $rows->sum(fn (Inventory $row) => (float) $row->quantity_on_hand * (float) $row->average_cost) / $onHand
            : ($this->latestIncomingCost($product->id, $variantId) ?? $this->enteredBuyingPrice($product, $variantId));

        $recent = $this->incomingCostQuery($product->id, $variantId)
            ->with('store:id,name')
            ->orderByDesc('id')
            ->limit($recentLimit)
            ->get()
            ->map(fn (InventoryMovement $movement) => [
                'date' => $movement->created_at->toDateString(),
                'quantity' => round((float) $movement->quantity_in_base_uom, 4),
                'unit_cost' => round((float) $movement->unit_cost_in_base_uom, 2),
                'store_id' => (int) $movement->store_id,
                'store_name' => $movement->store?->name,
            ]);

        return [
            'average_cost' => $average === null ? null : round((float) $average, 2),
            'entered_cost' => $this->enteredBuyingPrice($product, $variantId),
            'recent' => $recent->values()->all(),
        ];
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
        $cost = $this->incomingCostQuery($productId, $variantId)
            ->when($storeId !== null, fn ($query) => $query->where('store_id', $storeId))
            ->orderByDesc('id')
            ->value('unit_cost_in_base_uom');

        return $cost === null ? null : (float) $cost;
    }

    /**
     * @return Builder<InventoryMovement>
     */
    protected function incomingCostQuery(int $productId, ?int $variantId): Builder
    {
        return InventoryMovement::query()
            ->where('product_id', $productId)
            ->where('product_variant_id', $variantId)
            ->whereIn('movement_type', self::INCOMING_TYPES)
            ->where('quantity_in_base_uom', '>', 0)
            ->where('unit_cost_in_base_uom', '>', 0);
    }
}
