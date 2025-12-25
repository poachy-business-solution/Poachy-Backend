<?php

namespace App\Services\Tenant\Inventory;

use App\Enums\Tenant\PaymentStatus;
use App\Enums\Tenant\PurchaseOrderStatus;
use App\Models\Tenant\PurchaseOrder;
use App\Models\Tenant\PurchaseOrderItem;
use App\Models\Tenant\StoreProduct;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PurchaseOrderService
{
    /**
     * Create new purchase order
     *
     * @param array $data
     * @return PurchaseOrder
     */
    public function createPurchaseOrder(array $data): PurchaseOrder
    {
        return DB::transaction(function () use ($data) {
            // Validate: Items must be store products
            foreach ($data['items'] as $itemData) {
                $storeProduct = StoreProduct::where('store_id', $data['store_id'])
                    ->where('product_id', $itemData['product_id'])
                    ->first();

                if (!$storeProduct) {
                    $productName = \App\Models\Tenant\Product::find($itemData['product_id'])->name;
                    throw new \RuntimeException(
                        "Product '{$productName}' is not allocated to this store. " .
                            "Only store products can be ordered."
                    );
                }
            }

            // Generate PO number
            $poNumber = $this->generatePoNumber();

            // Calculate totals
            $subtotal = 0;
            $taxAmount = 0;

            foreach ($data['items'] as $itemData) {
                $itemSubtotal = $itemData['quantity_ordered'] * $itemData['unit_cost'];

                // Get tax rate from tax_rate_id if provided
                $taxRate = 0;
                if (isset($itemData['tax_rate_id'])) {
                    $taxRateModel = \App\Models\Tenant\TaxRate::find($itemData['tax_rate_id']);
                    $taxRate = $taxRateModel ? $taxRateModel->rate : 0;
                } elseif (isset($itemData['tax_rate'])) {
                    $taxRate = $itemData['tax_rate'];
                }

                $itemTax = $itemSubtotal * $taxRate / 100;

                $subtotal += $itemSubtotal;
                $taxAmount += $itemTax;
            }

            $totalAmount = $subtotal + $taxAmount + ($data['shipping_cost'] ?? 0);

            // Create purchase order
            $po = PurchaseOrder::create([
                'po_number' => $poNumber,
                'supplier_id' => $data['supplier_id'],
                'store_id' => $data['store_id'],
                'order_date' => $data['order_date'] ?? now()->toDateString(),
                'expected_delivery_date' => $data['expected_delivery_date'] ?? null,
                'status' => PurchaseOrderStatus::DRAFT,
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'shipping_cost' => $data['shipping_cost'] ?? 0,
                'total_amount' => $totalAmount,
                'payment_status' => PaymentStatus::UNPAID,
                'amount_paid' => 0,
                'notes' => $data['notes'] ?? null,
                'created_by' => Auth::id(),
            ]);

            // Create PO items
            foreach ($data['items'] as $itemData) {
                $this->addPurchaseOrderItem($po, $itemData);
            }

            Log::info('Purchase order created', [
                'po_id' => $po->id,
                'po_number' => $poNumber,
                'supplier_id' => $data['supplier_id'],
                'store_id' => $data['store_id'],
                'total_amount' => $totalAmount,
                'tenant_id' => tenant()->id ?? 'system',
            ]);

            return $po->fresh(['supplier', 'store', 'items.product', 'items.productVariant', 'items.uom']);
        });
    }

    /**
     * Add item to purchase order
     *
     * @param PurchaseOrder $po
     * @param array $itemData
     * @return PurchaseOrderItem
     */
    private function addPurchaseOrderItem(PurchaseOrder $po, array $itemData): PurchaseOrderItem
    {
        // Convert to base UOM
        $quantityInBaseUom = $this->convertToBaseUom(
            $itemData['quantity_ordered'],
            $itemData['uom_id'],
            $itemData['product_id']
        );

        // Calculate cost per base UOM
        $unitCostInBaseUom = $itemData['unit_cost'] / ($quantityInBaseUom / $itemData['quantity_ordered']);

        // Calculate line totals
        $subtotal = $itemData['quantity_ordered'] * $itemData['unit_cost'];

        // Get tax rate from tax_rate_id if provided
        $taxRate = 0;
        if (isset($itemData['tax_rate_id'])) {
            $taxRateModel = \App\Models\Tenant\TaxRate::find($itemData['tax_rate_id']);
            $taxRate = $taxRateModel ? $taxRateModel->rate : 0;
        } elseif (isset($itemData['tax_rate'])) {
            $taxRate = $itemData['tax_rate'];
        }

        $taxAmount = $subtotal * $taxRate / 100;

        return PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_id' => $itemData['product_id'],
            'product_variant_id' => $itemData['variant_id'] ?? null,
            'uom_id' => $itemData['uom_id'],
            'quantity_ordered' => $itemData['quantity_ordered'],
            'quantity_ordered_in_base_uom' => $quantityInBaseUom,
            'quantity_received' => 0,
            'quantity_received_in_base_uom' => 0,
            'unit_cost' => $itemData['unit_cost'],
            'unit_cost_in_base_uom' => $unitCostInBaseUom,
            'tax_rate_id' => $itemData['tax_rate_id'] ?? null,
            'tax_amount' => $taxAmount,
            'subtotal' => $subtotal,
            'status' => \App\Enums\Tenant\PurchaseOrderItemStatus::PENDING,
            'notes' => $itemData['notes'] ?? null,
        ]);
    }

    /**
     * Update purchase order (draft only)
     *
     * @param int $poId
     * @param array $data
     * @return PurchaseOrder
     */
    public function updatePurchaseOrder(int $poId, array $data): PurchaseOrder
    {
        return DB::transaction(function () use ($poId, $data) {
            $po = PurchaseOrder::lockForUpdate()->findOrFail($poId);

            if (!$po->status->canBeEdited()) {
                throw new \RuntimeException("Only draft purchase orders can be edited. Current status: {$po->status->label()}");
            }

            // Update header
            $po->update([
                'supplier_id' => $data['supplier_id'] ?? $po->supplier_id,
                'order_date' => $data['order_date'] ?? $po->order_date,
                'expected_delivery_date' => $data['expected_delivery_date'] ?? $po->expected_delivery_date,
                'shipping_cost' => $data['shipping_cost'] ?? $po->shipping_cost,
                'notes' => $data['notes'] ?? $po->notes,
            ]);

            // If items are provided, replace them
            if (isset($data['items'])) {
                // Delete old items
                $po->items()->delete();

                // Add new items
                foreach ($data['items'] as $itemData) {
                    $this->addPurchaseOrderItem($po, $itemData);
                }

                // Recalculate totals
                $this->recalculateTotals($po);
            }

            Log::info('Purchase order updated', [
                'po_id' => $poId,
                'po_number' => $po->po_number,
            ]);

            return $po->fresh();
        });
    }

    /**
     * Send purchase order to supplier
     *
     * @param int $poId
     * @return PurchaseOrder
     */
    public function sendPurchaseOrder(int $poId): PurchaseOrder
    {
        return DB::transaction(function () use ($poId) {
            $po = PurchaseOrder::lockForUpdate()->findOrFail($poId);

            if (!$po->status->canBeSent()) {
                throw new \RuntimeException("PO cannot be sent. Current status: {$po->status->label()}");
            }

            $po->update([
                'status' => PurchaseOrderStatus::SENT,
            ]);

            Log::info('Purchase order sent', [
                'po_id' => $poId,
                'po_number' => $po->po_number,
            ]);

            return $po->fresh();
        });
    }

    /**
     * Cancel purchase order
     *
     * @param int $poId
     * @return PurchaseOrder
     */
    public function cancelPurchaseOrder(int $poId): PurchaseOrder
    {
        return DB::transaction(function () use ($poId) {
            $po = PurchaseOrder::lockForUpdate()->findOrFail($poId);

            if (!$po->status->canBeCancelled()) {
                throw new \RuntimeException("PO cannot be cancelled. Current status: {$po->status->label()}");
            }

            $po->update([
                'status' => PurchaseOrderStatus::CANCELLED,
            ]);

            Log::warning('Purchase order cancelled', [
                'po_id' => $poId,
                'po_number' => $po->po_number,
            ]);

            return $po->fresh();
        });
    }

    /**
     * Get purchase orders for store
     *
     * @param int $storeId
     * @param PurchaseOrderStatus|null $status
     * @param PaymentStatus|null $paymentStatus
     * @return Collection
     */
    public function getStorePurchaseOrders(
        int $storeId,
        ?PurchaseOrderStatus $status = null,
        ?PaymentStatus $paymentStatus = null
    ): Collection {
        $query = PurchaseOrder::where('store_id', $storeId);

        if ($status) {
            $query->where('status', $status);
        }

        if ($paymentStatus) {
            $query->where('payment_status', $paymentStatus);
        }

        return $query->with(['supplier', 'store', 'items.product'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Recalculate PO totals
     *
     * @param PurchaseOrder $po
     * @return void
     */
    private function recalculateTotals(PurchaseOrder $po): void
    {
        $items = $po->items;

        $subtotal = $items->sum('subtotal');
        $taxAmount = $items->sum('tax_amount');
        $totalAmount = $subtotal + $taxAmount + $po->shipping_cost;

        $po->update([
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total_amount' => $totalAmount,
        ]);
    }

    /**
     * Generate unique PO number
     *
     * @return string
     */
    private function generatePoNumber(): string
    {
        $prefix = 'PO';
        $year = now()->year;

        $lastPo = PurchaseOrder::whereYear('created_at', $year)
            ->orderBy('id', 'desc')
            ->first();

        $sequence = $lastPo ? ((int) substr($lastPo->po_number, -4)) + 1 : 1;

        return sprintf('%s-%d-%04d', $prefix, $year, $sequence);
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
