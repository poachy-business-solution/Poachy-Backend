<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class SalesDailyAggregate extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'sales_daily_aggregates';

    protected $fillable = [
        'aggregate_date',
        'store_id',
        'sellable_type',
        'product_id',
        'product_variant_id',
        'bundle_id',
        'category_id',
        'total_quantity_sold',
        'total_revenue',
        'total_cost',
        'total_profit',
        'total_tax',
        'total_discount',
        'transaction_count',
        'unique_customers',
    ];

    protected $casts = [
        'aggregate_date' => 'date',
        'total_quantity_sold' => 'decimal:4',
        'total_revenue' => 'decimal:2',
        'total_cost' => 'decimal:2',
        'total_profit' => 'decimal:2',
        'total_tax' => 'decimal:2',
        'total_discount' => 'decimal:2',
        'transaction_count' => 'integer',
        'unique_customers' => 'integer',
    ];

    protected $appends = [
        'profit_margin_percentage',
        'average_transaction_value',
        'average_quantity_per_transaction',
    ];

    // ============================================
    // RELATIONSHIPS
    // ============================================

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function bundle(): BelongsTo
    {
        return $this->belongsTo(ProductBundle::class, 'bundle_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    // ============================================
    // SCOPES
    // ============================================

    public function scopeForDate(Builder $query, Carbon|string $date): Builder
    {
        $dateString = $date instanceof Carbon ? $date->toDateString() : $date;
        return $query->where('aggregate_date', $dateString);
    }

    public function scopeForDateRange(Builder $query, Carbon|string $from, Carbon|string $to): Builder
    {
        $fromString = $from instanceof Carbon ? $from->toDateString() : $from;
        $toString = $to instanceof Carbon ? $to->toDateString() : $to;

        return $query->whereBetween('aggregate_date', [$fromString, $toString]);
    }

    public function scopeForStore(Builder $query, int $storeId): Builder
    {
        return $query->where('store_id', $storeId);
    }

    public function scopeForProduct(Builder $query, int $productId): Builder
    {
        return $query->where('product_id', $productId);
    }

    public function scopeForCategory(Builder $query, int $categoryId): Builder
    {
        return $query->where('category_id', $categoryId);
    }

    public function scopeForSellableType(Builder $query, string $type): Builder
    {
        return $query->where('sellable_type', $type);
    }

    public function scopeProducts(Builder $query): Builder
    {
        return $query->where('sellable_type', 'Product');
    }

    public function scopeVariants(Builder $query): Builder
    {
        return $query->where('sellable_type', 'ProductVariant');
    }

    public function scopeBundles(Builder $query): Builder
    {
        return $query->where('sellable_type', 'ProductBundle');
    }

    public function scopeWithDetails(Builder $query): Builder
    {
        return $query->with([
            'store:id,name,code',
            'product:id,name,sku,primary_image',
            'productVariant:id,product_id,variant_name,sku',
            'bundle:id,bundle_name,bundle_sku',
            'category:id,name,slug',
        ]);
    }

    public function scopeTopSelling(Builder $query, int $limit = 10): Builder
    {
        return $query->orderBy('total_quantity_sold', 'desc')
            ->limit($limit);
    }

    public function scopeTopRevenue(Builder $query, int $limit = 10): Builder
    {
        return $query->orderBy('total_revenue', 'desc')
            ->limit($limit);
    }

    public function scopeMostProfitable(Builder $query, int $limit = 10): Builder
    {
        return $query->orderBy('total_profit', 'desc')
            ->limit($limit);
    }

    // ============================================
    // ACCESSORS
    // ============================================

    public function getProfitMarginPercentageAttribute(): float
    {
        if ($this->total_revenue == 0) {
            return 0;
        }

        return round(($this->total_profit / $this->total_revenue) * 100, 2);
    }

    public function getAverageTransactionValueAttribute(): float
    {
        if ($this->transaction_count == 0) {
            return 0;
        }

        return round($this->total_revenue / $this->transaction_count, 2);
    }

    public function getAverageQuantityPerTransactionAttribute(): float
    {
        if ($this->transaction_count == 0) {
            return 0;
        }

        return round($this->total_quantity_sold / $this->transaction_count, 4);
    }

    public function getDiscountRateAttribute(): float
    {
        if ($this->total_revenue == 0) {
            return 0;
        }

        return round(($this->total_discount / $this->total_revenue) * 100, 2);
    }

    public function getDisplayNameAttribute(): string
    {
        return match ($this->sellable_type) {
            'ProductBundle' => $this->bundle?->bundle_name ?? 'Unknown Bundle',
            'ProductVariant' => ($this->product?->name ?? 'Unknown') . ' - ' . ($this->productVariant?->variant_name ?? 'Unknown Variant'),
            default => $this->product?->name ?? 'Unknown Product',
        };
    }

    // ============================================
    // HELPER METHODS
    // ============================================

    public function isProduct(): bool
    {
        return $this->sellable_type === 'Product';
    }

    public function isVariant(): bool
    {
        return $this->sellable_type === 'ProductVariant';
    }

    public function isBundle(): bool
    {
        return $this->sellable_type === 'ProductBundle';
    }

    public function hasProfit(): bool
    {
        return $this->total_profit > 0;
    }

    public function hasLoss(): bool
    {
        return $this->total_profit < 0;
    }

    public function getRevenueGrowth(Carbon|string $previousDate): ?float
    {
        $previousDateString = $previousDate instanceof Carbon ? $previousDate->toDateString() : $previousDate;

        $previousAggregate = self::where('aggregate_date', $previousDateString)
            ->where('store_id', $this->store_id)
            ->where('sellable_type', $this->sellable_type)
            ->where('product_id', $this->product_id)
            ->where('product_variant_id', $this->product_variant_id)
            ->where('bundle_id', $this->bundle_id)
            ->first();

        if (!$previousAggregate || $previousAggregate->total_revenue == 0) {
            return null;
        }

        $growth = (($this->total_revenue - $previousAggregate->total_revenue) / $previousAggregate->total_revenue) * 100;

        return round($growth, 2);
    }
}
