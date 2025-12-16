<?php

namespace App\Models\Tenant;

use App\Observers\Tenant\ProductBrandObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[ObservedBy([ProductBrandObserver::class])]
class ProductBrand extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'product_brands';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'logo_url',
        'is_active',
        'is_featured',
        'display_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'display_order' => 'integer',
    ];

    protected $attributes = [
        'is_active' => true,
        'is_featured' => false,
        'display_order' => 0,
    ];

    protected static function boot()
    {
        parent::boot();

        // Auto-generate slug from name if not provided
        static::creating(function ($brand) {
            if (empty($brand->slug)) {
                $brand->slug = Str::slug($brand->name);
            }
        });

        static::updating(function ($brand) {
            if ($brand->isDirty('name') && empty($brand->slug)) {
                $brand->slug = Str::slug($brand->name);
            }
        });
    }

    // Relationships

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'brand_id');
    }

    // Helper methods

    public function activeProducts(): HasMany
    {
        return $this->products()->where('is_active', true);
    }

    public function hasProducts(): bool
    {
        return $this->products()->exists();
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

    public function scopeOrdered($query)
    {
        return $query->orderBy('display_order')->orderBy('name');
    }

    // Attributes

    public function getProductCountAttribute(): int
    {
        return $this->products()->count();
    }
}
