<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceSaleItem extends Model
{
    use HasFactory;

    protected $table = 'marketplace_sale_items';

    protected $fillable = [
        'marketplace_sale_id',
        'product_id',
        'product_variant_id',
        'bundle_id',
        'uom_id',
        'quantity',
        'quantity_in_base_uom',
        'unit_price',
        'tax_amount',
        'discount_amount',
        'subtotal',
    ];

    protected function casts(): array
    {
        return [
            'quantity'             => 'decimal:4',
            'quantity_in_base_uom' => 'decimal:4',
            'unit_price'           => 'decimal:2',
            'tax_amount'           => 'decimal:2',
            'discount_amount'      => 'decimal:2',
            'subtotal'             => 'decimal:2',
        ];
    }

    // =========================================================================
    // Relationships
    // =========================================================================

    public function sale(): BelongsTo
    {
        return $this->belongsTo(MarketplaceSale::class, 'marketplace_sale_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function bundle(): BelongsTo
    {
        return $this->belongsTo(ProductBundle::class, 'bundle_id');
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'uom_id');
    }
}
