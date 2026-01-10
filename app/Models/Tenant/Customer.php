<?php

namespace App\Models\Tenant;

use App\Enums\Tenant\CustomerType;
use App\Observers\Tenant\CustomerObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

#[ObservedBy([CustomerObserver::class])]
class Customer extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'customers';

    protected $fillable = [
        'customer_number',
        'name',
        'email',
        'phone',
        'date_of_birth',
        'address',
        'customer_type',
        'loyalty_points',
        'total_lifetime_purchases',
        'total_visits',
        'preferred_store_id',
        'credit_limit',
        'current_debt',
        'is_active',
        'registered_at',
    ];

    protected $casts = [
        'customer_type' => CustomerType::class,
        'date_of_birth' => 'date',
        'loyalty_points' => 'decimal:2',
        'total_lifetime_purchases' => 'decimal:2',
        'credit_limit' => 'decimal:2',
        'current_debt' => 'decimal:2',
        'is_active' => 'boolean',
        'registered_at' => 'datetime',
    ];

    protected $hidden = [
        'deleted_at',
    ];

    protected $appends = [
        'available_credit',
        'customer_type_label',
    ];

    // ============================================
    // RELATIONSHIPS
    // ============================================

    public function preferredStore(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'preferred_store_id');
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(
            CustomerGroup::class,
            'customer_group_members',
            'customer_id',
            'group_id'
        )->withTimestamps()
            ->withPivot('joined_at')
            ->wherePivot('joined_at', '<=', now());
    }

    public function currentGroup(): HasOne
    {
        return $this->hasOne(CustomerGroupMember::class)->latestOfMany();
    }

    public function loyaltyTransactions(): HasMany
    {
        return $this->hasMany(LoyaltyTransaction::class);
    }

    public function creditTransactions(): HasMany
    {
        return $this->hasMany(CustomerCreditTransaction::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    // ============================================
    // ACCESSORS
    // ============================================

    public function getAvailableCreditAttribute(): float
    {
        return (float) ($this->credit_limit - $this->current_debt);
    }

    public function getCustomerTypeLabelAttribute(): string
    {
        return $this->customer_type->label();
    }

    // ============================================
    // SCOPES
    // ============================================

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeInactive(Builder $query): Builder
    {
        return $query->where('is_active', false);
    }

    public function scopeByType(Builder $query, CustomerType $type): Builder
    {
        return $query->where('customer_type', $type);
    }

    public function scopeSearch(Builder $query, string $search): Builder
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")
                ->orWhere('customer_number', 'like', "%{$search}%");
        });
    }

    public function scopeWithDebt(Builder $query): Builder
    {
        return $query->where('current_debt', '>', 0);
    }

    public function scopeTopCustomers(Builder $query, int $limit = 10): Builder
    {
        return $query->orderByDesc('total_lifetime_purchases')->limit($limit);
    }

    // ============================================
    // METHODS
    // ============================================

    /**
     * Check if customer can be upgraded to specified type
     */
    public function canUpgradeTo(CustomerType $targetType): bool
    {
        return $this->customer_type->canUpgradeTo($targetType);
    }

    /**
     * Get next upgrade level for this customer
     */
    public function getNextUpgradeLevel(): ?CustomerType
    {
        return $this->customer_type->nextLevel();
    }

    /**
     * Check if customer has available credit
     */
    public function hasAvailableCredit(float $amount = 0): bool
    {
        return $this->available_credit >= $amount;
    }

    /**
     * Check if customer is in a specific group
     */
    public function isInGroup(int $groupId): bool
    {
        return $this->groups()->where('customer_groups.id', $groupId)->exists();
    }

    /**
     * Get customer's current active group
     */
    public function getActiveGroup(): ?CustomerGroup
    {
        return $this->groups()->first();
    }
}
