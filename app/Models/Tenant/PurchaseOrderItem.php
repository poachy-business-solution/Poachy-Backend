<?php

namespace App\Models\Tenant;

use App\Enums\Tenant\PurchaseOrderItemStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_order_id',
        'product_id',
        'product_variant_id',
        'uom_id',
        'quantity_ordered',
        'quantity_received',
        'quantity_ordered_in_base_uom',
        'quantity_received_in_base_uom',
        'unit_cost',
        'unit_cost_in_base_uom',
        'tax_rate_id',
        'tax_amount',
        'subtotal',
        'status',
        'notes',
    ];

    protected $casts = [
        'quantity_ordered' => 'decimal:4',
        'quantity_received' => 'decimal:4',
        'quantity_ordered_in_base_uom' => 'decimal:4',
        'quantity_received_in_base_uom' => 'decimal:4',
        'unit_cost' => 'decimal:2',
        'unit_cost_in_base_uom' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'status' => PurchaseOrderItemStatus::class,
    ];

    protected $attributes = [
        'quantity_received' => 0,
        'quantity_received_in_base_uom' => 0,
        'status' => PurchaseOrderItemStatus::PENDING,
    ];

    /**
     * RELATIONSHIPS
     */

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
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

    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class);
    }

    /**
     * ACCESSORS
     */

    public function getIsPendingAttribute(): bool
    {
        return $this->status === \App\Enums\Tenant\PurchaseOrderItemStatus::PENDING;
    }

    public function getIsFullyReceivedAttribute(): bool
    {
        return $this->quantity_received >= $this->quantity_ordered;
    }

    public function getIsPartiallyReceivedAttribute(): bool
    {
        return $this->quantity_received > 0
            && $this->quantity_received < $this->quantity_ordered;
    }

    public function getIsNotReceivedAttribute(): bool
    {
        return $this->quantity_received == 0;
    }

    public function getQuantityPendingAttribute(): float
    {
        return max(0, $this->quantity_ordered - $this->quantity_received);
    }

    public function getQuantityPendingInBaseUomAttribute(): float
    {
        return max(0, $this->quantity_ordered_in_base_uom - $this->quantity_received_in_base_uom);
    }

    public function getReceiveProgressAttribute(): float
    {
        if ($this->quantity_ordered <= 0) {
            return 0;
        }

        return ($this->quantity_received / $this->quantity_ordered) * 100;
    }

    public function getTotalCostAttribute(): float
    {
        return $this->subtotal + $this->tax_amount;
    }

    // Helper methods

    public function updateStatus(): void
    {
        $oldStatus = $this->status;

        if ($this->quantity_received >= $this->quantity_ordered) {
            $this->status = \App\Enums\Tenant\PurchaseOrderItemStatus::RECEIVED;
        } elseif ($this->quantity_received > 0) {
            $this->status = \App\Enums\Tenant\PurchaseOrderItemStatus::PARTIALLY_RECEIVED;
        } else {
            $this->status = \App\Enums\Tenant\PurchaseOrderItemStatus::PENDING;
        }

        // Only save if status changed
        if ($oldStatus !== $this->status) {
            $this->saveQuietly(); // Save without triggering events
        }
    }
}
