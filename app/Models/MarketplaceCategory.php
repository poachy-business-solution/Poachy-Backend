<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketplaceCategory extends Model
{
    use HasFactory;

    protected $connection = 'central';
    protected $table = 'marketplace_categories';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'icon',
        'banner_image',
        'parent_id',
        'display_order',
        'is_featured',
        'is_active',
        'meta_title',
        'meta_description',
    ];

    protected $casts = [
        'parent_id' => 'integer',
        'display_order' => 'integer',
        'is_featured' => 'boolean',
        'is_active' => 'boolean',
    ];

    // Relationships

    public function parent(): BelongsTo
    {
        return $this->belongsTo(MarketplaceCategory::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(MarketplaceCategory::class, 'parent_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(MarketplaceProduct::class, 'marketplace_category_id');
    }

    public function tenantMappings(): HasMany
    {
        return $this->hasMany(TenantCategoryMapping::class, 'marketplace_category_id');
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

    public function scopeParents($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Find best matching category by slug
     * Returns array with [category, confidence_score]
     */
    public static function findBestMatch(string $tenantSlug, string $tenantName): ?array
    {
        // Exact slug match (confidence: 100)
        $exactMatch = self::active()->where('slug', $tenantSlug)->first();
        if ($exactMatch) {
            return ['category' => $exactMatch, 'confidence' => 100.0];
        }

        // Partial slug match (confidence: 80)
        $partialSlugMatch = self::active()
            ->where('slug', 'LIKE', "%{$tenantSlug}%")
            ->first();
        if ($partialSlugMatch) {
            return ['category' => $partialSlugMatch, 'confidence' => 80.0];
        }

        // Name similarity (confidence: 60)
        $nameMatch = self::active()
            ->where('name', 'LIKE', "%{$tenantName}%")
            ->first();
        if ($nameMatch) {
            return ['category' => $nameMatch, 'confidence' => 60.0];
        }

        return null;
    }
}
