<?php

namespace App\Models\Tenant;

use App\Enums\Tenant\SerialStatus;
use App\Observers\Tenant\ProductSerialObserver;
use App\Traits\Tenant\HasAuditLogging;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ObservedBy([ProductSerialObserver::class])]
class ProductSerial extends Model
{
    use HasAuditLogging, HasFactory, SoftDeletes;

    protected $fillable = [
        'store_id',
        'product_id',
        'product_variant_id',
        'purchase_order_id',
        'serial_number',
        'status',
        'cost',
        'supplier_id',
        'sale_item_id',
        'marketplace_sale_item_id',
        'notes',
    ];

    protected $casts = [
        'status' => SerialStatus::class,
        'cost' => 'decimal:2',
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

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(SaleItem::class);
    }

    public function marketplaceSaleItem(): BelongsTo
    {
        return $this->belongsTo(MarketplaceSaleItem::class);
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
        return $query->where('status', SerialStatus::AVAILABLE);
    }

    public function scopeFifoOrder($query)
    {
        return $query->orderBy('purchase_order_id', 'asc')
            ->orderBy('created_at', 'asc');
    }

    /**
     * ACCESSORS
     */
    public function getIsAvailableAttribute(): bool
    {
        return $this->status === SerialStatus::AVAILABLE;
    }

    public function getIsSoldAttribute(): bool
    {
        return $this->status === SerialStatus::SOLD;
    }
}
