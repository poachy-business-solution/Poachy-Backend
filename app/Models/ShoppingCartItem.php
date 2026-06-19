<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShoppingCartItem extends Model
{
    protected $connection = 'central';

    protected $table = 'shopping_cart_items';

    public $timestamps = false;

    protected $fillable = [
        'cart_id',
        'marketplace_product_id',
        'product_name',
        'product_sku',
        'tenant_product_id',
        'tenant_variant_id',
        'quantity',
        'uom_code',
        'unit_price',
        'current_price',
        'added_at',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'tenant_product_id' => 'integer',
            'tenant_variant_id' => 'integer',
            'quantity'          => 'decimal:4',
            'unit_price'        => 'decimal:2',
            'current_price'     => 'decimal:2',
            'added_at'          => 'datetime',
            'updated_at'        => 'datetime',
        ];
    }

    // =========================================================================
    // Relationships
    // =========================================================================

    public function cart(): BelongsTo
    {
        return $this->belongsTo(ShoppingCart::class, 'cart_id');
    }

    public function marketplaceProduct(): BelongsTo
    {
        return $this->belongsTo(MarketplaceProduct::class, 'marketplace_product_id');
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    public function getLineTotal(): float
    {
        return round((float) $this->quantity * (float) $this->unit_price, 2);
    }

    /**
     * Whether the price has changed since the item was added to the cart.
     */
    public function isPriceChanged(): bool
    {
        if ($this->current_price === null) {
            return false;
        }

        return bccomp((string) $this->unit_price, (string) $this->current_price, 2) !== 0;
    }
}
