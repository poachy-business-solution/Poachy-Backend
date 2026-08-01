<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Marketplace-order counterpart to SaleItemBatchDepletion — records which
 * ProductBatch row(s) a marketplace sale item's FIFO depletion drew from, so
 * an order cancellation/refund can reverse the correct batch(es) instead of
 * leaving the depletion unrecoverable.
 */
class MarketplaceSaleItemBatchDepletion extends Model
{
    protected $fillable = [
        'marketplace_sale_item_id',
        'product_id',
        'batch_id',
        'quantity_in_base_uom',
    ];

    protected $casts = [
        'quantity_in_base_uom' => 'decimal:4',
    ];

    public function marketplaceSaleItem(): BelongsTo
    {
        return $this->belongsTo(MarketplaceSaleItem::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ProductBatch::class, 'batch_id');
    }
}
