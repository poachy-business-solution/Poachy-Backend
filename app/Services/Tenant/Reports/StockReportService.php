<?php

namespace App\Services\Tenant\Reports;

use App\Models\Tenant\Inventory;
use App\Models\Tenant\Product;
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
     * a week left), dead (none sold in [$deadDays] days though in stock), ok.
     *
     * @return array{store_id: int, as_of: string, dead_after_days: int, summary: array<string, float|int>, items: array<int, array<string, mixed>>}
     */
    public function stockHealth(int $storeId, int $deadDays = 30, ?string $asOf = null): array
    {
        $asOf = CarbonImmutable::parse($asOf ?? $this->tillService->localToday());
        [$windowStart] = $this->overviews->bounds($asOf->subDays(29), $asOf);
        [$deadStart] = $this->overviews->bounds($asOf->subDays($deadDays - 1), $asOf);

        $inventory = Inventory::query()
            ->where('store_id', $storeId)
            ->whereNull('product_variant_id')
            ->get(['product_id', 'quantity_on_hand'])
            ->keyBy('product_id');

        $products = Product::query()
            ->whereIn('id', $inventory->keys())
            ->get(['id', 'uuid', 'name', 'reorder_level', 'requires_serial_tracking', 'base_selling_price'])
            ->keyBy('id');

        $storeProducts = StoreProduct::query()
            ->where('store_id', $storeId)
            ->whereNull('product_variant_id')
            ->get(['product_id', 'min_stock_level', 'store_selling_price'])
            ->keyBy('product_id');
        $minLevels = $storeProducts->map(fn ($row) => $row->min_stock_level);

        $soldLast30 = $this->quantitySold($storeId, $windowStart);
        $lastSoldAt = SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.store_id', $storeId)
            ->whereNotNull('sale_items.product_id')
            ->groupBy('sale_items.product_id')
            ->selectRaw('sale_items.product_id, MAX(sales.sale_date) as last_sold_at')
            ->pluck('last_sold_at', 'product_id');

        $items = $inventory->map(function ($row, $productId) use ($products, $minLevels, $storeProducts, $soldLast30, $lastSoldAt, $deadStart, $storeId) {
            $product = $products->get($productId);
            $onHand = (float) $row->quantity_on_hand;
            $sold = (float) ($soldLast30[$productId] ?? 0);
            $perDay = $sold / 30;
            $daysLeft = $perDay > 0 ? round(max(0, $onHand) / $perDay, 1) : null;
            $reorderAt = (float) ($minLevels[$productId] ?? $product?->reorder_level ?? 0);
            $lastSold = $lastSoldAt[$productId] ?? null;
            $unitCost = $product ? $this->costs->unitCost($product, $storeId) : null;

            $status = match (true) {
                $onHand <= 0 => 'out',
                $onHand <= $reorderAt || ($daysLeft !== null && $daysLeft < 7) => 'low',
                $lastSold === null || CarbonImmutable::parse($lastSold)->lessThan($deadStart) => 'dead',
                default => 'ok',
            };

            return [
                'product_id' => (int) $productId,
                'uuid' => $product?->uuid,
                'name' => $product?->name ?? 'Unknown',
                'selling_price' => round((float) ($storeProducts[$productId]->store_selling_price ?? $product?->base_selling_price ?? 0), 2),
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

        $order = ['out' => 0, 'low' => 1, 'dead' => 2, 'ok' => 3];

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
     * Base-unit quantity sold per product at the branch since [$since].
     *
     * @return Collection<int, float>
     */
    protected function quantitySold(int $storeId, CarbonImmutable $since): Collection
    {
        return SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.store_id', $storeId)
            ->where('sales.sale_date', '>=', $since)
            ->whereNotNull('sale_items.product_id')
            ->groupBy('sale_items.product_id')
            ->selectRaw('sale_items.product_id, SUM(sale_items.quantity_in_base_uom) as sold')
            ->pluck('sold', 'product_id')
            ->map(fn ($sold) => (float) $sold);
    }
}
