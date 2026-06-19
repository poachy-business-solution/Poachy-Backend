<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductBundleItem extends Model
{
    use HasFactory;

    protected $table = 'product_bundle_items';

    protected $fillable = [
        'bundle_id',
        'product_id',
        'product_variant_id',
        'uom_id',
        'quantity',
        'quantity_in_base_uom',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'quantity_in_base_uom' => 'decimal:4',
    ];

    // Relationships

    public function bundle(): BelongsTo
    {
        return $this->belongsTo(ProductBundle::class, 'bundle_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'uom_id');
    }

    // Helper Methods

    /**
     * Get display name of the item
     */
    public function getDisplayNameAttribute(): string
    {
        if ($this->variant) {
            return $this->variant->display_name;
        }

        return $this->product?->name ?? 'Unknown Product';
    }

    /**
     * Get the price of this item
     */
    public function getItemPriceAttribute(): float
    {
        if ($this->variant) {
            return $this->variant->computed_price;
        }

        return $this->product?->base_selling_price ?? 0;
    }

    /**
     * Get total price for this item (price × quantity)
     */
    public function getTotalPriceAttribute(): float
    {
        return round($this->item_price * $this->quantity, 2);
    }

    /**
     * Get UOM display
     */
    public function getUomDisplayAttribute(): string
    {
        return $this->uom ? "{$this->quantity} {$this->uom->code}" : '';
    }

    /**
     * Check if using variant
     */
    public function isUsingVariant(): bool
    {
        return $this->product_variant_id !== null;
    }

    /**
     * Get SKU
     */
    public function getSkuAttribute(): string
    {
        if ($this->variant) {
            return $this->variant->sku;
        }

        return $this->product?->sku ?? '';
    }
}
