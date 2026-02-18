<?php

namespace App\Models;

use App\Enums\Central\OrderFulfillmentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MarketplaceOrderItem extends Model
{
    use SoftDeletes;

    protected $connection = 'central';

    protected $table = 'marketplace_order_items';

    protected $fillable = [
        'order_id',
        'marketplace_product_id',
        'tenant_product_id',
        'tenant_variant_id',
        'tenant_bundle_id',
        'product_name',
        'product_sku',
        'variant_name',
        'uom_code',
        'uom_name',
        'quantity',
        'quantity_in_base_uom',
        'unit_price',
        'tax_rate',
        'tax_amount',
        'discount_amount',
        'subtotal',
        'fulfillment_status',
    ];

    protected function casts(): array
    {
        return [
            'tenant_product_id'    => 'integer',
            'tenant_variant_id'    => 'integer',
            'tenant_bundle_id'     => 'integer',
            'quantity'             => 'decimal:4',
            'quantity_in_base_uom' => 'decimal:4',
            'unit_price'           => 'decimal:2',
            'tax_rate'             => 'decimal:2',
            'tax_amount'           => 'decimal:2',
            'discount_amount'      => 'decimal:2',
            'subtotal'             => 'decimal:2',
            'fulfillment_status'   => OrderFulfillmentStatus::class,
        ];
    }

    // =========================================================================
    // Relationships
    // =========================================================================

    public function order(): BelongsTo
    {
        return $this->belongsTo(MarketplaceOrder::class, 'order_id');
    }

    public function marketplaceProduct(): BelongsTo
    {
        return $this->belongsTo(MarketplaceProduct::class, 'marketplace_product_id');
    }
}
