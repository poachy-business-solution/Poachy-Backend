<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Wishlist extends Model
{
    protected $connection = 'central';

    protected $table = 'wishlists';

    protected $fillable = [
        'customer_id',
        'marketplace_product_id',
        'notes',
        'desired_quantity',
        'price_at_addition',
    ];

    protected function casts(): array
    {
        return [
            'desired_quantity' => 'integer',
            'price_at_addition' => 'float',
        ];
    }

    // =========================================================================
    // Relationships
    // =========================================================================

    public function customer(): BelongsTo
    {
        return $this->belongsTo(MarketplaceCustomer::class, 'customer_id');
    }

    public function marketplaceProduct(): BelongsTo
    {
        return $this->belongsTo(MarketplaceProduct::class, 'marketplace_product_id');
    }

    // =========================================================================
    // Scopes
    // =========================================================================

    public function scopeByCustomer(Builder $query, int $customerId): Builder
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeByProduct(Builder $query, int $productId): Builder
    {
        return $query->where('marketplace_product_id', $productId);
    }

    public function scopeWithAvailableProducts(Builder $query): Builder
    {
        return $query->whereHas('marketplaceProduct', function (Builder $q) {
            $q->where('is_active', true)->where('stock_status', 'in_stock');
        });
    }

    // =========================================================================
    // Helper Methods
    // =========================================================================

    public function isProductAvailable(): bool
    {
        if (! $this->relationLoaded('marketplaceProduct') || ! $this->marketplaceProduct) {
            return false;
        }

        return $this->marketplaceProduct->is_active
            && $this->marketplaceProduct->isInStock();
    }

    public function hasPriceChanged(): bool
    {
        if (! $this->price_at_addition || ! $this->marketplaceProduct) {
            return false;
        }

        return (float) $this->price_at_addition !== (float) $this->marketplaceProduct->online_price;
    }

    public function getPriceChange(): ?array
    {
        if (! $this->hasPriceChanged()) {
            return null;
        }

        $oldPrice = (float) $this->price_at_addition;
        $newPrice = (float) $this->marketplaceProduct->online_price;
        $difference = $newPrice - $oldPrice;

        return [
            'old_price' => $oldPrice,
            'current_price' => $newPrice,
            'difference' => $difference,
            'percentage_change' => $oldPrice > 0 ? (($difference / $oldPrice) * 100) : 0,
        ];
    }

    public function getCurrentPrice(): ?float
    {
        if (! $this->relationLoaded('marketplaceProduct') || ! $this->marketplaceProduct) {
            return null;
        }

        return (float) $this->marketplaceProduct->online_price;
    }
}
