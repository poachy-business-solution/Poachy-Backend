<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MarketplaceBrand extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'central';
    protected $table = 'marketplace_brands';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'logo_url',
        'is_featured',
        'is_active',
        'display_order',
    ];

    protected $casts = [
        'is_featured' => 'boolean',
        'is_active' => 'boolean',
        'display_order' => 'integer',
    ];

    // Relationships

    public function products(): HasMany
    {
        return $this->hasMany(MarketplaceProduct::class, 'marketplace_brand_id');
    }

    public function tenantMappings(): HasMany
    {
        return $this->hasMany(TenantBrandMapping::class, 'marketplace_brand_id');
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

    /**
     * Find best matching brand by slug
     * Returns array with [brand, confidence_score]
     */
    public static function findBestMatch(string $tenantSlug, string $tenantName): ?array
    {
        // Exact slug match (confidence: 100)
        $exactMatch = self::active()->where('slug', $tenantSlug)->first();
        if ($exactMatch) {
            return ['brand' => $exactMatch, 'confidence' => 100.0];
        }

        // Partial slug match (confidence: 80)
        $partialSlugMatch = self::active()
            ->where('slug', 'LIKE', "%{$tenantSlug}%")
            ->first();
        if ($partialSlugMatch) {
            return ['brand' => $partialSlugMatch, 'confidence' => 80.0];
        }

        // Name similarity (confidence: 60)
        $nameMatch = self::active()
            ->where('name', 'LIKE', "%{$tenantName}%")
            ->first();
        if ($nameMatch) {
            return ['brand' => $nameMatch, 'confidence' => 60.0];
        }

        return null;
    }
}
