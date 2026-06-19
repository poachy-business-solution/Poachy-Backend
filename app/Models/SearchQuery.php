<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SearchQuery extends Model
{
    protected $connection = 'central';

    protected $table = 'search_queries';

    public $timestamps = false;

    protected $fillable = [
        'customer_id',
        'session_id',
        'search_query',
        'results_count',
        'has_results',
        'filters_applied',
        'results_clicked',
        'products_added_to_cart',
        'converted_to_purchase',
        'parent_search_id',
        'searched_at',
    ];

    protected function casts(): array
    {
        return [
            'results_count'          => 'integer',
            'has_results'            => 'boolean',
            'filters_applied'        => 'array',
            'results_clicked'        => 'integer',
            'products_added_to_cart' => 'integer',
            'converted_to_purchase'  => 'boolean',
            'searched_at'            => 'datetime',
        ];
    }

    // =========================================================================
    // Relationships
    // =========================================================================

    public function customer(): BelongsTo
    {
        return $this->belongsTo(MarketplaceCustomer::class, 'customer_id');
    }

    public function parentSearch(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_search_id');
    }

    public function refinements(): HasMany
    {
        return $this->hasMany(self::class, 'parent_search_id');
    }

    // =========================================================================
    // Scopes
    // =========================================================================

    public function scopeWithoutResults(Builder $query): Builder
    {
        return $query->where('has_results', false);
    }

    public function scopePopular(Builder $query, int $limit = 10): Builder
    {
        return $query->selectRaw('search_query, COUNT(*) as search_count')
            ->groupBy('search_query')
            ->orderByDesc('search_count')
            ->limit($limit);
    }

    public function scopeConverted(Builder $query): Builder
    {
        return $query->where('converted_to_purchase', true);
    }

    public function scopeBySession(Builder $query, string $sessionId): Builder
    {
        return $query->where('session_id', $sessionId);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    public function markResultClicked(): void
    {
        $this->increment('results_clicked');
    }

    public function markProductAddedToCart(): void
    {
        $this->increment('products_added_to_cart');
    }

    public function markConvertedToPurchase(): void
    {
        $this->update(['converted_to_purchase' => true]);
    }
}
