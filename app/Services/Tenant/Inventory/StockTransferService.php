<?php

namespace App\Services\Tenant\Inventory;

use App\Enums\Tenant\InventoryMovementType;
use App\Models\Tenant\Inventory;
use App\Models\Tenant\StockTransfer;
use App\Models\Tenant\StockTransferItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StockTransferService
{
    public function __construct(
        private InventoryMovementService $movementService
    ) {}

    /**
     * Create a new stock transfer request
     *
     * @param array $data
     * @return StockTransfer
     */
    public function createTransfer(array $data): StockTransfer
    {
        return DB::transaction(function () use ($data) {
            // VALIDATE: Products must exist in source store
            foreach ($data['items'] as $itemData) {
                $inventory = Inventory::where('store_id', $data['from_store_id'])
                    ->where('product_id', $itemData['product_id'])
                    ->where('product_variant_id', $itemData['variant_id'] ?? null)
                    ->first();

                if (!$inventory) {
                    $productName = \App\Models\Tenant\Product::find($itemData['product_id'])->name;
                    $variantInfo = isset($itemData['variant_id']) ? " (Variant ID: {$itemData['variant_id']})" : "";

                    throw new \RuntimeException(
                        "Product '{$productName}'{$variantInfo} does not exist in source store. " .
                            "Cannot create transfer for products not in inventory."
                    );
                }

                // Convert requested quantity to base UOM for validation
                $quantityInBaseUom = $this->convertToBaseUom(
                    $itemData['quantity'],
                    $itemData['uom_id'],
                    $itemData['product_id']
                );

                if ($inventory->quantity_available < $quantityInBaseUom) {
                    $productName = \App\Models\Tenant\Product::find($itemData['product_id'])->name;
                    $variantInfo = isset($itemData['variant_id']) ? " (Variant ID: {$itemData['variant_id']})" : "";

                    throw new \RuntimeException(
                        "Insufficient stock for '{$productName}'{$variantInfo}'. " .
                            "Available: {$inventory->quantity_available}, Requested: {$quantityInBaseUom}"
                    );
                }
            }

            // Generate transfer number
            $transferNumber = $this->generateTransferNumber();

            // Create transfer header
            $transfer = StockTransfer::create([
                'transfer_number' => $transferNumber,
                'from_store_id' => $data['from_store_id'],
                'to_store_id' => $data['to_store_id'],
                'status' => 'pending',
                'transfer_date' => $data['transfer_date'] ?? now()->toDateString(),
                'expected_arrival_date' => $data['expected_arrival_date'] ?? null,
                'requested_by' => Auth::id(),
                'notes' => $data['notes'] ?? null,
            ]);

            // Create transfer items
            foreach ($data['items'] as $itemData) {
                $this->addTransferItem($transfer, $itemData);
            }

            Log::info('Stock transfer created', [
                'transfer_id' => $transfer->id,
                'transfer_number' => $transferNumber,
                'from_store' => $data['from_store_id'],
                'to_store' => $data['to_store_id'],
                'items_count' => count($data['items']),
                'tenant_id' => tenant()->id ?? 'system',
            ]);

            return $transfer->fresh(['fromStore', 'toStore', 'items.product', 'items.productVariant', 'items.uom']);
        });
    }

    /**
     * Add item to transfer
     *
     * @param StockTransfer $transfer
     * @param array $itemData
     * @return StockTransferItem
     */
    private function addTransferItem(StockTransfer $transfer, array $itemData): StockTransferItem
    {
        // Convert to base UOM
        $quantityInBaseUom = $this->convertToBaseUom(
            $itemData['quantity'],
            $itemData['uom_id'],
            $itemData['product_id']
        );

        return StockTransferItem::create([
            'transfer_id' => $transfer->id,
            'product_id' => $itemData['product_id'],
            'product_variant_id' => $itemData['variant_id'] ?? null,
            'uom_id' => $itemData['uom_id'],
            'quantity_requested' => $itemData['quantity'],
            'quantity_requested_in_base_uom' => $quantityInBaseUom,
            'notes' => $itemData['notes'] ?? null,
        ]);
    }

    /**
     * Approve transfer (manager approval)
     *
     * @param int $transferId
     * @return StockTransfer
     */
    public function approveTransfer(int $transferId): StockTransfer
    {
        return DB::transaction(function () use ($transferId) {
            $transfer = StockTransfer::lockForUpdate()->findOrFail($transferId);

            if ($transfer->status !== 'pending') {
                throw new \RuntimeException("Transfer must be in pending status. Current: {$transfer->status}");
            }

            // Check stock availability at source store
            foreach ($transfer->items as $item) {
                $inventory = Inventory::where('store_id', $transfer->from_store_id)
                    ->where('product_id', $item->product_id)
                    ->where('product_variant_id', $item->product_variant_id)
                    ->first();

                if (!$inventory || $inventory->quantity_available < $item->quantity_requested_in_base_uom) {
                    $productName = $item->product->name;
                    $variantInfo = $item->productVariant ? " ({$item->productVariant->variant_name})" : "";

                    throw new \RuntimeException(
                        "Insufficient stock for {$productName}{$variantInfo}. " .
                            "Available: " . ($inventory->quantity_available ?? 0) . ", " .
                            "Requested: {$item->quantity_requested_in_base_uom}"
                    );
                }
            }

            $transfer->update([
                'status' => 'approved',
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ]);

            Log::info('Stock transfer approved', [
                'transfer_id' => $transferId,
                'transfer_number' => $transfer->transfer_number,
                'approved_by' => Auth::id(),
            ]);

            return $transfer->fresh();
        });
    }

    /**
     * Send/dispatch transfer (deduct from source store)
     *
     * @param int $transferId
     * @return StockTransfer
     */
    public function sendTransfer(int $transferId): StockTransfer
    {
        return DB::transaction(function () use ($transferId) {
            $transfer = StockTransfer::with('items.product', 'items.productVariant')->lockForUpdate()->findOrFail($transferId);

            if ($transfer->status !== 'approved') {
                throw new \RuntimeException("Transfer must be approved before sending. Current: {$transfer->status}");
            }

            // Process each item - deduct from source
            foreach ($transfer->items as $item) {
                // Record movement OUT at source store
                $movement = $this->movementService->recordMovement([
                    'store_id' => $transfer->from_store_id,
                    'product_id' => $item->product_id,
                    'variant_id' => $item->product_variant_id,
                    'movement_type' => InventoryMovementType::TRANSFER_OUT,
                    'uom_id' => $item->uom_id,
                    'quantity' => -abs($item->quantity_requested), // Negative
                    'reference_type' => StockTransfer::class,
                    'reference_id' => $transfer->id,
                    'notes' => "Stock transfer to Store #{$transfer->to_store_id} - {$transfer->transfer_number}",
                ]);

                // Update item with sent quantity
                $item->update([
                    'quantity_sent' => $item->quantity_requested,
                    'quantity_sent_in_base_uom' => $item->quantity_requested_in_base_uom,
                ]);
            }

            $transfer->update([
                'status' => 'in_transit',
                'sent_by' => Auth::id(),
                'sent_at' => now(),
            ]);

            Log::info('Stock transfer sent', [
                'transfer_id' => $transferId,
                'transfer_number' => $transfer->transfer_number,
                'sent_by' => Auth::id(),
            ]);

            return $transfer->fresh();
        });
    }

    /**
     * Receive transfer at destination store
     *
     * @param int $transferId
     * @param array $receivedItems Format: ['item_id' => quantity_received]
     * @return StockTransfer
     */
    public function receiveTransfer(int $transferId, array $receivedItems): StockTransfer
    {
        return DB::transaction(function () use ($transferId, $receivedItems) {
            $transfer = StockTransfer::with('items.product', 'items.productVariant')->lockForUpdate()->findOrFail($transferId);

            if ($transfer->status !== 'in_transit') {
                throw new \RuntimeException("Transfer must be in transit. Current: {$transfer->status}");
            }

            // Process each received item
            foreach ($receivedItems as $itemId => $quantityReceived) {
                $item = $transfer->items()->findOrFail($itemId);

                // Validate received quantity
                if ($quantityReceived > $item->quantity_sent) {
                    throw new \RuntimeException(
                        "Cannot receive more than sent. Sent: {$item->quantity_sent}, Received: {$quantityReceived}"
                    );
                }

                // Convert to base UOM
                $quantityReceivedInBaseUom = $this->convertToBaseUom(
                    $quantityReceived,
                    $item->uom_id,
                    $item->product_id
                );

                // Record movement IN at destination store
                $movement = $this->movementService->recordMovement([
                    'store_id' => $transfer->to_store_id,
                    'product_id' => $item->product_id,
                    'variant_id' => $item->product_variant_id,
                    'movement_type' => InventoryMovementType::TRANSFER_IN,
                    'uom_id' => $item->uom_id,
                    'quantity' => abs($quantityReceived), // Positive
                    'reference_type' => StockTransfer::class,
                    'reference_id' => $transfer->id,
                    'notes' => "Stock transfer from Store #{$transfer->from_store_id} - {$transfer->transfer_number}",
                ]);

                // Update item with received quantity
                $item->update([
                    'quantity_received' => $quantityReceived,
                    'quantity_received_in_base_uom' => $quantityReceivedInBaseUom,
                ]);

                // Check for discrepancies
                if ($quantityReceived < $item->quantity_sent) {
                    Log::warning('Transfer quantity discrepancy', [
                        'transfer_id' => $transferId,
                        'item_id' => $itemId,
                        'sent' => $item->quantity_sent,
                        'received' => $quantityReceived,
                        'difference' => $item->quantity_sent - $quantityReceived,
                    ]);

                    $item->update([
                        'notes' => ($item->notes ?? '') . " | Discrepancy: Sent {$item->quantity_sent}, Received {$quantityReceived}",
                    ]);
                }
            }

            $transfer->update([
                'status' => 'completed',
                'received_by' => Auth::id(),
                'received_at' => now(),
                'actual_arrival_date' => now()->toDateString(),
            ]);

            Log::info('Stock transfer completed', [
                'transfer_id' => $transferId,
                'transfer_number' => $transfer->transfer_number,
                'received_by' => Auth::id(),
            ]);

            return $transfer->fresh();
        });
    }

    /**
     * Cancel transfer
     *
     * @param int $transferId
     * @param string $reason
     * @return StockTransfer
     */
    public function cancelTransfer(int $transferId, string $reason): StockTransfer
    {
        return DB::transaction(function () use ($transferId, $reason) {
            $transfer = StockTransfer::lockForUpdate()->findOrFail($transferId);

            // Can only cancel if pending or approved (not yet sent)
            if (!in_array($transfer->status, ['pending', 'approved'])) {
                throw new \RuntimeException(
                    "Cannot cancel transfer in status: {$transfer->status}. " .
                        "Only pending or approved transfers can be cancelled."
                );
            }

            $transfer->update([
                'status' => 'cancelled',
                'rejection_reason' => $reason,
            ]);

            Log::warning('Stock transfer cancelled', [
                'transfer_id' => $transferId,
                'transfer_number' => $transfer->transfer_number,
                'reason' => $reason,
                'cancelled_by' => Auth::id(),
            ]);

            return $transfer->fresh();
        });
    }

    /**
     * Get transfers for a store (as source or destination)
     *
     * @param int $storeId
     * @param string $direction 'outbound'|'inbound'|'all'
     * @param string|null $status
     * @return Collection
     */
    public function getStoreTransfers(
        int $storeId,
        string $direction = 'all',
        ?string $status = null
    ): Collection {
        $query = StockTransfer::query();

        if ($direction === 'outbound') {
            $query->where('from_store_id', $storeId);
        } elseif ($direction === 'inbound') {
            $query->where('to_store_id', $storeId);
        } else {
            $query->where(function ($q) use ($storeId) {
                $q->where('from_store_id', $storeId)
                    ->orWhere('to_store_id', $storeId);
            });
        }

        if ($status) {
            $query->where('status', $status);
        }

        return $query->with(['fromStore', 'toStore', 'items.product'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get pending approvals for manager
     *
     * @param int|null $storeId Optional - filter by source store
     * @return Collection
     */
    public function getPendingApprovals(?int $storeId = null): Collection
    {
        $query = StockTransfer::where('status', 'pending');

        if ($storeId) {
            $query->where('from_store_id', $storeId);
        }

        return $query->with(['fromStore', 'toStore', 'items.product', 'requestedBy'])
            ->orderBy('created_at', 'asc')
            ->get();
    }

    /**
     * Generate unique transfer number
     *
     * @return string
     */
    private function generateTransferNumber(): string
    {
        $prefix = 'TRF';
        $year = now()->year;
        $month = now()->format('m');

        // Get last transfer number for this month
        $lastTransfer = StockTransfer::whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->orderBy('id', 'desc')
            ->first();

        $sequence = $lastTransfer ? ((int) substr($lastTransfer->transfer_number, -4)) + 1 : 1;

        return sprintf('%s-%d%s-%04d', $prefix, $year, $month, $sequence);
    }

    /**
     * Convert quantity to base UOM
     */
    private function convertToBaseUom(float $quantity, int $uomId, int $productId): float
    {
        $product = \App\Models\Tenant\Product::findOrFail($productId);

        if ($uomId === $product->base_uom_id) {
            return $quantity;
        }

        $productUom = \App\Models\Tenant\ProductUom::where('product_id', $productId)
            ->where('uom_id', $uomId)
            ->firstOrFail();

        return $quantity * $productUom->conversion_to_base;
    }
}
