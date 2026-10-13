<?php

namespace App\Services\Tenant\Product;

use App\Enums\Tenant\SupplierType;
use App\Models\Tenant\Product;
use App\Models\Tenant\PurchaseOrder;
use App\Models\Tenant\StoreProduct;
use App\Models\Tenant\Supplier;
use App\Services\Tenant\Inventory\ProductBatchService;
use App\Services\Tenant\Inventory\ProductCostService;
use App\Services\Tenant\Inventory\PurchaseOrderService;
use App\Services\Tenant\Supplier\SupplierPaymentService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ProductStockReceivingService
{
    public const MARKET_SUPPLIER_NAME = 'Market (no supplier named)';

    public function __construct(
        private readonly PurchaseOrderService $purchaseOrderService,
        private readonly ProductBatchService $batchService,
        private readonly ProductCostService $costs,
        private readonly SupplierPaymentService $supplierPayments,
    ) {}

    /**
     * Receive first/restock inventory for a product in one mobile-friendly call.
     *
     * A delivery with no price is costed at what the stock currently costs (average, last
     * receipt or entered buying price) so it never drags the average down to zero.
     *
     * Most small-shop deliveries are paid on the spot, so the purchase order is settled at
     * once (cash by default, or M-Pesa) and nothing shows as owed to the supplier. "credit"
     * leaves it open — only for a named supplier; the market can't be owed.
     *
     * @return array{
     *     store_product_created: bool,
     *     batches: Collection,
     *     serials: Collection,
     *     purchase_order: PurchaseOrder,
     *     payment: array{status: string, method: string|null, amount: float}
     * }
     */
    public function receive(Product $product, array $data): array
    {
        return DB::transaction(function () use ($product, $data) {
            $supplierId = $data['supplier_id'] ?? $product->supplier_id;
            $variantId = isset($data['variant_id']) ? (int) $data['variant_id'] : null;

            if ($variantId !== null && ! $product->variants()->whereKey($variantId)->exists()) {
                throw new RuntimeException('That colour/size does not belong to this product.');
            }

            if ($supplierId && ! Supplier::whereKey($supplierId)->exists()) {
                throw new RuntimeException('That supplier does not exist.');
            }

            // Small shops often buy at the market with no named supplier.
            $marketPurchase = $supplierId === null;
            $supplierId ??= $this->marketSupplier()->id;
            $payment = $data['payment'] ?? 'cash';

            if ($payment === 'credit' && $marketPurchase) {
                throw new RuntimeException('A market purchase has no supplier to owe — choose cash or M-Pesa, or name the supplier.');
            }

            if ($product->requiresSerialTracking() && count($data['serial_numbers'] ?? []) !== (int) $data['quantity']) {
                throw new RuntimeException('Serial number count must match quantity for serial-tracked products.');
            }

            $storeProduct = StoreProduct::firstOrCreate(
                [
                    'store_id' => $data['store_id'],
                    'product_id' => $product->id,
                    'product_variant_id' => $variantId,
                ],
                [
                    'store_selling_price' => null,
                    'min_stock_level' => (int) $product->reorder_level,
                    'is_available' => true,
                ]
            );

            $purchaseOrder = $this->purchaseOrderService->createPurchaseOrder([
                'supplier_id' => $supplierId,
                'store_id' => $data['store_id'],
                'order_date' => now()->toDateString(),
                'notes' => $data['notes'] ?? "One-shot stock receipt for {$product->name}",
                'items' => [[
                    'product_id' => $product->id,
                    'variant_id' => $variantId,
                    'uom_id' => $product->base_uom_id,
                    'quantity_ordered' => $data['quantity'],
                    'unit_cost' => $data['unit_cost'] ?? $this->costs->unitCost($product, (int) $data['store_id'], $variantId) ?? 0,
                    'tax_rate_id' => $product->tax_rate_id,
                    'notes' => $data['notes'] ?? null,
                ]],
            ]);

            $this->purchaseOrderService->sendPurchaseOrder($purchaseOrder->id);

            $purchaseOrder = $purchaseOrder->fresh('items');
            $poItem = $purchaseOrder->items->first();

            $receipt = $this->batchService->receiveGoodsFromPurchaseOrder($purchaseOrder->id, [
                $poItem->id => [
                    'quantity' => $data['quantity'],
                    'manufacture_date' => $data['manufacture_date'] ?? null,
                    'expiry_date' => $data['expiry_date'] ?? null,
                    'serial_numbers' => $data['serial_numbers'] ?? [],
                    'notes' => $data['notes'] ?? null,
                ],
            ]);

            $paid = $this->settle($receipt['purchase_order'], $payment);

            return [
                'store_product_created' => $storeProduct->wasRecentlyCreated,
                'batches' => $receipt['batches'],
                'serials' => $receipt['serials'],
                'purchase_order' => $receipt['purchase_order']->fresh(),
                'payment' => $paid,
            ];
        });
    }

    /**
     * Pay the purchase order on the spot (unless on credit), so the supplier's balance
     * and "you owe" stay at what is really owed.
     *
     * @return array{status: string, method: string|null, amount: float}
     */
    protected function settle(PurchaseOrder $purchaseOrder, string $payment): array
    {
        $total = (float) $purchaseOrder->total_amount;

        if ($payment === 'credit') {
            return ['status' => 'credit', 'method' => null, 'amount' => 0.0];
        }

        if ($total <= 0) {
            return ['status' => 'paid', 'method' => $payment, 'amount' => 0.0];
        }

        $this->supplierPayments->recordPayment([
            'supplier_id' => $purchaseOrder->supplier_id,
            'purchase_order_id' => $purchaseOrder->id,
            'payment_date' => now()->toDateString(),
            'amount' => $total,
            'payment_method' => $payment,
            'notes' => 'Paid on delivery',
        ]);

        return ['status' => 'paid', 'method' => $payment, 'amount' => round($total, 2)];
    }

    /**
     * Stands in for "bought at the market" when stock arrives with no named
     * supplier, so the purchase record (and the stock's cost) still exists.
     */
    protected function marketSupplier(): Supplier
    {
        return Supplier::query()->firstOrCreate(
            ['name' => self::MARKET_SUPPLIER_NAME],
            ['supplier_type' => SupplierType::WHOLESALER->value, 'is_active' => true, 'notes' => 'Stock received without a named supplier.'],
        );
    }
}
