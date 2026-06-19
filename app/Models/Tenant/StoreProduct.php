<?php

namespace App\Models\Tenant;

use App\Observers\Tenant\StoreProductObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

#[ObservedBy(StoreProductObserver::class)]
class StoreProduct extends Model
{
    use HasFactory;

    protected $table = 'store_products';

    protected $fillable = [
        'store_id',
        'product_id',
        'product_variant_id',
        'store_selling_price',
        'is_available',
        'min_stock_level',
    ];

    protected $casts = [
        'product_variant_id' => 'integer',
        'store_selling_price' => 'decimal:2',
        'is_available' => 'boolean',
        'min_stock_level' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * ========================================
     * RELATIONSHIPS
     * ========================================
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

    /**
     * ========================================
     * COMPUTED ATTRIBUTES
     * ========================================
     */

    public function getEffectiveSellingPriceAttribute(): float
    {
        // Priority: store_selling_price > variant_price > base_selling_price
        if ($this->store_selling_price !== null) {
            return $this->store_selling_price;
        }

        // If this is a variant assignment, use variant price
        if ($this->product_variant_id && $this->productVariant) {
            return $this->productVariant->variant_price ?? $this->product->base_selling_price;
        }

        // Fall back to product base price
        return $this->product->base_selling_price;
    }


    public function getEffectiveMinStockLevelAttribute(): int
    {
        return $this->min_stock_level > 0
            ? $this->min_stock_level
            : (int) $this->product->reorder_level;
    }

    public function getIsPriceOverriddenAttribute(): bool
    {
        return $this->store_selling_price !== null;
    }

    public function getIsStockLevelOverriddenAttribute(): bool
    {
        return $this->min_stock_level > 0;
    }

    /**
     * Get current inventory for this product at this store
     */
    // public function getCurrentInventoryAttribute(): ?Inventory
    // {
    //     return $this->product->inventories()
    //         ->where('store_id', $this->store_id)
    //         ->first();
    // }

    /**
     * Get available quantity at this store
     */
    // public function getAvailableQuantityAttribute(): float
    // {
    //     $inventory = $this->current_inventory;
    //     return $inventory ? $inventory->quantity_available : 0;
    // }

    public function getIsLowStockAttribute(): bool
    {
        return $this->available_quantity < $this->effective_min_stock_level;
    }

    public function getIsOutOfStockAttribute(): bool
    {
        return $this->available_quantity <= 0;
    }

    public function getComputedStockStatusAttribute(): string
    {
        if ($this->is_out_of_stock) {
            return 'out_of_stock';
        }

        if ($this->is_low_stock) {
            return 'low_stock';
        }

        return 'in_stock';
    }

    /**
     * ========================================
     * SCOPES
     * ========================================
     */

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('is_available', true);
    }

    public function scopeUnavailable(Builder $query): Builder
    {
        return $query->where('is_available', false);
    }

    public function scopeWithPriceOverride(Builder $query): Builder
    {
        return $query->whereNotNull('store_selling_price');
    }

    public function scopeWithoutPriceOverride(Builder $query): Builder
    {
        return $query->whereNull('store_selling_price');
    }

    public function scopeForStore(Builder $query, int $storeId): Builder
    {
        return $query->where('store_id', $storeId);
    }

    public function scopeForProduct(Builder $query, int $productId): Builder
    {
        return $query->where('product_id', $productId);
    }

    public function scopeWithDetails(Builder $query): Builder
    {
        return $query->with([
            'product' => function ($query) {
                $query->select([
                    'id',
                    'name',
                    'slug',
                    'sku',
                    'description',
                    'category_id',
                    'brand_id',
                    'product_type',
                    'base_selling_price',
                    'base_uom_id',
                    'primary_image',
                    'is_active',
                    'is_available_online',
                    'reorder_level',
                ])->with([
                    'category:id,name,slug',
                    'brand:id,name,slug,logo_url',
                    'baseUom:id,code,name,type',
                ]);
            },
            'productVariant' => function ($query) {
                $query->select([
                    'id',
                    'product_id',
                    'variant_name',
                    'sku',
                    'attributes',
                    'uom_quantity',
                    'quantity_in_base_uom',
                    'variant_price',
                    'is_active',
                ]);
            },
            'store:id,name,code,is_main_store',
        ]);
    }

    /**
     * Scope to eager load inventory
     */
    // public function scopeWithInventory(Builder $query): Builder
    // {
    //     return $query->with(['product.inventories' => function ($query) {
    //         $query->select([
    //             'id',
    //             'store_id',
    //             'product_id',
    //             'product_variant_id',
    //             'quantity_on_hand',
    //             'quantity_reserved',
    //             'quantity_available',
    //             'quantity_damaged',
    //             'last_restock_date',
    //         ]);
    //     }]);
    // }

    /**
     * ========================================
     * HELPER METHODS
     * ========================================
     */

    public function hasVariants(): bool
    {
        return $this->product->product_type === 'variable';
    }

    public function isBaseProduct(): bool
    {
        return $this->product_variant_id === null;
    }

    public function isVariant(): bool
    {
        return $this->product_variant_id !== null;
    }

    public function getVariants()
    {
        if (!$this->hasVariants() || !$this->isBaseProduct()) {
            return collect();
        }

        return $this->product->variants()
            ->where('is_active', true)
            ->get();
    }

    public function getDisplayNameAttribute(): string
    {
        if ($this->isVariant() && $this->productVariant) {
            return $this->product->name . ' - ' . $this->productVariant->variant_name;
        }

        return $this->product->name;
    }

    public function toggleAvailability(): bool
    {
        $this->is_available = !$this->is_available;
        return $this->save();
    }

    public function setPriceOverride(?float $price): bool
    {
        $this->store_selling_price = $price;
        return $this->save();
    }

    public function removePriceOverride(): bool
    {
        return $this->setPriceOverride(null);
    }

    public function setMinStockLevel(int $level): bool
    {
        $this->min_stock_level = $level;
        return $this->save();
    }

    public function removeMinStockLevelOverride(): bool
    {
        return $this->setMinStockLevel(0);
    }
}
