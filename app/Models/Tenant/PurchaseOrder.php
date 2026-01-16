<?php

namespace App\Models\Tenant;

use App\Enums\Tenant\PaymentStatus;
use App\Enums\Tenant\PurchaseOrderStatus;
use App\Observers\Tenant\PurchaseOrderObserver;
use App\Traits\Tenant\HasAuditLogging;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ObservedBy([PurchaseOrderObserver::class])]
class PurchaseOrder extends Model
{
    use HasFactory, SoftDeletes, HasAuditLogging;

    protected $fillable = [
        'po_number',
        'supplier_id',
        'store_id',
        'order_date',
        'expected_delivery_date',
        'status', // draft, sent, received, partially_received, cancelled
        'subtotal',
        'tax_amount',
        'shipping_cost',
        'total_amount',
        'payment_status', // unpaid, partially_paid, paid
        'amount_paid',
        'notes',
        'created_by',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'status' => PurchaseOrderStatus::class,
        'payment_status' => PaymentStatus::class,
        'order_date' => 'date',
        'expected_delivery_date' => 'date',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    /**
     * RELATIONSHIPS
     */

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class, 'purchase_order_id');
    }

    public function batches(): HasMany
    {
        return $this->hasMany(ProductBatch::class, 'purchase_order_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SupplierPayment::class, 'purchase_order_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * SCOPES
     */

    public function scopeBySupplier($query, int $supplierId)
    {
        return $query->where('supplier_id', $supplierId);
    }

    public function scopeByStore($query, int $storeId)
    {
        return $query->where('store_id', $storeId);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'sent');
    }

    public function scopeReceived($query)
    {
        return $query->where('status', 'received');
    }

    /**
     * ACCESSORS
     */

    public function getIsDraftAttribute(): bool
    {
        return $this->status === 'draft';
    }

    public function getIsSentAttribute(): bool
    {
        return $this->status === 'sent';
    }

    public function getIsReceivedAttribute(): bool
    {
        return in_array($this->status, ['received', 'partially_received']);
    }

    public function getIsFullyReceivedAttribute(): bool
    {
        return $this->status === 'received';
    }

    public function getIsPartiallyReceivedAttribute(): bool
    {
        return $this->status === 'partially_received';
    }

    public function getIsUnpaidAttribute(): bool
    {
        return $this->payment_status === 'unpaid';
    }

    public function getIsPartiallyPaidAttribute(): bool
    {
        return $this->payment_status === 'partially_paid';
    }

    public function getIsPaidAttribute(): bool
    {
        return $this->payment_status === 'paid';
    }

    public function getAmountDueAttribute(): float
    {
        return max(0, $this->total_amount - $this->amount_paid);
    }

    public function getPaymentProgressAttribute(): float
    {
        if ($this->total_amount <= 0) {
            return 0;
        }

        return ($this->amount_paid / $this->total_amount) * 100;
    }
}
