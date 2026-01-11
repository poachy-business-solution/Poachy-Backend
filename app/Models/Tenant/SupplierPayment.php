<?php

namespace App\Models\Tenant;

use App\Enums\Tenant\PaymentMethod;
use App\Observers\Tenant\SupplierPaymentObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

#[ObservedBy([SupplierPaymentObserver::class])]
class SupplierPayment extends Model
{
    use HasFactory;

    protected $table = 'supplier_payments';

    protected $fillable = [
        'payment_number',
        'supplier_id',
        'purchase_order_id',
        'payment_date',
        'amount',
        'payment_method',
        'reference_number',
        'notes',
        'receipt_path',
        'created_by',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount' => 'decimal:2',
        'payment_method' => PaymentMethod::class,
    ];

    // ============================================
    // RELATIONSHIPS
    // ============================================

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ============================================
    // SCOPES
    // ============================================

    public function scopeBySupplier(Builder $query, int $supplierId): Builder
    {
        return $query->where('supplier_id', $supplierId);
    }

    public function scopeByPurchaseOrder(Builder $query, int $poId): Builder
    {
        return $query->where('purchase_order_id', $poId);
    }

    public function scopeByPaymentMethod(Builder $query, PaymentMethod|string $method): Builder
    {
        $methodValue = $method instanceof PaymentMethod ? $method->value : $method;
        return $query->where('payment_method', $methodValue);
    }

    public function scopeByDateRange(Builder $query, ?string $from, ?string $to): Builder
    {
        if ($from) {
            $query->whereDate('payment_date', '>=', $from);
        }

        if ($to) {
            $query->whereDate('payment_date', '<=', $to);
        }

        return $query;
    }

    public function scopeWithDetails(Builder $query): Builder
    {
        return $query->with([
            'supplier:id,name,outstanding_balance,credit_limit',
            'purchaseOrder:id,po_number,total_amount,amount_paid,payment_status,supplier_id',
            'createdBy:id,name,email',
        ]);
    }

    public function scopeRecent(Builder $query): Builder
    {
        return $query->orderBy('payment_date', 'desc')
            ->orderBy('created_at', 'desc');
    }

    // ============================================
    // ACCESSORS
    // ============================================

    public function getPaymentMethodLabelAttribute(): string
    {
        return $this->payment_method->label();
    }

    public function getHasReceiptAttribute(): bool
    {
        return !empty($this->receipt_path);
    }

    public function getReceiptUrlAttribute(): ?string
    {
        if (!$this->has_receipt) {
            return null;
        }

        return Storage::disk('public')->url($this->receipt_path);
    }

    // ============================================
    // HELPER METHODS
    // ============================================

    /**
     * Check if payment requires reference number
     */
    public function requiresReference(): bool
    {
        return $this->payment_method->requiresReference();
    }

    /**
     * Check if payment is linked to a purchase order
     */
    public function isLinkedToPurchaseOrder(): bool
    {
        return !is_null($this->purchase_order_id);
    }

    /**
     * Get display name for the payment
     */
    public function getDisplayNameAttribute(): string
    {
        if ($this->isLinkedToPurchaseOrder()) {
            return "{$this->payment_number} - PO {$this->purchaseOrder->po_number}";
        }

        return "{$this->payment_number} - General Payment";
    }
}
