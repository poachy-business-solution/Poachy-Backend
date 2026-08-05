<?php

namespace Database\Seeders\Demo;

use App\Enums\Tenant\PaymentMethod;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductVariant;
use App\Models\Tenant\Store;
use App\Models\Tenant\Supplier;
use App\Services\Tenant\Inventory\ProductBatchService;
use App\Services\Tenant\Inventory\PurchaseOrderService;
use App\Services\Tenant\Supplier\SupplierPaymentService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DemoStockSeeder extends Seeder
{
    protected int $poCount = 0;

    public function run(
        PurchaseOrderService $purchaseOrderService,
        ProductBatchService $productBatchService,
        SupplierPaymentService $supplierPaymentService,
    ): void {
        $cbd = Store::mainStore()->firstOrFail();
        $westlands = Store::branches()->firstOrFail();

        // Main store: fully stocked — one PO per supplier, covering all its products.
        foreach (Supplier::all() as $supplier) {
            $this->orderAndReceive($purchaseOrderService, $productBatchService, $supplierPaymentService, $cbd, $supplier);
        }

        // Branch: smaller assortment — groceries + home goods only.
        $branchSuppliers = Supplier::whereIn('name', ['Twiga Foods Supplies', 'Nairobi Wholesale Distributors'])->get();
        foreach ($branchSuppliers as $supplier) {
            $this->orderAndReceive($purchaseOrderService, $productBatchService, $supplierPaymentService, $westlands, $supplier);
        }

        $this->command->info("✓ Opening stock: {$this->poCount} purchase orders received across 2 stores");
    }

    protected function orderAndReceive(
        PurchaseOrderService $purchaseOrderService,
        ProductBatchService $productBatchService,
        SupplierPaymentService $supplierPaymentService,
        Store $store,
        Supplier $supplier,
    ): void {
        $products = Product::where('supplier_id', $supplier->id)->with('variants')->get();

        if ($products->isEmpty()) {
            return;
        }

        $items = [];

        foreach ($products as $product) {
            if ($product->isVariable()) {
                foreach ($product->variants as $variant) {
                    $items[] = $this->buildItem($product, $variant);
                }
            } else {
                $items[] = $this->buildItem($product, null);
            }
        }

        $po = $purchaseOrderService->createPurchaseOrder([
            'supplier_id' => $supplier->id,
            'store_id' => $store->id,
            'order_date' => now()->subDays(20)->toDateString(),
            'expected_delivery_date' => now()->subDays(15)->toDateString(),
            'items' => $items,
        ]);

        $purchaseOrderService->sendPurchaseOrder($po->id);

        $receivedItems = [];

        foreach ($po->items as $poItem) {
            $product = $poItem->product;
            $receiveData = ['quantity' => $poItem->quantity_ordered];

            if ($product->requires_batch_tracking) {
                $manufactureDate = now()->subDays(random_int(1, 3));
                $shelfLifeDays = $product->shelf_life_days ?? 180;

                $receiveData['manufacture_date'] = $manufactureDate->toDateString();
                $receiveData['expiry_date'] = $manufactureDate->copy()->addDays($shelfLifeDays)->toDateString();
            } elseif ($product->requires_serial_tracking) {
                $receiveData['serial_numbers'] = collect(range(1, (int) $poItem->quantity_ordered))
                    ->map(fn () => strtoupper(Str::random(2)).fake()->unique()->numerify('###########'))
                    ->all();
            }

            $receivedItems[$poItem->id] = $receiveData;
        }

        $productBatchService->receiveGoodsFromPurchaseOrder($po->id, $receivedItems);

        // Alternate full vs. partial payment so payment_status varies realistically.
        $this->poCount++;
        $po->refresh();
        $paymentAmount = $this->poCount % 2 === 0
            ? $po->total_amount
            : round($po->total_amount * 0.5, 2);

        $supplierPaymentService->recordPayment([
            'supplier_id' => $supplier->id,
            'purchase_order_id' => $po->id,
            'payment_date' => now()->subDays(10)->toDateString(),
            'amount' => $paymentAmount,
            'payment_method' => PaymentMethod::BANK_TRANSFER,
            'reference_number' => 'PMT-'.strtoupper(Str::random(8)),
        ]);
    }

    protected function buildItem(Product $product, ?ProductVariant $variant): array
    {
        $purchaseProductUom = $product->productUoms()
            ->where('is_purchase_uom', true)
            ->where('is_base_uom', false)
            ->first();

        $uomId = $purchaseProductUom->uom_id ?? $product->base_uom_id;
        $conversion = $purchaseProductUom ? (float) $purchaseProductUom->conversion_to_base : 1.0;

        $baseQuantity = match (true) {
            $product->requires_serial_tracking => random_int(8, 15),
            $product->is_weighed => random_int(80, 150),
            default => random_int(30, 80),
        };

        $quantityOrdered = $baseQuantity / $conversion;

        if (! $product->is_weighed) {
            $quantityOrdered = max(1, (int) round($quantityOrdered));
        }

        $unitCostInBaseUom = round($product->base_selling_price * 0.65, 2);

        return [
            'product_id' => $product->id,
            'variant_id' => $variant?->id,
            'uom_id' => $uomId,
            'quantity_ordered' => $quantityOrdered,
            'unit_cost' => round($unitCostInBaseUom * $conversion, 2),
            'tax_rate_id' => $product->tax_rate_id,
        ];
    }
}
