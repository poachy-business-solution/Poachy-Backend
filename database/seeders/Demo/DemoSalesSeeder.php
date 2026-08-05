<?php

namespace Database\Seeders\Demo;

use App\Enums\Tenant\CustomerType;
use App\Enums\Tenant\MarketplaceFulfillmentStatus;
use App\Enums\Tenant\PaymentMethod as MarketplacePaymentMethod;
use App\Enums\Tenant\PaymentStatus;
use App\Enums\Tenant\RefundMethod;
use App\Enums\Tenant\ShiftStatus;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Inventory;
use App\Models\Tenant\MarketplaceSale;
use App\Models\Tenant\MarketplaceSaleItem;
use App\Models\Tenant\MarketplaceSaleItemBatchDepletion;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductBatch;
use App\Models\Tenant\ProductBundle;
use App\Models\Tenant\ProductSerial;
use App\Models\Tenant\ProductVariant;
use App\Models\Tenant\Sale;
use App\Models\Tenant\ShiftAssignment;
use App\Models\Tenant\Store;
use App\Models\Tenant\TenantConfiguration;
use App\Services\Tenant\Sales\RefundService;
use App\Services\Tenant\Sales\SalesDailyAggregateService;
use App\Services\Tenant\Sales\SaleService;
use App\Services\Tenant\Sales\ShiftSalesSummaryService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class DemoSalesSeeder extends Seeder
{
    /** @var array<int, Sale> */
    protected array $createdSales = [];

    public function run(
        SaleService $saleService,
        RefundService $refundService,
        ShiftSalesSummaryService $shiftSalesSummaryService,
        SalesDailyAggregateService $salesDailyAggregateService,
    ): void {
        // These default to disabled on a fresh tenant (no seeder writes them) — turn
        // them on so credit/loyalty/refund tables actually get exercised below.
        TenantConfiguration::set('loyalty_enabled', true);
        TenantConfiguration::set('loyalty_earning_rate', 0.02);
        TenantConfiguration::set('credit_enabled', true);
        TenantConfiguration::set('pos.refunds_enabled', true);

        $registeredCustomers = Customer::where('customer_type', '!=', CustomerType::WALK_IN->value)->get();

        // Random sales below only take the credit path ~10% of the time when a
        // customer happens to be picked — not reliable enough to guarantee
        // customer_credit_transactions ends up non-empty on every run. One
        // deterministic credit sale first.
        $this->seedGuaranteedCreditSale($saleService, $shiftSalesSummaryService, $salesDailyAggregateService);

        $assignments = ShiftAssignment::whereIn('status', [ShiftStatus::COMPLETED, ShiftStatus::IN_PROGRESS])
            ->with('user')
            ->orderBy('shift_date')
            ->get();

        foreach ($assignments as $assignment) {
            $salesForShift = random_int(1, 3);

            for ($i = 0; $i < $salesForShift; $i++) {
                try {
                    $this->createRandomSale($saleService, $shiftSalesSummaryService, $salesDailyAggregateService, $assignment, $registeredCustomers);
                } catch (\Throwable $e) {
                    Log::warning('DemoSalesSeeder: skipped a random sale', ['error' => $e->getMessage()]);
                }
            }
        }

        $this->createRefunds($refundService);
        $this->createMarketplaceSales();

        $this->command->info('✓ Sales: '.count($this->createdSales).' POS sales, refunds + marketplace sales seeded');
    }

    protected function seedGuaranteedCreditSale(
        SaleService $saleService,
        ShiftSalesSummaryService $shiftSalesSummaryService,
        SalesDailyAggregateService $salesDailyAggregateService,
    ): void {
        $assignment = ShiftAssignment::where('status', ShiftStatus::IN_PROGRESS)->first();
        $customer = Customer::where('customer_type', CustomerType::WHOLESALE->value)->first();
        $product = Product::where('name', 'Reusable Shopping Bag')->first();

        if (! $assignment || ! $customer || ! $product) {
            return;
        }

        Auth::guard('tenant')->setUser($assignment->user);

        $sale = $saleService->createSale([
            'store_id' => $assignment->store_id,
            'customer_id' => $customer->id,
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
            'payments' => [['method' => 'credit', 'amount' => 0]],
        ]);

        if (! $sale->shift_assignment_id) {
            $sale->update(['shift_assignment_id' => $assignment->id]);
            $sale->refresh();
        }

        $this->createdSales[] = $sale;
        $shiftSalesSummaryService->updateFromSale($sale);
        $salesDailyAggregateService->updateFromSale($sale);
    }

    protected function createRandomSale(
        SaleService $saleService,
        ShiftSalesSummaryService $shiftSalesSummaryService,
        SalesDailyAggregateService $salesDailyAggregateService,
        ShiftAssignment $assignment,
        Collection $registeredCustomers,
    ): void {
        $backdated = $assignment->status === ShiftStatus::COMPLETED;

        $run = function () use ($saleService, $shiftSalesSummaryService, $salesDailyAggregateService, $assignment, $registeredCustomers) {
            Auth::guard('tenant')->setUser($assignment->user);

            $items = $this->pickRandomLineItems($assignment->store_id);

            if (empty($items)) {
                return;
            }

            $customer = random_int(1, 100) <= 45 ? $registeredCustomers->random() : null;
            $estimatedTotal = $this->estimateTotal($items);

            $data = [
                'store_id' => $assignment->store_id,
                'customer_id' => $customer?->id,
                'items' => $items,
                'payments' => $this->buildPayments($estimatedTotal, $customer),
            ];

            if ($customer && random_int(1, 100) <= 15 && $estimatedTotal >= 500) {
                $data['coupon_code'] = 'WELCOME10';
            }

            if ($customer && $customer->loyalty_points >= 100 && random_int(1, 100) <= 25) {
                $data['loyalty_points_to_redeem'] = 100.0;
            }

            $sale = $saleService->createSale($data);

            // SaleService derives shift_assignment_id from a *live* "currently in
            // progress, right now" lookup — for our backdated shifts (already
            // COMPLETED by the time this runs, regardless of the faked clock) that
            // always comes back null. Patch in the real historical assignment so the
            // summary services below (and any report reading this column) see it.
            if (! $sale->shift_assignment_id) {
                $sale->update(['shift_assignment_id' => $assignment->id]);
                $sale->refresh();
            }

            $this->createdSales[] = $sale;

            // Bypasses the queued SaleCompleted -> UpdateShiftSalesSummary listener
            // (QUEUE_CONNECTION=redis, so it wouldn't run inline during a seed script) —
            // call the same underlying service method directly instead.
            $shiftSalesSummaryService->updateFromSale($sale);
            $salesDailyAggregateService->updateFromSale($sale);
        };

        if ($backdated) {
            $start = Carbon::parse($assignment->actual_start);
            $end = Carbon::parse($assignment->actual_end);
            $minutesSpan = max(1, $start->diffInMinutes($end) - 1);

            $this->runAt($start->copy()->addMinutes(random_int(0, $minutesSpan)), $run);
        } else {
            $run();
        }
    }

    protected function pickRandomLineItems(int $storeId): array
    {
        $items = [];

        $productInventories = Inventory::where('store_id', $storeId)
            ->whereNull('product_variant_id')
            ->where('quantity_available', '>', 0)
            ->with('product')
            ->get()
            ->filter(fn (Inventory $inv) => $inv->product?->is_active);

        if ($productInventories->isEmpty()) {
            return [];
        }

        // 15% of the time, sell a bundle instead of loose items — safe now that
        // sale_items.product_id is nullable (see make_product_id_nullable_on_sale_items_table).
        if (random_int(1, 100) <= 15) {
            $bundle = ProductBundle::where('is_active', true)->inRandomOrder()->first();

            if ($bundle) {
                return [['bundle_id' => $bundle->id, 'quantity' => 1]];
            }
        }

        $count = random_int(1, min(3, $productInventories->count()));
        $chosen = $productInventories->random($count);
        $chosen = $chosen instanceof Collection ? $chosen : collect([$chosen]);

        foreach ($chosen as $inventory) {
            $product = $inventory->product;
            $maxQty = $product->requires_serial_tracking ? 1 : (int) min(3, floor($inventory->quantity_available));

            if ($maxQty < 1) {
                continue;
            }

            $quantity = $product->is_weighed
                ? round(random_int(5, 20) / 10, 1)
                : random_int(1, $maxQty);

            $item = ['product_id' => $product->id, 'quantity' => $quantity];

            if ($product->requires_serial_tracking) {
                $serials = ProductSerial::where('store_id', $storeId)
                    ->where('product_id', $product->id)
                    ->available()
                    ->limit((int) $quantity)
                    ->pluck('serial_number')
                    ->all();

                if (count($serials) < $quantity) {
                    continue;
                }

                $item['serial_numbers'] = $serials;
            }

            $items[] = $item;
        }

        // Occasionally add one variant-level item (sneakers/t-shirt sizes).
        if (random_int(1, 100) <= 20) {
            $variantInventory = Inventory::where('store_id', $storeId)
                ->whereNotNull('product_variant_id')
                ->where('quantity_available', '>', 0)
                ->inRandomOrder()
                ->first();

            if ($variantInventory) {
                $items[] = [
                    'product_id' => $variantInventory->product_id,
                    'variant_id' => $variantInventory->product_variant_id,
                    'quantity' => 1,
                ];
            }
        }

        return $items;
    }

    protected function estimateTotal(array $items): float
    {
        $total = 0;

        foreach ($items as $item) {
            if (isset($item['bundle_id'])) {
                $total += ProductBundle::find($item['bundle_id'])->bundle_price * $item['quantity'];

                continue;
            }

            if (isset($item['variant_id'])) {
                $variant = ProductVariant::find($item['variant_id']);
                $total += ($variant->variant_price ?? $variant->product->base_selling_price) * $item['quantity'];

                continue;
            }

            $product = Product::find($item['product_id']);
            $total += $product->base_selling_price * $item['quantity'];
        }

        // Generous buffer over the real server-calculated total (which only ever
        // adds ~16% tax and can only reduce via discounts) so cash tendered always covers it.
        return ceil(($total * 1.2) / 50) * 50;
    }

    protected function buildPayments(float $estimatedTotal, ?Customer $customer): array
    {
        if ($customer && $customer->credit_limit > 0 && random_int(1, 100) <= 10) {
            return [['method' => 'credit', 'amount' => 0]];
        }

        $method = Arr::random(['cash', 'cash', 'mpesa', 'mpesa', 'card']);

        return [[
            'method' => $method,
            'amount' => $estimatedTotal,
            'reference' => $method === 'cash' ? null : strtoupper($method).'-'.random_int(100000, 999999),
        ]];
    }

    protected function createRefunds(RefundService $refundService): void
    {
        $refundableSales = collect($this->createdSales)
            ->filter(fn (Sale $sale) => $sale->payment_status === PaymentStatus::PAID)
            ->shuffle()
            ->take(5);

        foreach ($refundableSales as $sale) {
            $sale->loadMissing('items.product');
            // Bundle line items have product_id = null (no ->product to check), and
            // serial-tracked items require an explicit 'serial_numbers' array matching
            // the refunded quantity (ProductSerialService::restoreSerialsForRefund) —
            // skip both here and refund a plain item instead, to keep this a
            // straightforward demo refund rather than looking up which serials sold.
            $item = $sale->items->first(fn ($i) => $i->product && ! $i->product->requires_serial_tracking);

            if (! $item) {
                continue;
            }

            try {
                $refundService->processRefund($sale, [
                    'store_id' => $sale->store_id,
                    'reason' => Arr::random(['customer_changed_mind', 'defective', 'wrong_item']),
                    'refund_method' => RefundMethod::ORIGINAL_METHOD->value,
                    'notes' => 'Demo refund — customer request.',
                    'items' => [[
                        'sale_item_id' => $item->id,
                        'quantity_refunded' => min(1, (float) $item->quantity),
                        'refund_amount' => round((float) $item->unit_price * min(1, (float) $item->quantity), 2),
                    ]],
                ]);
            } catch (\Throwable $e) {
                Log::warning('DemoSalesSeeder: skipped a refund', ['sale_id' => $sale->id, 'error' => $e->getMessage()]);
            }
        }
    }

    /**
     * marketplace_sales/marketplace_sale_items are normally created by an inbound
     * sync job when central forwards a paid marketplace order — MarketplaceSaleService
     * itself has no create path (list/update-fulfillment only), so there's no service
     * to route through. Built directly via the existing factories instead.
     */
    protected function createMarketplaceSales(): void
    {
        $cbd = Store::mainStore()->firstOrFail();
        // Not filtered to is_available_online — only 1 non-serial product (the
        // sneakers) actually has that flag set, which would make every marketplace
        // sale below reference the same product. Any non-serial product works fine
        // as synthetic marketplace order data.
        $catalogue = Product::where('requires_serial_tracking', false)->get();

        if ($catalogue->isEmpty()) {
            return;
        }

        $statuses = [
            MarketplaceFulfillmentStatus::DELIVERED,
            MarketplaceFulfillmentStatus::PREPARING,
            MarketplaceFulfillmentStatus::READY,
            MarketplaceFulfillmentStatus::PENDING,
        ];

        foreach ($statuses as $index => $status) {
            $product = $catalogue->get($index % $catalogue->count());
            $quantity = random_int(1, 3);
            $unitPrice = $product->online_price ?? $product->base_selling_price;
            $subtotal = round($unitPrice * $quantity, 2);
            $taxAmount = round($subtotal * ($product->taxRate->rate ?? 16) / 100, 2);

            // MarketplaceSale/MarketplaceSaleItem have factories under database/factories/,
            // but Model::factory() resolves to Database\Factories\Tenant\* (mirroring the
            // App\Models\Tenant\* namespace) for these tenant models, which doesn't exist —
            // the factories are unused dead code (no test references them either). Created
            // directly instead of via ::factory()->create().
            $sale = MarketplaceSale::create([
                'central_order_id' => 9000 + $index,
                'sale_number' => 'MKT-ORD-'.now()->year.'-'.str_pad((string) (9000 + $index), 6, '0', STR_PAD_LEFT),
                'store_id' => $cbd->id,
                'sale_date' => now(),
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'discount_amount' => 0,
                'total_amount' => round($subtotal + $taxAmount, 2),
                'payment_status' => PaymentStatus::PAID,
                'amount_paid' => round($subtotal + $taxAmount, 2),
                'amount_due' => 0,
                'payment_method' => MarketplacePaymentMethod::MPESA,
                'payment_reference' => 'MKT-REF-'.random_int(10000000, 99999999),
                'fulfillment_type' => 'delivery',
                'fulfillment_status' => $status,
            ]);

            $saleItem = MarketplaceSaleItem::create([
                'marketplace_sale_id' => $sale->id,
                'product_id' => $product->id,
                'uom_id' => $product->base_uom_id,
                'quantity' => $quantity,
                'quantity_in_base_uom' => $quantity,
                'unit_price' => $unitPrice,
                'tax_amount' => $taxAmount,
                'discount_amount' => 0,
                'subtotal' => round($subtotal + $taxAmount, 2),
            ]);

            if ($product->requires_batch_tracking) {
                $batch = ProductBatch::where('store_id', $cbd->id)
                    ->where('product_id', $product->id)
                    ->where('quantity_remaining_in_base_uom', '>', $quantity)
                    ->first();

                if ($batch) {
                    MarketplaceSaleItemBatchDepletion::create([
                        'marketplace_sale_item_id' => $saleItem->id,
                        'product_id' => $product->id,
                        'batch_id' => $batch->id,
                        'quantity_in_base_uom' => $quantity,
                    ]);

                    $batch->decrement('quantity_remaining_in_base_uom', $quantity);

                    Inventory::where('store_id', $cbd->id)
                        ->where('product_id', $product->id)
                        ->whereNull('product_variant_id')
                        ->decrement('quantity_on_hand', $quantity);

                    Inventory::where('store_id', $cbd->id)
                        ->where('product_id', $product->id)
                        ->whereNull('product_variant_id')
                        ->decrement('quantity_available', $quantity);
                }
            }
        }
    }

    protected function runAt(Carbon $when, callable $callback): mixed
    {
        Carbon::setTestNow($when);

        try {
            return $callback();
        } finally {
            Carbon::setTestNow();
        }
    }
}
