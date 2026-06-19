<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductPageView extends Model
{
    protected $connection = 'central';

    protected $table = 'product_page_views';

    public $timestamps = false;

    protected $fillable = [
        'marketplace_product_id',
        'customer_id',
        'session_id',
        'referrer_source',
        'referrer_url',
        'search_query',
        'time_spent_seconds',
        'scrolled_to_description',
        'scrolled_to_reviews',
        'clicked_images',
        'added_to_cart',
        'added_to_wishlist',
        'device_type',
        'browser',
        'viewed_at',
    ];

    protected function casts(): array
    {
        return [
            'time_spent_seconds'     => 'integer',
            'scrolled_to_description' => 'boolean',
            'scrolled_to_reviews'    => 'boolean',
            'clicked_images'         => 'boolean',
            'added_to_cart'          => 'boolean',
            'added_to_wishlist'      => 'boolean',
            'viewed_at'              => 'datetime',
        ];
    }

    // =========================================================================
    // Relationships
    // =========================================================================

    public function marketplaceProduct(): BelongsTo
    {
        return $this->belongsTo(MarketplaceProduct::class, 'marketplace_product_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(MarketplaceCustomer::class, 'customer_id');
    }

    // =========================================================================
    // Scopes
    // =========================================================================

    public function scopeByProduct(Builder $query, int $productId): Builder
    {
        return $query->where('marketplace_product_id', $productId);
    }

    public function scopeWithConversion(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->where('added_to_cart', true)
              ->orWhere('added_to_wishlist', true);
        });
    }

    public function scopeBySession(Builder $query, string $sessionId): Builder
    {
        return $query->where('session_id', $sessionId);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    public function markScrolledToDescription(): void
    {
        $this->update(['scrolled_to_description' => true]);
    }

    public function markScrolledToReviews(): void
    {
        $this->update(['scrolled_to_reviews' => true]);
    }

    public function markClickedImages(): void
    {
        $this->update(['clicked_images' => true]);
    }

    public function markAddedToCart(): void
    {
        $this->update(['added_to_cart' => true]);
    }

    public function markAddedToWishlist(): void
    {
        $this->update(['added_to_wishlist' => true]);
    }

    public function updateTimeSpent(int $seconds): void
    {
        $this->update(['time_spent_seconds' => $seconds]);
    }
}
