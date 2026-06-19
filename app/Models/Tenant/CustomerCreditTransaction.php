<?php

namespace App\Models\Tenant;

use App\Enums\Tenant\CreditTransactionType;
use App\Enums\Tenant\PaymentMethod;
use App\Observers\Tenant\CustomerCreditTransactionObserver;
use App\Traits\Tenant\HasAuditLogging;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

#[ObservedBy([CustomerCreditTransactionObserver::class])]
class CustomerCreditTransaction extends Model
{
    use HasFactory, SoftDeletes, HasAuditLogging;

    protected $table = 'customer_credit_transactions';

    protected $fillable = [
        'customer_id',
        'transaction_type',
        'amount',
        'balance_after',
        'reference_type',
        'reference_id',
        'payment_method',
        'payment_reference',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'transaction_type' => CreditTransactionType::class,
        'payment_method' => PaymentMethod::class,
    ];

    // ============================================
    // RELATIONSHIPS
    // ============================================

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo('reference', 'reference_type', 'reference_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ============================================
    // SCOPES
    // ============================================

    public function scopeByCustomer(Builder $query, int $customerId): Builder
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeCreditSales(Builder $query): Builder
    {
        return $query->where('transaction_type', CreditTransactionType::SALE_ON_CREDIT);
    }

    public function scopePayments(Builder $query): Builder
    {
        return $query->where('transaction_type', CreditTransactionType::PAYMENT);
    }

    public function scopeAdjustments(Builder $query): Builder
    {
        return $query->where('transaction_type', CreditTransactionType::ADJUSTMENT);
    }

    public function scopeWriteOffs(Builder $query): Builder
    {
        return $query->where('transaction_type', CreditTransactionType::WRITE_OFF);
    }

    // ============================================
    // ACCESSORS
    // ============================================

    public function getIsDebitAttribute(): bool
    {
        return $this->amount > 0;
    }

    public function getIsCreditAttribute(): bool
    {
        return $this->amount < 0;
    }

    public function getAbsoluteAmountAttribute(): float
    {
        return abs($this->amount);
    }
}
