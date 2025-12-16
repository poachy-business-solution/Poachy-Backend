<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    // Scopes

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
