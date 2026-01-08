<?php

namespace App\Models\Tenant;

use App\Enums\Tenant\LoyaltyTransactionType;
use App\Observers\Tenant\LoyaltyTransactionObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

// #[ObservedBy([LoyaltyTransactionObserver::class])]
class LoyaltyTransaction extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'loyalty_transactions';

    protected $fillable = [
        'customer_id',
        'transaction_type',
        'points',
        'balance_after',
        'reference_type',
        'reference_id',
        'description',
        'expires_at',
    ];

    protected $casts = [
        'points' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'transaction_type' => LoyaltyTransactionType::class,
        'expires_at' => 'date',
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

    // ============================================
    // SCOPES
    // ============================================

    public function scopeByCustomer(Builder $query, int $customerId): Builder
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeEarned(Builder $query): Builder
    {
        return $query->where('transaction_type', LoyaltyTransactionType::EARNED);
    }

    public function scopeRedeemed(Builder $query): Builder
    {
        return $query->where('transaction_type', LoyaltyTransactionType::REDEEMED);
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('transaction_type', LoyaltyTransactionType::EXPIRED);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
                ->orWhere('expires_at', '>', now());
        });
    }

    public function scopeExpiringSoon(Builder $query, int $days = 30): Builder
    {
        $thresholdDate = now()->addDays($days)->toDateString();

        return $query->where('transaction_type', LoyaltyTransactionType::EARNED)
            ->whereBetween('expires_at', [now()->toDateString(), $thresholdDate]);
    }

    // ============================================
    // ACCESSORS
    // ============================================

    public function getIsPositiveAttribute(): bool
    {
        return $this->points > 0;
    }

    public function getIsExpiredAttribute(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }
}
