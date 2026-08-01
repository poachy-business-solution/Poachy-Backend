<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Records exactly which ProductBatch rows (and how much) a sale item's FIFO
 * depletion drew from, since depleteBatchesFIFO() can span multiple batches
 * for a single sale item. This is what lets a refund reverse the correct
 * batch(es) instead of guessing — without it, refunding a batch-tracked
 * product has no way to know which batch(es) to restore into.
 */
class SaleItemBatchDepletion extends Model
{
    protected $fillable = [
        'sale_item_id',
        'product_id',
        'batch_id',
        'quantity_in_base_uom',
    ];

    protected $casts = [
        'quantity_in_base_uom' => 'decimal:4',
    ];

    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(SaleItem::class);
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
