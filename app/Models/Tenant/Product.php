<?php

namespace App\Models\Tenant;

use App\Enums\Tenant\ProductStatus;
use App\Enums\Tenant\ProductType;
use App\Observers\Tenant\ProductObserver;
use App\Traits\Tenant\HasAuditLogging;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy([ProductObserver::class])]
class Product extends Model
{
    use HasFactory, HasAuditLogging;

    protected $table = 'products';

    protected $fillable = [
        'uuid',
        'name',
        'slug',
        'description',
        'sku',
        'category_id',
        'brand_id',
        'supplier_id',
        'product_type', // simple or variable
        'stock_status', // in_stock, out_of_stock, discontinued
        'is_weighed',
        'requires_batch_tracking',
        'requires_serial_tracking',
        'base_selling_price',
        'tax_rate_id',
        'base_uom_id',
        'reorder_level',
        'shelf_life_days',
        'primary_image',
        'secondary_images',
        'is_active',
        'is_featured',
        'is_available_online',
        'online_price',
        'online_description',
        'notes',
    ];

    protected $casts = [
        'uuid' => 'string',
        'product_type' => ProductType::class,
        'stock_status' => ProductStatus::class,
        'is_weighed' => 'boolean',
        'requires_batch_tracking' => 'boolean',
        'requires_serial_tracking' => 'boolean',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'is_available_online' => 'boolean',
        'base_selling_price' => 'decimal:2',
        'online_price' => 'decimal:2',
        'reorder_level' => 'decimal:4',
        'secondary_images' => 'array',
        'shelf_life_days' => 'integer',
    ];

    protected $attributes = [
        'product_type' => ProductType::SIMPLE,
        'stock_status' => ProductStatus::IN_STOCK,
        'is_weighed' => false,
        'requires_batch_tracking' => false,
        'requires_serial_tracking' => false,
        'is_active' => true,
        'is_featured' => false,
        'is_available_online' => false,
    ];

    // Relationships

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(ProductBrand::class, 'brand_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class, 'tax_rate_id');
    }

    public function baseUom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'base_uom_id');
    }

    public function productUoms(): HasMany
    {
        return $this->hasMany(ProductUom::class, 'product_id');
    }

    public function activeProductUoms(): HasMany
    {
        return $this->productUoms()
            ->whereHas('uom', fn($q) => $q->where('is_active', true));
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class, 'product_id');
    }

    public function activeVariants(): HasMany
    {
        return $this->variants()->where('is_active', true);
    }

    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class);
    }

    public function inventoryForStore(int $storeId): ?Inventory
    {
        return $this->inventories()
            ->where('store_id', $storeId)
            ->whereNull('product_variant_id')
            ->first();
    }

    public function batches(): HasMany
    {
        return $this->hasMany(ProductBatch::class);
    }

    public function availableBatches(): HasMany
    {
        return $this->hasMany(ProductBatch::class)
            ->where('quantity_remaining_in_base_uom', '>', 0)
            ->where('is_expired', false)
            ->orderBy('purchase_order_id', 'asc')
            ->orderBy('expiry_date', 'asc');
    }

    public function purchaseOrderItems(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function transferItems(): HasMany
    {
        return $this->hasMany(StockTransferItem::class);
    }

    public function coupons(): BelongsToMany
    {
        return $this->belongsToMany(Coupon::class, 'coupon_products')
            ->withPivot('product_variant_id')
            ->withTimestamps();
    }


    // Scopes

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeAvailableOnline($query)
    {
        return $query->where('is_available_online', true);
    }

    public function scopeInStock($query)
    {
        return $query->where('stock_status', ProductStatus::IN_STOCK);
    }

    public function scopeByCategory($query, int $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    public function scopeByBrand($query, int $brandId)
    {
        return $query->where('brand_id', $brandId);
    }

    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('sku', 'like', "%{$term}%")
                ->orWhere('description', 'like', "%{$term}%");
        });
    }

    // Accessors & Mutators

    public function getFormattedBasePriceAttribute(): string
    {
        return 'KES ' . number_format($this->base_selling_price, 2);
    }

    public function getFormattedOnlinePriceAttribute(): ?string
    {
        return $this->online_price
            ? 'KES ' . number_format($this->online_price, 2)
            : null;
    }

    public function getImageCountAttribute(): int
    {
        return count($this->secondary_images ?? []);
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    // Helper Methods

    public function isActive(): bool
    {
        return $this->is_active === true;
    }

    public function isFeatured(): bool
    {
        return $this->is_featured === true;
    }

    public function isAvailableOnline(): bool
    {
        return $this->is_available_online === true;
    }

    public function isInStock(): bool
    {
        return $this->stock_status === ProductStatus::IN_STOCK;
    }

    public function requiresBatchTracking(): bool
    {
        return $this->requires_batch_tracking === true;
    }

    public function requiresSerialTracking(): bool
    {
        return $this->requires_serial_tracking === true;
    }

    public function getBaseProductUom(): ?ProductUom
    {
        return $this->productUoms()->where('is_base_uom', true)->first();
    }

    public function hasUomsConfigured(): bool
    {
        return $this->productUoms()->exists();
    }

    public function getPurchaseUoms()
    {
        return $this->productUoms()->where('is_purchase_uom', true)->get();
    }

    public function getSalesUoms()
    {
        return $this->productUoms()->where('is_sales_uom', true)->get();
    }

    public function hasVariants(): bool
    {
        return $this->variants()->exists();
    }

    public function getVariantCount(): int
    {
        return $this->variants()->count();
    }

    public function isVariable(): bool
    {
        return $this->product_type === ProductType::VARIABLE;
    }

    public function totalAvailableQuantity(): float
    {
        return $this->inventories()
            ->whereNull('product_variant_id')
            ->sum('quantity_available');
    }

    public function isAvailableInStore(int $storeId, float $quantity = 1): bool
    {
        $inventory = $this->inventoryForStore($storeId);
        return $inventory && $inventory->hasStock($quantity);
    }

    public function storesInStock()
    {
        return $this->inventories()
            ->with('store')
            ->where('quantity_available', '>', 0)
            ->get()
            ->pluck('store');
    }

    public function hasActiveCoupons(): bool
    {
        return $this->coupons()
            ->where('is_active', true)
            ->where('valid_from', '<=', now())
            ->where('valid_until', '>=', now())
            ->exists();
    }

    public function getActiveCoupons()
    {
        return $this->coupons()
            ->where('is_active', true)
            ->where('valid_from', '<=', now())
            ->where('valid_until', '>=', now())
            ->get();
    }
}
