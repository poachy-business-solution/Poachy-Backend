<?php

namespace App\Models\Tenant;

use App\Enums\Tenant\ProductStatus;
use App\Observers\Tenant\ProductVariantObserver;
use App\Traits\Tenant\HasAuditLogging;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ObservedBy([ProductVariantObserver::class])]
class ProductVariant extends Model
{
    use HasFactory, HasAuditLogging;

    protected $table = 'product_variants';

    protected $fillable = [
        'uuid',
        'product_id',
        'variant_name',
        'sku',
        'attributes',
        'uom_id',
        'uom_quantity',
        'quantity_in_base_uom',
        'base_selling_price_adjustment',
        'variant_price',
        'online_price',
        'stock_status',
        'reorder_level',
        'shelf_life_days',
        'is_active',
    ];

    protected $casts = [
        'attributes' => 'array',
        'uom_quantity' => 'decimal:4',
        'quantity_in_base_uom' => 'decimal:4',
        'base_selling_price_adjustment' => 'decimal:2',
        'variant_price' => 'decimal:2',
        'online_price' => 'decimal:2',
        'stock_status' => ProductStatus::class,
        'reorder_level' => 'decimal:4',
        'shelf_life_days' => 'integer',
        'is_active' => 'boolean',
    ];

    protected $attributes = [
        'base_selling_price_adjustment' => 0,
        'stock_status' => ProductStatus::IN_STOCK,
        'reorder_level' => 0,
        'is_active' => true,
    ];

    // Relationships

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'uom_id');
    }

    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class, 'product_variant_id');
    }

    public function batches(): HasMany
    {
        return $this->hasMany(ProductBatch::class, 'product_variant_id');
    }

    public function availableBatches(): HasMany
    {
        return $this->hasMany(ProductBatch::class, 'product_variant_id')
            ->where('quantity_remaining_in_base_uom', '>', 0)
            ->where('is_expired', false)
            ->orderBy('purchase_order_id', 'asc')
            ->orderBy('expiry_date', 'asc');
    }

    // Scopes

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInStock($query)
    {
        return $query->where('stock_status', ProductStatus::IN_STOCK);
    }

    public function scopeOutOfStock($query)
    {
        return $query->where('stock_status', ProductStatus::OUT_OF_STOCK);
    }

    public function scopeForProduct($query, int $productId)
    {
        return $query->where('product_id', $productId);
    }

    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('variant_name', 'like', "%{$term}%")
                ->orWhere('sku', 'like', "%{$term}%")
                ->orWhereRaw("JSON_SEARCH(attributes, 'one', ?) IS NOT NULL", ["%{$term}%"]);
        });
    }

    public function scopeByAttribute($query, string $key, string $value)
    {
        return $query->whereRaw("JSON_EXTRACT(attributes, '$.{$key}') = ?", [$value]);
    }

    public function scopeAvailableOnline($query)
    {
        return $query->where('is_active', true)
            ->whereNotNull('online_price')
            ->where('stock_status', ProductStatus::IN_STOCK);
    }

    // Accessors & Mutators

    public function getFormattedVariantPriceAttribute(): string
    {
        return 'KES ' . number_format($this->variant_price ?? 0, 2);
    }

    public function getFormattedOnlinePriceAttribute(): string
    {
        return 'KES ' . number_format($this->online_price ?? 0, 2);
    }

    public function getFormattedAdjustmentAttribute(): string
    {
        $adjustment = $this->base_selling_price_adjustment;

        if ($adjustment > 0) {
            return '+KES ' . number_format($adjustment, 2);
        } elseif ($adjustment < 0) {
            return '-KES ' . number_format(abs($adjustment), 2);
        }

        return 'KES 0.00';
    }

    public function getDisplayNameAttribute(): string
    {
        $product = $this->product;
        return $product ? "{$product->name} - {$this->variant_name}" : $this->variant_name;
    }

    public function getFullSkuAttribute(): string
    {
        return $this->sku;
    }

    /**
     * Get computed variant price
     * Uses variant_price if set, otherwise calculates from base + adjustment
     */
    public function getComputedPriceAttribute(): float
    {
        if ($this->variant_price !== null) {
            return $this->variant_price;
        }

        $basePrice = $this->product?->base_selling_price ?? 0;
        return $basePrice + $this->base_selling_price_adjustment;
    }

    public function getComputedOnlinePriceAttribute(): float
    {
        // Priority 1: Explicit online_price
        if ($this->online_price !== null) {
            return $this->online_price;
        }

        // Priority 2: Regular variant_price
        if ($this->variant_price !== null) {
            return $this->variant_price;
        }

        // Priority 3: Product's online_price (if available)
        if ($this->product?->online_price !== null) {
            return $this->product->online_price + $this->base_selling_price_adjustment;
        }

        // Priority 4: Base price + adjustment
        $basePrice = $this->product?->base_selling_price ?? 0;
        return $basePrice + $this->base_selling_price_adjustment;
    }

    // Helper Methods

    public function isActive(): bool
    {
        return $this->is_active === true;
    }

    public function isInStock(): bool
    {
        return $this->stock_status === ProductStatus::IN_STOCK;
    }

    public function isOutOfStock(): bool
    {
        return $this->stock_status === ProductStatus::OUT_OF_STOCK;
    }

    public function isDiscontinued(): bool
    {
        return $this->stock_status === ProductStatus::DISCONTINUED;
    }

    public function isAvailableOnline(): bool
    {
        return $this->is_active
            && $this->online_price !== null
            && $this->isInStock();
    }

    /**
     * Get formatted UOM display
     */
    public function getUomDisplay(): string
    {
        if (!$this->uom) {
            return '';
        }

        return "{$this->uom_quantity} {$this->uom->code}";
    }

    // /**
    //  * Get attribute value by key
    //  */
    // public function getAttribute(string $key): ?string
    // {
    //     $attributes = $this->attributes ?? [];
    //     return $attributes[$key] ?? null;
    // }

    // /**
    //  * Check if variant has specific attribute
    //  */
    // public function hasAttribute(string $key): bool
    // {
    //     $attributes = $this->attributes ?? [];
    //     return isset($attributes[$key]);
    // }

    /**
     * Get all attribute keys
     */
    public function getAttributeKeys(): array
    {
        $attributes = $this->attributes ?? [];
        return array_keys($attributes);
    }

    /**
     * Check if variant needs reordering
     */
    public function needsReorder(float $currentStock): bool
    {
        return $currentStock <= $this->reorder_level;
    }

    /**
     * Calculate base UOM equivalent for a given quantity
     */
    public function convertToBaseUom(float $quantity): float
    {
        return $quantity * $this->quantity_in_base_uom;
    }

    /**
     * Calculate variant quantity from base UOM
     */
    public function convertFromBaseUom(float $baseQuantity): float
    {
        if ($this->quantity_in_base_uom == 0) {
            throw new \RuntimeException('Cannot convert: quantity_in_base_uom is 0');
        }

        return $baseQuantity / $this->quantity_in_base_uom;
    }

    public function inventoryForStore(int $storeId): ?Inventory
    {
        return $this->inventories()
            ->where('store_id', $storeId)
            ->first();
    }

    public function totalAvailableQuantity(): float
    {
        return $this->inventories()->sum('quantity_available');
    }

    public function isAvailableInStore(int $storeId, float $quantity = 1): bool
    {
        $inventory = $this->inventoryForStore($storeId);
        return $inventory && $inventory->hasStock($quantity);
    }

    public function getPriceDifferenceAttribute(): float
    {
        if ($this->online_price === null) {
            return 0;
        }

        $offlinePrice = $this->computed_price;
        return $this->online_price - $offlinePrice;
    }

    public function getFormattedPriceDifferenceAttribute(): string
    {
        $diff = $this->price_difference;

        if ($diff > 0) {
            return '+KES ' . number_format($diff, 2) . ' online';
        } elseif ($diff < 0) {
            return '-KES ' . number_format(abs($diff), 2) . ' online';
        }

        return 'Same price';
    }
}
