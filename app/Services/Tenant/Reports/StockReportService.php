<?php

namespace App\Services\Tenant\Reports;

use App\Models\Tenant\Inventory;
use App\Models\Tenant\InventoryMovement;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductVariant;
use App\Models\Tenant\Sale;
use App\Models\Tenant\SaleItem;
use App\Models\Tenant\StoreProduct;
use App\Services\Tenant\Inventory\ProductCostService;
use App\Services\Tenant\Shift\TillService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Stock health at a branch — what's running low, what isn't selling, how many
 * days each item will last — and when in the day the shop sells.
 */
class StockReportService
{
    public function __construct(
        protected OwnerOverviewService $overviews,
        protected ProductCostService $costs,
        protected TillService $tillService,
    ) {}

    /**
     * Sales per local hour over a period (0–23), for "when do I need help".
     *
     * @return array{period: array{from: string, to: string}, hours: array<int, array{hour: int, sales_count: int, sales_amount: float}>, busiest_hour: int|null}
     */
    public function salesByHour(string $from, string $to, ?int $storeId = null): array
    {
        $from = CarbonImmutable::parse($from);
        $to = CarbonImmutable::parse($to);
        $timezone = $this->tillService->timezone();

        $buckets = array_fill(0, 24, ['count' => 0, 'amount' => 0.0]);

        $this->overviews->salesQuery($from, $to, $storeId)
            ->get(['sale_date', 'total_amount'])
            ->each(function (Sale $sale) use (&$buckets, $timezone) {
                $hour = (int) $sale->sale_date->copy()->setTimezone($timezone)->format('G');
                $buckets[$hour]['count']++;
                $buckets[$hour]['amount'] += (float) $sale->total_amount;
            });

        $hours = array_map(fn (int $hour) => [
            'hour' => $hour,
            'sales_count' => $buckets[$hour]['count'],
            'sales_amount' => round($buckets[$hour]['amount'], 2),
        ], range(0, 23));

        $busiest = collect($hours)->sortByDesc('sales_amount')->first();

        return [
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'hours' => $hours,
            'busiest_hour' => ($busiest['sales_amount'] ?? 0) > 0 ? $busiest['hour'] : null,
        ];
    }

    /**
     * Every stocked product at the branch with its level, recent sales, days
     * of stock left and status: low (at or under its reorder level, or under
     * a week left), dead (none sold in [$deadDays] days though in stock for at
     * least that long), new (in stock, arrived within the window, not sold yet), ok.
     *
     * @return array{store_id: int, as_of: string, dead_after_days: int, summary: array<string, float|int>, items: array<int, array<string, mixed>>}
     */
    public function stockHealth(int $storeId, int $deadDays = 30, ?string $asOf = null): array
    {
        $asOf = CarbonImmutable::parse($asOf ?? $this->tillService->localToday());
        [$windowStart] = $this->overviews->bounds($asOf->subDays(29), $asOf);
        [$deadStart] = $this->overviews->bounds($asOf->subDays($deadDays - 1), $asOf);

        // One row per product, and one per colour/size for products that have them.
        $inventory = Inventory::query()
            ->where('store_id', $storeId)
            ->get(['product_id', 'product_variant_id', 'quantity_on_hand'])
            ->keyBy(fn (Inventory $row) => self::lineKey($row->product_id, $row->product_variant_id));

        $products = Product::query()
            ->whereIn('id', $inventory->pluck('product_id')->unique())
            ->get(['id', 'uuid', 'name', 'reorder_level', 'requires_serial_tracking', 'requires_batch_tracking', 'base_selling_price', 'base_uom_id'])
            ->keyBy('id');

        $variants = ProductVariant::query()
            ->whereIn('id', $inventory->pluck('product_variant_id')->filter()->unique())
            ->get(['id', 'product_id', 'variant_name', 'variant_price', 'reorder_level', 'is_active'])
            ->keyBy('id');

        $storeProducts = StoreProduct::query()
            ->where('store_id', $storeId)
            ->get(['product_id', 'product_variant_id', 'min_stock_level', 'store_selling_price'])
            ->keyBy(fn (StoreProduct $row) => self::lineKey($row->product_id, $row->product_variant_id));

        $soldLast30 = $this->quantitySold($storeId, $windowStart);
        // When each line first arrived: stock younger than the dead window is "new", not "not selling".
        $firstReceivedAt = InventoryMovement::query()
            ->where('store_id', $storeId)
            ->whereIn('movement_type', ProductCostService::INCOMING_TYPES)
            ->where('quantity_in_base_uom', '>', 0)
            ->groupBy('product_id', 'product_variant_id')
            ->selectRaw('product_id, product_variant_id, MIN(created_at) as first_received_at')
            ->get()
            ->mapWithKeys(fn ($row) => [self::lineKey($row->product_id, $row->product_variant_id) => $row->first_received_at]);
        $lastSoldAt = SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.store_id', $storeId)
            ->whereNotNull('sale_items.product_id')
            ->groupBy('sale_items.product_id', 'sale_items.product_variant_id')
            ->selectRaw('sale_items.product_id, sale_items.product_variant_id, MAX(sales.sale_date) as last_sold_at')
            ->get()
            ->keyBy(fn ($row) => self::lineKey($row->product_id, $row->product_variant_id))
            ->map(fn ($row) => $row->last_sold_at);

        $items = $inventory->map(function (Inventory $row, string $key) use ($products, $variants, $storeProducts, $soldLast30, $lastSoldAt, $firstReceivedAt, $deadStart, $storeId) {
            $productId = (int) $row->product_id;
            $variantId = $row->product_variant_id === null ? null : (int) $row->product_variant_id;
            $product = $products->get($productId);
            $variant = $variantId === null ? null : $variants->get($variantId);
            $storeProduct = $storeProducts->get($key);
            $onHand = (float) $row->quantity_on_hand;
            $sold = (float) ($soldLast30[$key] ?? 0);
            $perDay = $sold / 30;
            $daysLeft = $perDay > 0 ? round(max(0, $onHand) / $perDay, 1) : null;
            $branchMinimum = (float) ($storeProduct->min_stock_level ?? 0);
            $reorderAt = $branchMinimum > 0 ? $branchMinimum : (float) ($variant?->reorder_level ?? $product?->reorder_level ?? 0);
            $lastSold = $lastSoldAt[$key] ?? null;
            $firstReceived = $firstReceivedAt[$key] ?? null;
            $recentlyReceived = $firstReceived !== null && CarbonImmutable::parse($firstReceived)->greaterThanOrEqualTo($deadStart);
            $unitCost = $product ? $this->costs->unitCost($product, $storeId, $variantId) : null;
            $name = $product?->name ?? 'Unknown';

            $status = match (true) {
                $onHand <= 0 => 'out',
                $onHand <= $reorderAt || ($daysLeft !== null && $daysLeft < 7) => 'low',
                $lastSold === null || CarbonImmutable::parse($lastSold)->lessThan($deadStart) => $recentlyReceived ? 'new' : 'dead',
                default => 'ok',
            };

            return [
                'product_id' => $productId,
                'variant_id' => $variantId,
                'variant_name' => $variant?->variant_name,
                'uuid' => $product?->uuid,
                'base_uom_id' => $product?->base_uom_id,
                'tracking' => $product?->requires_serial_tracking ? 'serial' : ($product?->requires_batch_tracking ? 'batch' : 'none'),
                'name' => $variant ? "{$name} — {$variant->variant_name}" : $name,
                'selling_price' => round((float) ($storeProduct->store_selling_price ?? $variant?->variant_price ?? $product?->base_selling_price ?? 0), 2),
                'on_hand' => round($onHand, 4),
                'reorder_level' => $reorderAt,
                'sold_last_30_days' => round($sold, 4),
                'days_left' => $daysLeft,
                'last_sold_at' => $lastSold ? CarbonImmutable::parse($lastSold)->toISOString() : null,
                'unit_cost' => $unitCost !== null ? round($unitCost, 2) : null,
                'stock_value' => $unitCost !== null ? round(max(0, $onHand) * $unitCost, 2) : null,
                'status' => $status,
            ];
        })->values();

        $order = ['out' => 0, 'low' => 1, 'dead' => 2, 'new' => 3, 'ok' => 4];

        return [
            'store_id' => $storeId,
            'as_of' => $asOf->toDateString(),
            'dead_after_days' => $deadDays,
            'summary' => [
                'products' => $items->count(),
                'out' => $items->where('status', 'out')->count(),
                'low' => $items->where('status', 'low')->count(),
                'dead' => $items->where('status', 'dead')->count(),
                'dead_stock_value' => round((float) $items->where('status', 'dead')->sum('stock_value'), 2),
                'stock_value' => round((float) $items->sum('stock_value'), 2),
            ],
            'items' => $items->sortBy(fn (array $item) => sprintf('%d-%010.1f-%s', $order[$item['status']], $item['days_left'] ?? 99999, $item['name']))->values()->all(),
        ];
    }

    /**
     * Base-unit quantity sold per product (and colour/size) at the branch since [$since].
     *
     * @return Collection<string, float>
     */
    protected function quantitySold(int $storeId, CarbonImmutable $since): Collection
    {
        return SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.store_id', $storeId)
            ->where('sales.sale_date', '>=', $since)
            ->whereNotNull('sale_items.product_id')
            ->groupBy('sale_items.product_id', 'sale_items.product_variant_id')
            ->selectRaw('sale_items.product_id, sale_items.product_variant_id, SUM(sale_items.quantity_in_base_uom) as sold')
            ->get()
            ->mapWithKeys(fn ($row) => [self::lineKey($row->product_id, $row->product_variant_id) => (float) $row->sold]);
    }

    /** "product" or "product:variant" — one stock line per colour/size. */
    protected static function lineKey(int|string $productId, int|string|null $variantId): string
    {
        return $variantId === null ? (string) $productId : "{$productId}:{$variantId}";
    }
}
