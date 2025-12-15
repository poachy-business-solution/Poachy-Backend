<?php

namespace App\Models\Tenant;

use App\Observers\Tenant\ProductCategoryObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[ObservedBy([ProductCategoryObserver::class])]
class ProductCategory extends Model
{
    use HasFactory;

    protected $table = 'product_categories';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'parent_id',
        'display_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'display_order' => 'integer',
        'parent_id' => 'integer',
    ];

    protected $attributes = [
        'is_active' => true,
        'display_order' => 0,
    ];

    protected static function boot()
    {
        parent::boot();

        // Auto-generate slug from name if not provided
        static::creating(function ($category) {
            if (empty($category->slug)) {
                $category->slug = Str::slug($category->name);
            }
        });

        static::updating(function ($category) {
            if ($category->isDirty('name') && empty($category->slug)) {
                $category->slug = Str::slug($category->name);
            }
        });
    }

    // Relationships

    public function parent(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(ProductCategory::class, 'parent_id')
            ->orderBy('display_order');
    }

    public function activeChildren(): HasMany
    {
        return $this->children()->where('is_active', true);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'category_id');
    }

    // Helper methods

    public function activeProducts(): HasMany
    {
        return $this->products()->where('is_active', true);
    }

    public function isRoot(): bool
    {
        return is_null($this->parent_id);
    }

    public function hasProducts(): bool
    {
        return $this->products()->exists();
    }

    public function hasChildren(): bool
    {
        return $this->children()->exists();
    }

    // Scopes

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeRootCategories($query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopeWithChildren($query)
    {
        return $query->with(['children' => function ($query) {
            $query->orderBy('display_order');
        }]);
    }

    public function scopeOrdered($query)
    {
        return $query
            ->orderByRaw('COALESCE(parent_id, id)')
            ->orderByRaw('parent_id IS NOT NULL')
            ->orderBy('display_order')
            ->orderBy('name');
    }



    // Attributes

    public function getProductCountAttribute(): int
    {
        return $this->products()->count();
    }

    public function getFullPath(): array
    {
        $path = [$this];
        $category = $this;

        while ($category->parent) {
            $category = $category->parent;
            array_unshift($path, $category);
        }

        return $path;
    }

    public function getFullPathName(string $separator = ' > '): string
    {
        return collect($this->getFullPath())
            ->pluck('name')
            ->implode($separator);  // Electronics > Phones > Smartphones
    }
}
