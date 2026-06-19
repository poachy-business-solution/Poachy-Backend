<?php

namespace App\Models\Tenant;

use App\Observers\Tenant\CustomerGroupObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

#[ObservedBy([CustomerGroupObserver::class])]
class CustomerGroup extends Model
{
    use HasFactory;

    protected $table = 'customer_groups';

    protected $fillable = [
        'name',
        'description',
        'discount_percentage',
        'requires_approval',
        'is_active',
    ];

    protected $casts = [
        'discount_percentage' => 'decimal:2',
        'requires_approval' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected $hidden = [];

    protected $appends = [
        'members_count',
    ];

    // ============================================
    // RELATIONSHIPS
    // ============================================

    public function customers(): BelongsToMany
    {
        return $this->belongsToMany(
            Customer::class,
            'customer_group_members',
            'group_id',
            'customer_id'
        )->withTimestamps()
            ->withPivot('joined_at');
    }

    public function members(): HasMany
    {
        return $this->hasMany(CustomerGroupMember::class, 'group_id');
    }

    // ============================================
    // ACCESSORS
    // ============================================

    public function getMembersCountAttribute(): int
    {
        return $this->customers()->count();
    }

    // ============================================
    // SCOPES
    // ============================================

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeRequiringApproval(Builder $query): Builder
    {
        return $query->where('requires_approval', true);
    }

    public function scopeSearch(Builder $query, string $search): Builder
    {
        return $query->where('name', 'like', "%{$search}%");
    }

    // ============================================
    // METHODS
    // ============================================

    /**
     * Check if group has a specific customer
     */
    public function hasCustomer(int $customerId): bool
    {
        return $this->customers()->where('customers.id', $customerId)->exists();
    }

    /**
     * Add customer to this group
     */
    public function addCustomer(int $customerId): bool
    {
        if ($this->hasCustomer($customerId)) {
            return false;
        }

        $this->customers()->attach($customerId, [
            'joined_at' => now(),
        ]);

        return true;
    }

    /**
     * Remove customer from this group
     */
    public function removeCustomer(int $customerId): bool
    {
        return $this->customers()->detach($customerId) > 0;
    }

    /**
     * Check if group offers discount
     */
    public function hasDiscount(): bool
    {
        return $this->discount_percentage > 0;
    }
}
