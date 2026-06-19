<?php

namespace App\Services\Tenant\Sales;

use App\Models\Tenant\Sale;
use App\Models\Tenant\SaleItem;
use App\Models\Tenant\SalesDailyAggregate;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SalesDailyAggregateService
{
    private const CACHE_TTL = 3600; // 1 hour

    /**
     * Update aggregates from a completed sale
     * This is the main entry point called by queued jobs
     *
     * @param Sale $sale
     * @return void
     */
    public function updateFromSale(Sale $sale): void
    {
        DB::transaction(function () use ($sale) {
            $aggregateDate = $sale->sale_date->toDateString();
            $storeId = $sale->store_id;

            // Load sale items with relationships
            $sale->loadMissing([
                'items.product.category',
                'items.productVariant',
                'items.bundle',
            ]);

            // Process each sale item
            foreach ($sale->items as $item) {
                $this->updateAggregateForItem($aggregateDate, $storeId, $item, $sale);
            }

            // Clear cache for this date/store
            $this->clearCache($aggregateDate, $storeId);

            Log::info('Daily aggregates updated from sale', [
                'tenant_id' => tenant()->id,
                'sale_id' => $sale->id,
                'sale_number' => $sale->sale_number,
                'aggregate_date' => $aggregateDate,
                'store_id' => $storeId,
                'items_processed' => $sale->items->count(),
            ]);
        });
    }

    /**
     * Update aggregate for a single sale item
     *
     * @param string $aggregateDate
     * @param int $storeId
     * @param SaleItem $item
     * @param Sale $sale
     * @return void
     */
    protected function updateAggregateForItem(
        string $aggregateDate,
        int $storeId,
        SaleItem $item,
        Sale $sale
    ): void {
        // Determine sellable type and IDs
        $aggregateData = $this->determineSellableData($item);

        // Calculate metrics
        $metrics = $this->calculateMetrics($item);

        // Upsert aggregate record with atomic increments
        DB::table('sales_daily_aggregates')->upsert(
            [
                'aggregate_date' => $aggregateDate,
                'store_id' => $storeId,
                'sellable_type' => $aggregateData['sellable_type'],
                'product_id' => $aggregateData['product_id'],
                'product_variant_id' => $aggregateData['product_variant_id'],
                'bundle_id' => $aggregateData['bundle_id'],
                'category_id' => $aggregateData['category_id'],
                'total_quantity_sold' => $metrics['quantity'],
                'total_revenue' => $metrics['revenue'],
                'total_cost' => $metrics['cost'],
                'total_profit' => $metrics['profit'],
                'total_tax' => $metrics['tax'],
                'total_discount' => $metrics['discount'],
                'transaction_count' => 1,
                'unique_customers' => 0, // Updated separately by job
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'aggregate_date',
                'store_id',
                'sellable_type',
                'product_id',
                'product_variant_id',
                'bundle_id',
            ],
            [
                'total_quantity_sold' => DB::raw("total_quantity_sold + {$metrics['quantity']}"),
                'total_revenue' => DB::raw("total_revenue + {$metrics['revenue']}"),
                'total_cost' => DB::raw("total_cost + {$metrics['cost']}"),
                'total_profit' => DB::raw("total_profit + {$metrics['profit']}"),
                'total_tax' => DB::raw("total_tax + {$metrics['tax']}"),
                'total_discount' => DB::raw("total_discount + {$metrics['discount']}"),
                'transaction_count' => DB::raw('transaction_count + 1'),
                'updated_at' => now(),
            ]
        );
    }

    /**
     * Determine sellable type and related IDs from sale item
     *
     * @param SaleItem $item
     * @return array
     */
    protected function determineSellableData(SaleItem $item): array
    {
        if ($item->bundle_id) {
            return [
                'sellable_type' => 'ProductBundle',
                'product_id' => null,
                'product_variant_id' => null,
                'bundle_id' => $item->bundle_id,
                'category_id' => null, // Bundles don't have categories
            ];
        }

        if ($item->product_variant_id) {
            return [
                'sellable_type' => 'ProductVariant',
                'product_id' => $item->product_id,
                'product_variant_id' => $item->product_variant_id,
                'bundle_id' => null,
                'category_id' => $item->product->category_id ?? null,
            ];
        }

        return [
            'sellable_type' => 'Product',
            'product_id' => $item->product_id,
            'product_variant_id' => null,
            'bundle_id' => null,
            'category_id' => $item->product->category_id ?? null,
        ];
    }

    /**
     * Calculate metrics from sale item
     *
     * @param SaleItem $item
     * @return array
     */
    protected function calculateMetrics(SaleItem $item): array
    {
        // Revenue = subtotal (final amount after discount + tax)
        $revenue = (float) $item->subtotal;

        // Cost = COGS
        $cost = (float) ($item->unit_cost * $item->quantity);

        // Profit = Revenue - Cost
        $profit = $revenue - $cost;

        return [
            'quantity' => (float) $item->quantity_in_base_uom,
            'revenue' => $revenue,
            'cost' => $cost,
            'profit' => $profit,
            'tax' => (float) $item->tax_amount,
            'discount' => (float) $item->discount_amount,
        ];
    }

    /**
     * Update unique customer count for a date/store
     * Called by queued job
     *
     * @param string $aggregateDate
     * @param int $storeId
     * @return void
     */
    public function updateUniqueCustomerCount(string $aggregateDate, int $storeId): void
    {
        $uniqueCustomers = Sale::whereDate('sale_date', $aggregateDate)
            ->where('store_id', $storeId)
            ->whereNotNull('customer_id')
            ->distinct('customer_id')
            ->count('customer_id');

        // Update all aggregates for this date/store
        SalesDailyAggregate::where('aggregate_date', $aggregateDate)
            ->where('store_id', $storeId)
            ->update(['unique_customers' => $uniqueCustomers]);

        Log::info('Updated unique customer count for aggregates', [
            'tenant_id' => tenant()->id,
            'aggregate_date' => $aggregateDate,
            'store_id' => $storeId,
            'unique_customers' => $uniqueCustomers,
        ]);
    }

    /**
     * Get aggregates for a specific date and store
     *
     * @param Carbon|string $date
     * @param int $storeId
     * @return Collection
     */
    public function getAggregatesForDate(Carbon|string $date, int $storeId): Collection
    {
        $dateString = $date instanceof Carbon ? $date->toDateString() : $date;
        $cacheKey = $this->getCacheKey("date:{$dateString}:store:{$storeId}");

        return Cache::tags($this->getCacheTags($storeId))->remember(
            $cacheKey,
            self::CACHE_TTL,
            function () use ($dateString, $storeId) {
                return SalesDailyAggregate::forDate($dateString)
                    ->forStore($storeId)
                    ->withDetails()
                    ->orderBy('total_revenue', 'desc')
                    ->get();
            }
        );
    }

    /**
     * Get aggregates for a date range
     *
     * @param Carbon|string $from
     * @param Carbon|string $to
     * @param int $storeId
     * @return Collection
     */
    public function getAggregatesForDateRange(
        Carbon|string $from,
        Carbon|string $to,
        int $storeId
    ): Collection {
        $fromString = $from instanceof Carbon ? $from->toDateString() : $from;
        $toString = $to instanceof Carbon ? $to->toDateString() : $to;

        return SalesDailyAggregate::forDateRange($fromString, $toString)
            ->forStore($storeId)
            ->withDetails()
            ->orderBy('aggregate_date', 'desc')
            ->orderBy('total_revenue', 'desc')
            ->get();
    }

    /**
     * Get top selling products for a date
     *
     * @param Carbon|string $date
     * @param int $storeId
     * @param int $limit
     * @return Collection
     */
    public function getTopSellingProducts(
        Carbon|string $date,
        int $storeId,
        int $limit = 10
    ): Collection {
        $dateString = $date instanceof Carbon ? $date->toDateString() : $date;

        return SalesDailyAggregate::forDate($dateString)
            ->forStore($storeId)
            ->withDetails()
            ->topSelling($limit)
            ->get();
    }

    /**
     * Get top revenue products for a date
     *
     * @param Carbon|string $date
     * @param int $storeId
     * @param int $limit
     * @return Collection
     */
    public function getTopRevenueProducts(
        Carbon|string $date,
        int $storeId,
        int $limit = 10
    ): Collection {
        $dateString = $date instanceof Carbon ? $date->toDateString() : $date;

        return SalesDailyAggregate::forDate($dateString)
            ->forStore($storeId)
            ->withDetails()
            ->topRevenue($limit)
            ->get();
    }

    /**
     * Get category summary for a date
     *
     * @param Carbon|string $date
     * @param int $storeId
     * @return Collection
     */
    public function getCategorySummary(Carbon|string $date, int $storeId): Collection
    {
        $dateString = $date instanceof Carbon ? $date->toDateString() : $date;

        return SalesDailyAggregate::forDate($dateString)
            ->forStore($storeId)
            ->whereNotNull('category_id')
            ->select(
                'category_id',
                DB::raw('SUM(total_quantity_sold) as total_quantity'),
                DB::raw('SUM(total_revenue) as total_revenue'),
                DB::raw('SUM(total_cost) as total_cost'),
                DB::raw('SUM(total_profit) as total_profit'),
                DB::raw('SUM(total_tax) as total_tax'),
                DB::raw('SUM(total_discount) as total_discount'),
                DB::raw('COUNT(DISTINCT CASE WHEN sellable_type = "Product" THEN product_id 
                         WHEN sellable_type = "ProductVariant" THEN product_variant_id 
                         ELSE bundle_id END) as unique_items')
            )
            ->with('category:id,name,slug')
            ->groupBy('category_id')
            ->orderBy('total_revenue', 'desc')
            ->get();
    }

    /**
     * Get store-level summary (all products combined)
     *
     * @param Carbon|string $date
     * @param int $storeId
     * @return array
     */
    public function getStoreSummary(Carbon|string $date, int $storeId): array
    {
        $dateString = $date instanceof Carbon ? $date->toDateString() : $date;

        $summary = SalesDailyAggregate::forDate($dateString)
            ->forStore($storeId)
            ->selectRaw('
                SUM(total_quantity_sold) as total_quantity,
                SUM(total_revenue) as total_revenue,
                SUM(total_cost) as total_cost,
                SUM(total_profit) as total_profit,
                SUM(total_tax) as total_tax,
                SUM(total_discount) as total_discount,
                SUM(transaction_count) as total_transactions,
                MAX(unique_customers) as unique_customers
            ')
            ->first();

        if (!$summary) {
            return [
                'total_quantity' => 0,
                'total_revenue' => 0,
                'total_cost' => 0,
                'total_profit' => 0,
                'total_tax' => 0,
                'total_discount' => 0,
                'total_transactions' => 0,
                'unique_customers' => 0,
                'profit_margin_percentage' => 0,
                'average_transaction_value' => 0,
            ];
        }

        $totalRevenue = (float) $summary->total_revenue;
        $totalProfit = (float) $summary->total_profit;
        $totalTransactions = (int) $summary->total_transactions;

        return [
            'total_quantity' => round((float) $summary->total_quantity, 4),
            'total_revenue' => round($totalRevenue, 2),
            'total_cost' => round((float) $summary->total_cost, 2),
            'total_profit' => round($totalProfit, 2),
            'total_tax' => round((float) $summary->total_tax, 2),
            'total_discount' => round((float) $summary->total_discount, 2),
            'total_transactions' => $totalTransactions,
            'unique_customers' => (int) $summary->unique_customers,
            'profit_margin_percentage' => $totalRevenue > 0
                ? round(($totalProfit / $totalRevenue) * 100, 2)
                : 0,
            'average_transaction_value' => $totalTransactions > 0
                ? round($totalRevenue / $totalTransactions, 2)
                : 0,
        ];
    }

    /**
     * Recalculate aggregates for a specific date/store
     * Used for corrections or initial backfill
     *
     * @param Carbon|string $date
     * @param int $storeId
     * @return Collection
     */
    public function recalculateForDate(Carbon|string $date, int $storeId): Collection
    {
        return DB::transaction(function () use ($date, $storeId) {
            $dateString = $date instanceof Carbon ? $date->toDateString() : $date;

            // Delete existing aggregates for this date/store
            SalesDailyAggregate::where('aggregate_date', $dateString)
                ->where('store_id', $storeId)
                ->forceDelete();

            // Get all sales for this date/store
            $sales = Sale::whereDate('sale_date', $dateString)
                ->where('store_id', $storeId)
                ->with([
                    'items.product.category',
                    'items.productVariant',
                    'items.bundle',
                ])
                ->get();

            // Rebuild aggregates from scratch
            foreach ($sales as $sale) {
                foreach ($sale->items as $item) {
                    $this->updateAggregateForItem($dateString, $storeId, $item, $sale);
                }
            }

            // Update unique customer count
            $this->updateUniqueCustomerCount($dateString, $storeId);

            // Clear cache
            $this->clearCache($dateString, $storeId);

            Log::info('Daily aggregates recalculated', [
                'tenant_id' => tenant()->id,
                'aggregate_date' => $dateString,
                'store_id' => $storeId,
                'sales_processed' => $sales->count(),
            ]);

            return $this->getAggregatesForDate($dateString, $storeId);
        });
    }

    /**
     * Clear cache for a date/store
     *
     * @param string $date
     * @param int $storeId
     * @return void
     */
    protected function clearCache(string $date, int $storeId): void
    {
        Cache::tags($this->getCacheTags($storeId))->flush();
    }

    /**
     * Get cache key
     *
     * @param string $suffix
     * @return string
     */
    protected function getCacheKey(string $suffix): string
    {
        $tenantId = tenant()->id ?? 'global';
        return "daily_aggregates:{$tenantId}:{$suffix}";
    }

    /**
     * Get cache tags
     *
     * @param int $storeId
     * @return array
     */
    protected function getCacheTags(int $storeId): array
    {
        $tenantId = tenant()->id ?? 'global';
        return [
            "tenant:{$tenantId}",
            "daily_aggregates:{$tenantId}",
            "store:{$storeId}:aggregates",
        ];
    }
}
