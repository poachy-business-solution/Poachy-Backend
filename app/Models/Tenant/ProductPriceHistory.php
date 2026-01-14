<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class ProductPriceHistory extends Model
{
    protected $table = 'product_price_history';

    public $timestamps = false;

    protected $fillable = [
        'product_id',
        'base_uom_id',
        'product_variant_id',
        'old_selling_price',
        'new_selling_price',
        'change_reason',
        'changed_by',
        'effective_from',
    ];

    protected $casts = [
        'old_selling_price' => 'decimal:2',
        'new_selling_price' => 'decimal:2',
        'effective_from' => 'datetime',
        'created_at' => 'datetime',
    ];

    protected $attributes = [
        'change_reason' => 'manual',
    ];

    // ============================================
    // RELATIONSHIPS
    // ============================================

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function baseUom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'base_uom_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    // ============================================
    // SCOPES
    // ============================================

    public function scopeByProduct(Builder $query, int $productId): Builder
    {
        return $query->where('product_id', $productId);
    }

    public function scopeByVariant(Builder $query, ?int $variantId): Builder
    {
        return $query->where('product_variant_id', $variantId);
    }

    public function scopeRecent(Builder $query): Builder
    {
        return $query->orderBy('created_at', 'desc');
    }

    public function scopeBetweenDates(Builder $query, ?string $from = null, ?string $to = null): Builder
    {
        if ($from) {
            $query->where('created_at', '>=', $from);
        }

        if ($to) {
            $query->where('created_at', '<=', $to . ' 23:59:59');
        }

        return $query;
    }

    public function scopeChangedBy(Builder $query, int $userId): Builder
    {
        return $query->where('changed_by', $userId);
    }

    public function scopePriceIncreases(Builder $query): Builder
    {
        return $query->whereRaw('new_selling_price > COALESCE(old_selling_price, 0)');
    }

    public function scopePriceDecreases(Builder $query): Builder
    {
        return $query->whereRaw('new_selling_price < old_selling_price');
    }

    public function scopeWithFullDetails(Builder $query): Builder
    {
        return $query->with([
            'product:id,name,sku,base_selling_price',
            'productVariant:id,product_id,variant_name,sku,variant_price',
            'baseUom:id,code,name',
            'changedBy:id,name,email',
        ]);
    }

    // ============================================
    // ACCESSORS
    // ============================================

    public function getPriceChangeAmountAttribute(): ?float
    {
        if ($this->old_selling_price === null) {
            return null;
        }

        return round($this->new_selling_price - $this->old_selling_price, 2);
    }

    public function getPriceChangePercentageAttribute(): ?float
    {
        if ($this->old_selling_price === null || $this->old_selling_price == 0) {
            return null;
        }

        return round((($this->new_selling_price - $this->old_selling_price) / $this->old_selling_price) * 100, 2);
    }

    public function getDisplayNameAttribute(): string
    {
        if ($this->productVariant) {
            return "{$this->product->name} - {$this->productVariant->variant_name}";
        }

        return $this->product->name;
    }
}
