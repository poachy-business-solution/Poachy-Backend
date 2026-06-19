<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockTransferItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'transfer_id',
        'product_id',
        'product_variant_id',
        'uom_id',
        'quantity_requested',
        'quantity_sent',
        'quantity_received',
        'quantity_requested_in_base_uom',
        'quantity_sent_in_base_uom',
        'quantity_received_in_base_uom',
        'notes',
    ];

    protected $casts = [
        'quantity_requested' => 'decimal:4',
        'quantity_sent' => 'decimal:4',
        'quantity_received' => 'decimal:4',
        'quantity_requested_in_base_uom' => 'decimal:4',
        'quantity_sent_in_base_uom' => 'decimal:4',
        'quantity_received_in_base_uom' => 'decimal:4',
    ];

    /**
     * RELATIONSHIPS
     */

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(StockTransfer::class, 'transfer_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'uom_id');
    }

    /**
     * ACCESSORS
     */

    public function getHasDiscrepancyAttribute(): bool
    {
        if (!$this->quantity_received || !$this->quantity_sent) {
            return false;
        }

        return $this->quantity_received != $this->quantity_sent;
    }

    public function getDiscrepancyQuantityAttribute(): ?float
    {
        if (!$this->has_discrepancy) {
            return null;
        }

        return $this->quantity_sent - $this->quantity_received;
    }

    public function getIsFulfilledAttribute(): bool
    {
        return $this->quantity_received > 0
            && $this->quantity_received == $this->quantity_sent;
    }

    public function getIsPartialAttribute(): bool
    {
        return $this->quantity_received > 0
            && $this->quantity_received < $this->quantity_sent;
    }
}
