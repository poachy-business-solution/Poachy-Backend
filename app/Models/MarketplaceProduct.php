<?php

namespace App\Models;

use App\Observers\Central\MarketplaceProductObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ObservedBy([MarketplaceProductObserver::class])]
class MarketplaceProduct extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'central';
    protected $table = 'marketplace_products';

    protected $fillable = [
        'tenant_id',
        'tenant_product_id',
        'tenant_product_type',
        'tenant_variant_id',
        'tenant_bundle_id',
        'name',
        'slug',
        'description',
        'online_description',
        'sku',
        'tenant_category_id',
        'tenant_category_name',
        'tenant_brand_id',
        'tenant_brand_name',
        'marketplace_category_id',
        'marketplace_brand_id',
        'online_price',
        'base_uom_code',
        'base_uom_name',
        'tax_rate',
        'available_quantity',
        'stock_status',
        'primary_image',
        'secondary_images',
        'view_count',
        'order_count',
        'average_rating',
        'rating_count',
        'is_active',
        'is_featured',
        'display_priority',
        'last_synced_at',
        'sync_status',
    ];

    protected $casts = [
        'tenant_product_id' => 'integer',
        'tenant_variant_id' => 'integer',
        'tenant_bundle_id' => 'integer',
        'tenant_category_id' => 'integer',
        'tenant_brand_id' => 'integer',
        'marketplace_category_id' => 'integer',
        'marketplace_brand_id' => 'integer',
        'online_price' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'available_quantity' => 'decimal:4',
        'view_count' => 'integer',
        'order_count' => 'integer',
        'average_rating' => 'decimal:2',
        'rating_count' => 'integer',
        'display_priority' => 'integer',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'secondary_images' => 'array',
        'last_synced_at' => 'datetime',
    ];

    // Relationships

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function marketplaceCategory(): BelongsTo
    {
        return $this->belongsTo(MarketplaceCategory::class, 'marketplace_category_id');
    }

    public function marketplaceBrand(): BelongsTo
    {
        return $this->belongsTo(MarketplaceBrand::class, 'marketplace_brand_id');
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(ShoppingCartItem::class, 'marketplace_product_id');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(MarketplaceOrderItem::class, 'marketplace_product_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class, 'marketplace_product_id');
    }

    public function approvedReviews(): HasMany
    {
        return $this->hasMany(ProductReview::class, 'marketplace_product_id')
            ->where('status', \App\Enums\Central\ReviewStatus::Approved);
    }

    public function wishlistItems(): HasMany
    {
        return $this->hasMany(Wishlist::class, 'marketplace_product_id');
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

    public function scopeInStock($query)
    {
        return $query->where('stock_status', 'in_stock');
    }

    public function scopeByTenant($query, string $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeByCategory($query, int $categoryId)
    {
        return $query->where('marketplace_category_id', $categoryId);
    }

    public function scopeByBrand($query, int $brandId)
    {
        return $query->where('marketplace_brand_id', $brandId);
    }

    // Helper Methods

    public function isInStock(): bool
    {
        return $this->stock_status === 'in_stock' && $this->available_quantity > 0;
    }

    public function incrementViewCount(): void
    {
        $this->increment('view_count');
    }

    public function incrementOrderCount(): void
    {
        $this->increment('order_count');
    }

    public function updateRating(float $newRating): void
    {
        $totalRatings = $this->rating_count;
        $currentTotal = $this->average_rating * $totalRatings;
        $newTotal = $currentTotal + $newRating;
        $newCount = $totalRatings + 1;

        $this->update([
            'average_rating' => round($newTotal / $newCount, 2),
            'rating_count' => $newCount,
        ]);
    }
}
