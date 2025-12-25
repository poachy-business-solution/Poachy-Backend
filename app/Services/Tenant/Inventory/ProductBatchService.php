<?php

namespace App\Services\Tenant\Inventory;

use App\Models\Tenant\ProductBatch;
use App\Models\Tenant\PurchaseOrder;
use App\Services\Tenant\Inventory\InventoryMovementService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProductBatchService
{
    /**
     * Receive goods from purchase order and create batches
     * 
     * This handles the complete receiving workflow:
     * 1. Validate PO can be received
     * 2. Create batches for received items
     * 3. Update inventory
     * 4. Update PO item quantities
     * 5. Update PO status
     *
     * @param int $purchaseOrderId
     * @param array $receivedItems Format: ['item_id' => ['quantity' => X, 'manufacture_date' => Y, 'expiry_date' => Z]]
     * @return array ['batches' => Collection, 'purchase_order' => PurchaseOrder]
     */
    public function receiveGoodsFromPurchaseOrder(int $purchaseOrderId, array $receivedItems): array
    {
        return DB::transaction(function () use ($purchaseOrderId, $receivedItems) {
            // Load PO with items
            $po = PurchaseOrder::with(['items.product', 'items.productVariant', 'items.uom', 'supplier', 'store'])
                ->lockForUpdate()
                ->findOrFail($purchaseOrderId);

            // Validate PO can be received
            if (!$po->status->canBeReceived()) {
                throw new \RuntimeException(
                    "Purchase order cannot be received. Current status: {$po->status->label()}"
                );
            }

            $createdBatches = collect();

            // Process each received item
            foreach ($receivedItems as $itemId => $receiveData) {
                $poItem = $po->items()->findOrFail($itemId);

                // Validate quantity
                $quantityReceiving = $receiveData['quantity'];
                $quantityPending = $poItem->quantity_ordered - $poItem->quantity_received;

                if ($quantityReceiving > $quantityPending) {
                    throw new \RuntimeException(
                        "Cannot receive more than ordered. Ordered: {$poItem->quantity_ordered}, " .
                            "Already received: {$poItem->quantity_received}, Pending: {$quantityPending}, " .
                            "Attempting to receive: {$quantityReceiving}"
                    );
                }

                // Create batch
                $batch = $this->createBatchFromPurchaseOrderItem(
                    purchaseOrder: $po,
                    poItem: $poItem,
                    quantityReceived: $quantityReceiving,
                    manufactureDate: $receiveData['manufacture_date'] ?? null,
                    expiryDate: $receiveData['expiry_date'] ?? null,
                    notes: $receiveData['notes'] ?? null
                );

                $createdBatches->push($batch);

                // Update inventory
                $this->updateInventoryFromBatch($batch);

                // Update PO item quantities
                $newQuantityReceived = $poItem->quantity_received + $quantityReceiving;
                $newQuantityReceivedInBaseUom = $poItem->quantity_received_in_base_uom + $batch->quantity_received_in_base_uom;

                $poItem->update([
                    'quantity_received' => $newQuantityReceived,
                    'quantity_received_in_base_uom' => $newQuantityReceivedInBaseUom,
                ]);

                // Update item status based on quantities
                $poItem->updateStatus();

                Log::info('PO item received', [
                    'po_item_id' => $poItem->id,
                    'quantity_received' => $quantityReceiving,
                    'total_received' => $newQuantityReceived,
                    'item_status' => $poItem->fresh()->status->value,
                    'batch_id' => $batch->id,
                ]);
            }

            // Update PO status based on completion
            $this->updatePurchaseOrderStatus($po);

            Log::info('Goods received from purchase order', [
                'po_id' => $po->id,
                'po_number' => $po->po_number,
                'batches_created' => $createdBatches->count(),
                'new_status' => $po->fresh()->status->value,
            ]);

            return [
                'batches' => $createdBatches,
                'purchase_order' => $po->fresh(['items', 'supplier', 'store']),
            ];
        });
    }

    /**
     * Create batch from a purchase order item
     *
     * @param PurchaseOrder $purchaseOrder
     * @param \App\Models\Tenant\PurchaseOrderItem $poItem
     * @param float $quantityReceived
     * @param string|null $manufactureDate
     * @param string|null $expiryDate
     * @param string|null $notes
     * @return ProductBatch
     */
    private function createBatchFromPurchaseOrderItem(
        $purchaseOrder,
        $poItem,
        float $quantityReceived,
        ?string $manufactureDate = null,
        ?string $expiryDate = null,
        ?string $notes = null
    ): ProductBatch {
        // Generate batch number
        $batchNumber = $this->generateBatchNumber();

        // Convert received quantity to base UOM
        $product = $poItem->product;
        $conversionFactor = $this->getConversionToBaseUom($poItem->uom_id, $product->id);
        $quantityInBaseUom = $quantityReceived * $conversionFactor;

        // Calculate costs
        $costPerPurchaseUom = $poItem->unit_cost;
        $costPerBaseUom = $poItem->unit_cost_in_base_uom;
        $totalCost = $quantityInBaseUom * $costPerBaseUom;

        // Determine if product is perishable
        $isPerishable = $expiryDate !== null;

        // Create batch
        $batch = ProductBatch::create([
            'store_id' => $purchaseOrder->store_id,
            'product_id' => $poItem->product_id,
            'product_variant_id' => $poItem->product_variant_id,
            'purchase_order_id' => $purchaseOrder->id,
            'batch_number' => $batchNumber,
            'purchase_uom_id' => $poItem->uom_id,
            'quantity_received_in_purchase_uom' => $quantityReceived,
            'quantity_received_in_base_uom' => $quantityInBaseUom,
            'quantity_remaining_in_base_uom' => $quantityInBaseUom,
            'cost_per_purchase_uom' => $costPerPurchaseUom,
            'cost_per_base_uom' => $costPerBaseUom,
            'total_cost' => $totalCost,
            'manufacture_date' => $manufactureDate,
            'expiry_date' => $expiryDate,
            'is_expired' => false,
            'supplier_id' => $purchaseOrder->supplier_id,
            'notes' => $notes,
        ]);

        Log::info('Product batch created from PO', [
            'batch_id' => $batch->id,
            'batch_number' => $batchNumber,
            'po_id' => $purchaseOrder->id,
            'po_number' => $purchaseOrder->po_number,
            'product_id' => $poItem->product_id,
            'variant_id' => $poItem->product_variant_id,
            'quantity_in_base_uom' => $quantityInBaseUom,
            'is_perishable' => $isPerishable,
            'tenant_id' => tenant()->id ?? 'system',
        ]);

        return $batch;
    }

    /**
     * Update inventory when batch is created
     *
     * @param ProductBatch $batch
     * @return void
     */
    private function updateInventoryFromBatch($batch): void
    {
        // Record inventory movement
        $movementService = app(InventoryMovementService::class);

        $movementService->recordMovement([
            'store_id' => $batch->store_id,
            'product_id' => $batch->product_id,
            'variant_id' => $batch->product_variant_id,
            'movement_type' => \App\Enums\Tenant\InventoryMovementType::PURCHASE,
            'uom_id' => $batch->purchase_uom_id,
            'quantity' => $batch->quantity_received_in_purchase_uom,
            'unit_cost' => $batch->cost_per_purchase_uom,
            'reference_type' => PurchaseOrder::class,
            'reference_id' => $batch->purchase_order_id,
            'notes' => "Goods received - Batch {$batch->batch_number}",
        ]);

        Log::info('Inventory updated from batch', [
            'batch_id' => $batch->id,
            'store_id' => $batch->store_id,
            'product_id' => $batch->product_id,
            'quantity_added' => $batch->quantity_received_in_base_uom,
        ]);
    }

    /**
     * Update purchase order status based on item statuses and received quantities
     *
     * Logic:
     * - All items RECEIVED → PO status: RECEIVED
     * - Some items RECEIVED or PARTIALLY_RECEIVED → PO status: PARTIALLY_RECEIVED
     * - All items PENDING → No change (remains SENT or CONFIRMED)
     * - Mixed statuses → PO status: PARTIALLY_RECEIVED
     *
     * @param PurchaseOrder $po
     * @return void
     */
    private function updatePurchaseOrderStatus($po): void
    {
        $po->load('items'); // Ensure items are fresh
        $items = $po->items;

        if ($items->isEmpty()) {
            return; // No items, no status update
        }

        // Count items by status
        $statusCounts = [
            'pending' => 0,
            'partially_received' => 0,
            'received' => 0,
            'cancelled' => 0,
        ];

        foreach ($items as $item) {
            $statusCounts[$item->status->value]++;
        }

        $totalItems = $items->count();
        $receivedItems = $statusCounts['received'];
        $partiallyReceivedItems = $statusCounts['partially_received'];
        $pendingItems = $statusCounts['pending'];

        $oldStatus = $po->status;
        $newStatus = $oldStatus;

        // Determine new PO status
        if ($receivedItems === $totalItems) {
            // All items fully received
            $newStatus = \App\Enums\Tenant\PurchaseOrderStatus::RECEIVED;
        } elseif ($receivedItems > 0 || $partiallyReceivedItems > 0) {
            // At least one item has been received (fully or partially)
            $newStatus = \App\Enums\Tenant\PurchaseOrderStatus::PARTIALLY_RECEIVED;
        }
        // If all items are still pending, keep current status (SENT or CONFIRMED)

        // Only update if status changed
        if ($oldStatus !== $newStatus) {
            $po->update(['status' => $newStatus]);

            Log::info('Purchase order status updated', [
                'po_id' => $po->id,
                'po_number' => $po->po_number,
                'old_status' => $oldStatus->value,
                'new_status' => $newStatus->value,
                'total_items' => $totalItems,
                'received_items' => $receivedItems,
                'partially_received_items' => $partiallyReceivedItems,
                'pending_items' => $pendingItems,
            ]);
        }
    }

    /**
     * Get conversion factor to base UOM for a product
     *
     * @param int $uomId
     * @param int $productId
     * @return float
     */
    private function getConversionToBaseUom(int $uomId, int $productId): float
    {
        $product = \App\Models\Tenant\Product::findOrFail($productId);

        if ($uomId === $product->base_uom_id) {
            return 1.0;
        }

        $productUom = \App\Models\Tenant\ProductUom::where('product_id', $productId)
            ->where('uom_id', $uomId)
            ->firstOrFail();

        return $productUom->conversion_to_base;
    }

    /**
     * Deplete batches using FIFO method for a sale
     *
     * @param int $storeId
     * @param int $productId
     * @param int|null $variantId
     * @param float $quantityInBaseUom
     * @return array Array of batch depletions ['batch_id' => quantity_depleted]
     */
    public function depleteBatchesFIFO(
        int $storeId,
        int $productId,
        ?int $variantId,
        float $quantityInBaseUom
    ): array {
        return DB::transaction(function () use ($storeId, $productId, $variantId, $quantityInBaseUom) {
            // Get available batches ordered by FIFO (oldest first, nearest expiry first)
            $batches = ProductBatch::where('store_id', $storeId)
                ->where('product_id', $productId)
                ->where('product_variant_id', $variantId)
                ->where('quantity_remaining_in_base_uom', '>', 0)
                ->where('is_expired', false)
                ->orderBy('purchase_order_id', 'asc') // FIFO: Oldest purchase first
                ->orderBy('expiry_date', 'asc') // Then nearest expiry
                ->lockForUpdate()
                ->get();

            $remainingQuantity = $quantityInBaseUom;
            $depletions = [];
            $totalCost = 0;

            foreach ($batches as $batch) {
                if ($remainingQuantity <= 0) {
                    break;
                }

                // Calculate how much to take from this batch
                $quantityToDeplete = min($batch->quantity_remaining_in_base_uom, $remainingQuantity);

                // Calculate cost of depleted quantity
                $costOfDepleted = $quantityToDeplete * $batch->cost_per_base_uom;
                $totalCost += $costOfDepleted;

                // Update batch
                $batch->decrement('quantity_remaining_in_base_uom', $quantityToDeplete);

                // Track depletion
                $depletions[$batch->id] = $quantityToDeplete;

                $remainingQuantity -= $quantityToDeplete;

                Log::debug('Batch depleted (FIFO)', [
                    'batch_id' => $batch->id,
                    'batch_number' => $batch->batch_number,
                    'depleted' => $quantityToDeplete,
                    'remaining_in_batch' => $batch->quantity_remaining_in_base_uom,
                ]);
            }

            // Check if we depleted all requested quantity
            if ($remainingQuantity > 0) {
                throw new \RuntimeException(
                    "Insufficient batch inventory. Requested: {$quantityInBaseUom}, " .
                        "Available: " . ($quantityInBaseUom - $remainingQuantity)
                );
            }

            Log::info('Batches depleted using FIFO', [
                'store_id' => $storeId,
                'product_id' => $productId,
                'variant_id' => $variantId,
                'quantity_depleted' => $quantityInBaseUom,
                'batches_affected' => count($depletions),
                'total_cogs' => $totalCost,
            ]);

            return [
                'depletions' => $depletions,
                'total_cost' => $totalCost,
                'average_cost' => $totalCost / $quantityInBaseUom,
            ];
        });
    }

    /**
     * Restore batch quantities (for returns/refunds)
     *
     * @param int $batchId
     * @param float $quantityInBaseUom
     * @return ProductBatch
     */
    public function restoreBatchQuantity(int $batchId, float $quantityInBaseUom): ProductBatch
    {
        return DB::transaction(function () use ($batchId, $quantityInBaseUom) {
            $batch = ProductBatch::lockForUpdate()->findOrFail($batchId);

            // Ensure we don't restore more than originally received
            $newRemaining = $batch->quantity_remaining_in_base_uom + $quantityInBaseUom;

            if ($newRemaining > $batch->quantity_received_in_base_uom) {
                throw new \RuntimeException(
                    "Cannot restore more than originally received. " .
                        "Received: {$batch->quantity_received_in_base_uom}, " .
                        "Would restore to: {$newRemaining}"
                );
            }

            $batch->increment('quantity_remaining_in_base_uom', $quantityInBaseUom);

            Log::info('Batch quantity restored', [
                'batch_id' => $batchId,
                'quantity_restored' => $quantityInBaseUom,
                'new_remaining' => $batch->quantity_remaining_in_base_uom,
            ]);

            return $batch->fresh();
        });
    }

    /**
     * Get batches for a product/variant
     *
     * @param int $storeId
     * @param int $productId
     * @param int|null $variantId
     * @param bool $onlyAvailable
     * @return Collection
     */
    public function getBatchesForProduct(
        int $storeId,
        int $productId,
        ?int $variantId = null,
        bool $onlyAvailable = false
    ): Collection {
        $query = ProductBatch::where('store_id', $storeId)
            ->where('product_id', $productId)
            ->where('product_variant_id', $variantId);

        if ($onlyAvailable) {
            $query->where('quantity_remaining_in_base_uom', '>', 0)
                ->where('is_expired', false);
        }

        return $query->with(['product', 'productVariant', 'supplier', 'purchaseOrder'])
            ->orderBy('purchase_order_id', 'asc')
            ->orderBy('expiry_date', 'asc')
            ->get();
    }

    /**
     * Check and mark expired batches
     *
     * @param int|null $storeId Optional - check specific store
     * @return int Count of batches marked as expired
     */
    public function markExpiredBatches(?int $storeId = null): int
    {
        $query = ProductBatch::where('is_expired', false)
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<', now()->toDateString());

        if ($storeId) {
            $query->where('store_id', $storeId);
        }

        $expiredBatches = $query->get();

        foreach ($expiredBatches as $batch) {
            $batch->update(['is_expired' => true]);

            Log::warning('Batch marked as expired', [
                'batch_id' => $batch->id,
                'batch_number' => $batch->batch_number,
                'product_id' => $batch->product_id,
                'expiry_date' => $batch->expiry_date,
                'remaining_quantity' => $batch->quantity_remaining_in_base_uom,
            ]);
        }

        return $expiredBatches->count();
    }

    /**
     * Get batches expiring soon (within X days)
     *
     * @param int $storeId
     * @param int $daysThreshold Default 30 days
     * @return Collection
     */
    public function getExpiringSoonBatches(int $storeId, int $daysThreshold = 30): Collection
    {
        $thresholdDate = now()->addDays($daysThreshold)->toDateString();

        return ProductBatch::where('store_id', $storeId)
            ->where('is_expired', false)
            ->where('quantity_remaining_in_base_uom', '>', 0)
            ->whereNotNull('expiry_date')
            ->whereBetween('expiry_date', [now()->toDateString(), $thresholdDate])
            ->with(['product', 'productVariant', 'supplier'])
            ->orderBy('expiry_date', 'asc')
            ->get();
    }

    /**
     * Calculate COGS (Cost of Goods Sold) for a product using FIFO
     *
     * @param int $storeId
     * @param int $productId
     * @param int|null $variantId
     * @param float $quantityInBaseUom
     * @return float COGS amount
     */
    public function calculateCOGS(
        int $storeId,
        int $productId,
        ?int $variantId,
        float $quantityInBaseUom
    ): float {
        // This is a dry-run version - doesn't actually deplete batches
        $batches = ProductBatch::where('store_id', $storeId)
            ->where('product_id', $productId)
            ->where('product_variant_id', $variantId)
            ->where('quantity_remaining_in_base_uom', '>', 0)
            ->where('is_expired', false)
            ->orderBy('purchase_order_id', 'asc')
            ->orderBy('expiry_date', 'asc')
            ->get();

        $remainingQuantity = $quantityInBaseUom;
        $totalCost = 0;

        foreach ($batches as $batch) {
            if ($remainingQuantity <= 0) {
                break;
            }

            $quantityToDeplete = min($batch->quantity_remaining_in_base_uom, $remainingQuantity);
            $totalCost += $quantityToDeplete * $batch->cost_per_base_uom;
            $remainingQuantity -= $quantityToDeplete;
        }

        if ($remainingQuantity > 0) {
            throw new \RuntimeException("Insufficient batch quantity for COGS calculation");
        }

        return $totalCost;
    }

    /**
     * Get inventory valuation using FIFO
     *
     * @param int $storeId
     * @param int|null $productId
     * @return array
     */
    public function getInventoryValuation(int $storeId, ?int $productId = null): array
    {
        $query = ProductBatch::where('store_id', $storeId)
            ->where('quantity_remaining_in_base_uom', '>', 0)
            ->where('is_expired', false);

        if ($productId) {
            $query->where('product_id', $productId);
        }

        $batches = $query->get();

        $totalQuantity = $batches->sum('quantity_remaining_in_base_uom');
        $totalValue = $batches->sum(function ($batch) {
            return $batch->quantity_remaining_in_base_uom * $batch->cost_per_base_uom;
        });

        return [
            'total_quantity' => $totalQuantity,
            'total_value' => $totalValue,
            'average_cost' => $totalQuantity > 0 ? $totalValue / $totalQuantity : 0,
            'batch_count' => $batches->count(),
        ];
    }

    /**
     * Generate unique batch number
     *
     * @return string
     */
    private function generateBatchNumber(): string
    {
        $prefix = 'BATCH';
        $year = now()->year;
        $month = now()->format('m');

        $lastBatch = ProductBatch::whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->orderBy('id', 'desc')
            ->first();

        $sequence = $lastBatch ? ((int) substr($lastBatch->batch_number, -4)) + 1 : 1;

        return sprintf('%s-%d%s-%04d', $prefix, $year, $month, $sequence);
    }
}
