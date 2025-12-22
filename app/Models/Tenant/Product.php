<?php

namespace App\Models\Tenant;

use App\Enums\Tenant\ProductStatus;
use App\Enums\Tenant\ProductType;
use App\Observers\Tenant\ProductObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy([ProductObserver::class])]
class Product extends Model
{
    use HasFactory;

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
}
