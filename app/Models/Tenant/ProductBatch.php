<?php

namespace App\Models\Tenant;

use App\Observers\Tenant\ProductBatchObserver;
use App\Traits\Tenant\HasAuditLogging;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ObservedBy([ProductBatchObserver::class])]
class ProductBatch extends Model
{
    use HasFactory, SoftDeletes, HasAuditLogging;

    protected $fillable = [
        'store_id',
        'product_id',
        'product_variant_id',
        'purchase_order_id',
        'batch_number',
        'purchase_uom_id',
        'quantity_received_in_purchase_uom',
        'quantity_received_in_base_uom',
        'quantity_remaining_in_base_uom',
        'cost_per_purchase_uom',
        'cost_per_base_uom',
        'total_cost',
        'manufacture_date',
        'expiry_date',
        'is_expired',
        'supplier_id',
        'notes',
    ];

    protected $casts = [
        'quantity_received_in_purchase_uom' => 'decimal:4',
        'quantity_received_in_base_uom' => 'decimal:4',
        'quantity_remaining_in_base_uom' => 'decimal:4',
        'cost_per_purchase_uom' => 'decimal:2',
        'cost_per_base_uom' => 'decimal:2',
        'total_cost' => 'decimal:2',
        'manufacture_date' => 'date',
        'expiry_date' => 'date',
        'is_expired' => 'boolean',
    ];

    /**
     * RELATIONSHIPS
     */

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

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function purchaseUom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'purchase_uom_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * SCOPES
     */

    public function scopeByStore($query, int $storeId)
    {
        return $query->where('store_id', $storeId);
    }

    public function scopeByProduct($query, int $productId, ?int $variantId = null)
    {
        return $query->where('product_id', $productId)
            ->where('product_variant_id', $variantId);
    }

    public function scopeAvailable($query)
    {
        return $query->where('quantity_remaining_in_base_uom', '>', 0)
            ->where('is_expired', false);
    }

    public function scopeExpired($query)
    {
        return $query->where('is_expired', true);
    }

    public function scopeExpiringSoon($query, int $days = 30)
    {
        $thresholdDate = now()->addDays($days)->toDateString();

        return $query->where('is_expired', false)
            ->where('quantity_remaining_in_base_uom', '>', 0)
            ->whereNotNull('expiry_date')
            ->whereBetween('expiry_date', [now()->toDateString(), $thresholdDate]);
    }

    public function scopeFifoOrder($query)
    {
        return $query->orderBy('purchase_order_id', 'asc')
            ->orderBy('expiry_date', 'asc');
    }

    /**
     * ACCESSORS
     */

    public function getIsAvailableAttribute(): bool
    {
        return $this->quantity_remaining_in_base_uom > 0 && !$this->is_expired;
    }

    public function getIsDepletedAttribute(): bool
    {
        return $this->quantity_remaining_in_base_uom <= 0;
    }

    public function getPercentageRemainingAttribute(): float
    {
        if ($this->quantity_received_in_base_uom <= 0) {
            return 0;
        }

        return ($this->quantity_remaining_in_base_uom / $this->quantity_received_in_base_uom) * 100;
    }

    public function getQuantityDepletedAttribute(): float
    {
        return $this->quantity_received_in_base_uom - $this->quantity_remaining_in_base_uom;
    }

    public function getRemainingValueAttribute(): float
    {
        return $this->quantity_remaining_in_base_uom * $this->cost_per_base_uom;
    }

    public function getDaysUntilExpiryAttribute(): ?int
    {
        if (!$this->expiry_date) {
            return null;
        }

        return now()->diffInDays($this->expiry_date, false);
    }

    public function getIsExpiringSoonAttribute(): bool
    {
        $daysUntilExpiry = $this->days_until_expiry;

        return $daysUntilExpiry !== null && $daysUntilExpiry >= 0 && $daysUntilExpiry <= 30;
    }
}
