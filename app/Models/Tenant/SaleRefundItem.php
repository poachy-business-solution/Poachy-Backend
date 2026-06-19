<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class SaleRefundItem extends Model
{
    use HasFactory;

    protected $table = 'sale_refund_items';

    protected $fillable = [
        'refund_id',
        'sale_item_id',
        'product_id',
        'quantity_refunded',
        'quantity_refunded_in_base_uom',
        'refund_amount',
    ];

    protected $casts = [
        'quantity_refunded' => 'decimal:4',
        'quantity_refunded_in_base_uom' => 'decimal:4',
        'refund_amount' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // ============================================
    // RELATIONSHIPS
    // ============================================

    /**
     * Parent refund
     */
    public function refund(): BelongsTo
    {
        return $this->belongsTo(SaleRefund::class, 'refund_id');
    }

    /**
     * Original sale item being refunded
     */
    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(SaleItem::class);
    }

    /**
     * Product being refunded
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    // ============================================
    // ACCESSORS
    // ============================================

    /**
     * Get formatted refund amount
     */
    public function getFormattedAmountAttribute(): string
    {
        return 'KES ' . number_format($this->refund_amount, 2);
    }

    /**
     * Get product name
     */
    public function getProductNameAttribute(): ?string
    {
        return $this->product?->name;
    }

    /**
     * Get original sale item quantity
     */
    public function getOriginalQuantityAttribute(): ?float
    {
        return $this->saleItem?->quantity;
    }

    /**
     * Get original sale item price
     */
    public function getOriginalPriceAttribute(): ?float
    {
        return $this->saleItem?->unit_price;
    }

    /**
     * Get unit refund price
     */
    public function getUnitRefundPriceAttribute(): float
    {
        if ($this->quantity_refunded <= 0) {
            return 0;
        }

        return round($this->refund_amount / $this->quantity_refunded, 2);
    }

    /**
     * Check if full quantity was refunded
     */
    public function getIsFullRefundAttribute(): bool
    {
        if (!$this->saleItem) {
            return false;
        }

        return $this->quantity_refunded >= $this->saleItem->quantity;
    }

    /**
     * Get remaining quantity after refund
     */
    public function getRemainingQuantityAttribute(): ?float
    {
        if (!$this->saleItem) {
            return null;
        }

        return max(0, $this->saleItem->quantity - $this->quantity_refunded);
    }

    // ============================================
    // SCOPES
    // ============================================

    /**
     * Scope to filter by refund
     */
    public function scopeByRefund(Builder $query, int $refundId): Builder
    {
        return $query->where('refund_id', $refundId);
    }

    /**
     * Scope to filter by product
     */
    public function scopeByProduct(Builder $query, int $productId): Builder
    {
        return $query->where('product_id', $productId);
    }

    /**
     * Scope to filter by sale item
     */
    public function scopeBySaleItem(Builder $query, int $saleItemId): Builder
    {
        return $query->where('sale_item_id', $saleItemId);
    }

    /**
     * Scope to get full refunds only
     */
    public function scopeFullRefunds(Builder $query): Builder
    {
        return $query->whereHas('saleItem', function ($q) {
            $q->whereColumn('sale_items.quantity', '=', 'sale_refund_items.quantity_refunded');
        });
    }

    /**
     * Scope to get partial refunds only
     */
    public function scopePartialRefunds(Builder $query): Builder
    {
        return $query->whereHas('saleItem', function ($q) {
            $q->whereColumn('sale_items.quantity', '>', 'sale_refund_items.quantity_refunded');
        });
    }

    // ============================================
    // STATIC METHODS
    // ============================================

    /**
     * Get total refunded quantity for a sale item
     */
    public static function getTotalRefundedForSaleItem(int $saleItemId): float
    {
        return (float) self::where('sale_item_id', $saleItemId)
            ->sum('quantity_refunded');
    }

    /**
     * Get total refunded amount for a sale item
     */
    public static function getTotalAmountRefundedForSaleItem(int $saleItemId): float
    {
        return (float) self::where('sale_item_id', $saleItemId)
            ->sum('refund_amount');
    }

    /**
     * Check if sale item has been fully refunded
     */
    public static function isSaleItemFullyRefunded(int $saleItemId): bool
    {
        $saleItem = SaleItem::find($saleItemId);

        if (!$saleItem) {
            return false;
        }

        $totalRefunded = self::getTotalRefundedForSaleItem($saleItemId);

        return $totalRefunded >= $saleItem->quantity;
    }

    /**
     * Get remaining refundable quantity for sale item
     */
    public static function getRemainingRefundableQuantity(int $saleItemId): float
    {
        $saleItem = SaleItem::find($saleItemId);

        if (!$saleItem) {
            return 0;
        }

        $totalRefunded = self::getTotalRefundedForSaleItem($saleItemId);

        return max(0, $saleItem->quantity - $totalRefunded);
    }

    /**
     * Get product refund statistics
     */
    public static function getProductStatistics(int $productId, ?string $startDate = null, ?string $endDate = null): array
    {
        $query = self::where('product_id', $productId);

        if ($startDate && $endDate) {
            $query->whereHas('refund', function ($q) use ($startDate, $endDate) {
                $q->byDateRange($startDate, $endDate);
            });
        }

        $items = $query->get();

        return [
            'total_quantity_refunded' => $items->sum('quantity_refunded'),
            'total_amount_refunded' => $items->sum('refund_amount'),
            'refund_count' => $items->count(),
            'average_refund_amount' => $items->avg('refund_amount'),
        ];
    }
}
