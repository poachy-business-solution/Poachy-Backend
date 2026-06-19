<?php

namespace App\Models\Tenant;

use App\Observers\Tenant\InventoryObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

#[ObservedBy([InventoryObserver::class])]
class Inventory extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'inventory';

    protected $fillable = [
        'store_id',
        'product_id',
        'product_variant_id',
        'quantity_on_hand',
        'quantity_reserved',
        'quantity_available',
        'quantity_damaged',
        'last_restock_date',
        'last_stock_take_date',
        'last_restocked_by',
    ];

    protected $casts = [
        'quantity_on_hand' => 'decimal:4',
        'quantity_reserved' => 'decimal:4',
        'quantity_available' => 'decimal:4',
        'quantity_damaged' => 'decimal:4',
        'last_restock_date' => 'date',
        'last_stock_take_date' => 'date',
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

    public function lastRestocker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_restocked_by');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'product_id', 'product_id')
            ->where('store_id', $this->store_id)
            ->where('product_variant_id', $this->product_variant_id);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(InventoryReservation::class);
    }

    // ============================================
    // SCOPES (Only those used in services)
    // ============================================

    public function scopeByStore(Builder $query, int $storeId): Builder
    {
        return $query->where('store_id', $storeId);
    }

    public function scopeByProduct(Builder $query, int $productId): Builder
    {
        return $query->where('product_id', $productId);
    }

    public function scopeLowStock(Builder $query): Builder
    {
        return $query->whereHas('product', function ($q) {
            $q->whereColumn('inventory.quantity_available', '<=', 'products.reorder_level');
        });
    }

    public function scopeOutOfStock(Builder $query): Builder
    {
        return $query->where('quantity_available', '<=', 0);
    }

    public function scopeInStock(Builder $query): Builder
    {
        return $query->where('quantity_available', '>', 0);
    }

    public function scopeWithDetails(Builder $query): Builder
    {
        return $query->with([
            'product:id,name,slug,sku,base_selling_price,base_uom_id,reorder_level,primary_image,is_active',
            'product.baseUom:id,code,name,type',
            'product.category:id,name,slug',
            'product.brand:id,name,slug',
            'productVariant:id,product_id,variant_name,sku,variant_price,is_active',
            'store:id,name,code,is_main_store',
        ]);
    }

    // ============================================
    // ACCESSORS 
    // ============================================

    public function getIsLowStockAttribute(): bool
    {
        if (!$this->product) {
            return false;
        }

        $threshold = $this->product->reorder_level ?? 0;
        return $this->quantity_available <= $threshold && $this->quantity_available > 0;
    }

    public function getIsOutOfStockAttribute(): bool
    {
        return $this->quantity_available <= 0;
    }

    /**
     * Get stock status as string
     */
    public function getStockStatusAttribute(): string
    {
        if ($this->is_out_of_stock) {
            return 'out_of_stock';
        }

        if ($this->is_low_stock) {
            return 'low_stock';
        }

        return 'in_stock';
    }

    public function getDisplayNameAttribute(): string
    {
        if ($this->productVariant) {
            return "{$this->product->name} - {$this->productVariant->variant_name}";
        }

        return $this->product->name;
    }

    // ============================================
    // HELPER METHODS 
    // ============================================

    public function hasStock(float $quantity): bool
    {
        return $this->quantity_available >= $quantity;
    }

    public function canFulfill(float $quantity): bool
    {
        return $this->hasStock($quantity);
    }

    public function getEffectiveReorderLevel(): float
    {
        // Try to get from store_products first
        $storeProduct = StoreProduct::where('store_id', $this->store_id)
            ->where('product_id', $this->product_id)
            ->where('product_variant_id', $this->product_variant_id)
            ->first();

        if ($storeProduct && $storeProduct->min_stock_level > 0) {
            return $storeProduct->min_stock_level;
        }

        // Fall back to product reorder level
        return $this->product->reorder_level ?? 0;
    }

    public function recalculateAvailableQuantity(): void
    {
        $this->quantity_available = max(0, $this->quantity_on_hand - $this->quantity_reserved);
        $this->save();
    }

    public static function getForProduct(
        int $productId,
        int $storeId,
        ?int $variantId = null
    ): ?self {
        return self::where('store_id', $storeId)
            ->where('product_id', $productId)
            ->where('product_variant_id', $variantId)
            ->first();
    }

    public static function getOrCreate(
        int $productId,
        int $storeId,
        ?int $variantId = null
    ): self {
        return self::firstOrCreate(
            [
                'store_id' => $storeId,
                'product_id' => $productId,
                'product_variant_id' => $variantId,
            ],
            [
                'quantity_on_hand' => 0,
                'quantity_reserved' => 0,
                'quantity_available' => 0,
                'quantity_damaged' => 0,
            ]
        );
    }
}
