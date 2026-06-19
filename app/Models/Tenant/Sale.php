<?php

namespace App\Models\Tenant;

use App\Enums\Tenant\PaymentStatus;
use App\Enums\Tenant\PaymentMethod;
use App\Observers\Tenant\SaleObserver;
use App\Traits\Tenant\HasAuditLogging;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

#[ObservedBy([SaleObserver::class])]
class Sale extends Model
{
    use HasFactory, SoftDeletes, HasAuditLogging;

    protected $table = 'sales';

    protected $fillable = [
        'sale_number',
        'store_id',
        'shift_assignment_id',
        'customer_id',
        'sale_date',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'total_amount',
        'payment_status',
        'amount_paid',
        'amount_due',
        'payment_method',
        'payment_reference',
        'coupon_id',
        'loyalty_points_earned',
        'loyalty_points_redeemed',
        'served_by',
        'notes',
    ];

    protected $casts = [
        'sale_date' => 'datetime',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'amount_due' => 'decimal:2',
        'loyalty_points_earned' => 'decimal:2',
        'loyalty_points_redeemed' => 'decimal:2',
        'payment_status' => PaymentStatus::class,
        'payment_method' => PaymentMethod::class,
    ];

    protected $attributes = [
        'payment_status' => 'paid',
        'payment_method' => 'cash',
    ];

    protected $appends = [
        'is_paid',
        'is_credit_sale',
        'has_refunds',
    ];

    /**
     * Override getAuditableFields from HasAuditLogging
     */
    public function getAuditableFields(): array
    {
        return [
            'sale_number',
            'store_id',
            'customer_id',
            'subtotal',
            'tax_amount',
            'discount_amount',
            'total_amount',
            'payment_status',
            'amount_paid',
            'amount_due',
            'payment_method',
        ];
    }

    // ============================================
    // RELATIONSHIPS
    // ============================================

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function shiftAssignment(): BelongsTo
    {
        return $this->belongsTo(ShiftAssignment::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function servedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'served_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SalePayment::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(SaleRefund::class, 'original_sale_id');
    }

    public function couponUsage(): HasMany
    {
        return $this->hasMany(CouponUsage::class);
    }

    public function promotionUsages(): HasMany
    {
        return $this->hasMany(PromotionUsage::class);
    }

    public function loyaltyTransactions(): HasMany
    {
        return $this->hasMany(LoyaltyTransaction::class, 'reference_id')
            ->where('reference_type', self::class);
    }

    public function creditTransactions(): HasMany
    {
        return $this->hasMany(CustomerCreditTransaction::class, 'reference_id')
            ->where('reference_type', self::class);
    }

    // ============================================
    // SCOPES
    // ============================================

    public function scopeByStore(Builder $query, int $storeId): Builder
    {
        return $query->where('store_id', $storeId);
    }

    public function scopeForShift($query, int $shiftAssignmentId)
    {
        return $query->where('shift_assignment_id', $shiftAssignmentId);
    }

    public function scopeByCustomer(Builder $query, int $customerId): Builder
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeByPaymentStatus(Builder $query, PaymentStatus $status): Builder
    {
        return $query->where('payment_status', $status);
    }

    public function scopePaid(Builder $query): Builder
    {
        return $query->where('payment_status', PaymentStatus::PAID);
    }

    public function scopeUnpaid(Builder $query): Builder
    {
        return $query->where('payment_status', PaymentStatus::UNPAID);
    }

    public function scopePartiallyPaid(Builder $query): Builder
    {
        return $query->where('payment_status', PaymentStatus::PARTIALLY_PAID);
    }

    public function scopeCreditSales(Builder $query): Builder
    {
        return $query->where('payment_method', PaymentMethod::CREDIT)
            ->orWhere('amount_due', '>', 0);
    }

    public function scopeByDateRange(Builder $query, ?string $fromDate = null, ?string $toDate = null): Builder
    {
        if ($fromDate) {
            $query->whereDate('sale_date', '>=', $fromDate);
        }

        if ($toDate) {
            $query->whereDate('sale_date', '<=', $toDate);
        }

        return $query;
    }

    public function scopeToday(Builder $query): Builder
    {
        return $query->whereDate('sale_date', today());
    }

    public function scopeRecent(Builder $query): Builder
    {
        return $query->orderBy('sale_date', 'desc')
            ->orderBy('created_at', 'desc');
    }

    public function scopeWithDetails(Builder $query): Builder
    {
        return $query->with([
            'store:id,name,code',
            'customer:id,customer_number,name,phone',
            'coupon:id,code,description',
            'servedBy:id,name',
            'items.product:id,name,sku',
            'items.productVariant:id,variant_name',
            'items.uom:id,code,name',
            'payments',
        ]);
    }

    // ============================================
    // ACCESSORS
    // ============================================

    public function getIsPaidAttribute(): bool
    {
        return $this->payment_status === PaymentStatus::PAID;
    }

    public function getIsCreditSaleAttribute(): bool
    {
        return $this->amount_due > 0 || $this->payment_method === PaymentMethod::CREDIT;
    }

    public function getHasRefundsAttribute(): bool
    {
        return $this->refunds()->exists();
    }

    public function getTotalRefundedAttribute(): float
    {
        return (float) $this->refunds()->sum('refund_amount');
    }

    public function getNetAmountAttribute(): float
    {
        return $this->total_amount - $this->total_refunded;
    }

    public function getOutstandingBalanceAttribute(): float
    {
        return max(0, $this->amount_due);
    }

    // ============================================
    // HELPER METHODS
    // ============================================

    /**
     * Check if sale can be refunded
     */
    public function canBeRefunded(): bool
    {
        // Cannot refund if already fully refunded
        if ($this->total_refunded >= $this->total_amount) {
            return false;
        }

        // Cannot refund unpaid credit sales
        if ($this->payment_status === PaymentStatus::UNPAID) {
            return false;
        }

        return true;
    }

    /**
     * Check if sale is walk-in (no customer)
     */
    public function isWalkIn(): bool
    {
        return $this->customer_id === null;
    }

    /**
     * Get sale profit margin
     */
    public function getProfitMargin(): array
    {
        $totalCost = $this->items->sum(function ($item) {
            return $item->unit_cost * $item->quantity;
        });

        $profit = $this->total_amount - $totalCost;
        $marginPercentage = $totalCost > 0 ? ($profit / $totalCost) * 100 : 0;

        return [
            'total_cost' => round($totalCost, 2),
            'total_revenue' => round($this->total_amount, 2),
            'profit' => round($profit, 2),
            'margin_percentage' => round($marginPercentage, 2),
        ];
    }

    /**
     * Update payment status based on amounts
     */
    public function updatePaymentStatus(): void
    {
        if ($this->amount_paid >= $this->total_amount) {
            $this->payment_status = PaymentStatus::PAID;
            $this->amount_due = 0;
        } elseif ($this->amount_paid > 0) {
            $this->payment_status = PaymentStatus::PARTIALLY_PAID;
            $this->amount_due = $this->total_amount - $this->amount_paid;
        } else {
            $this->payment_status = PaymentStatus::UNPAID;
            $this->amount_due = $this->total_amount;
        }

        $this->save();
    }

    /**
     * Calculate totals from items
     */
    public function recalculateTotals(): void
    {
        $this->subtotal = $this->items->sum('subtotal');
        $this->tax_amount = $this->items->sum('tax_amount');
        $this->discount_amount = $this->items->sum('discount_amount');

        // Total is already calculated correctly as final amount after all adjustments
        $this->save();
    }

    public function hasShift(): bool
    {
        return $this->shift_assignment_id !== null;
    }

    public function isCashPayment(): bool
    {
        return $this->payment_method === \App\Enums\Tenant\PaymentMethod::CASH;
    }

    public function getChangeAmount(): float
    {
        // Change only applies to cash payments
        if (!$this->isCashPayment()) {
            return 0;
        }

        // Only if customer paid more than total
        if ($this->amount_paid <= $this->total_amount) {
            return 0;
        }

        return round($this->amount_paid - $this->total_amount, 2);
    }
}
