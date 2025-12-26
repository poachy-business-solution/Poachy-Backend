<?php

namespace App\Models\Tenant;

use App\Enums\Tenant\CouponApplicabilityType;
use App\Enums\Tenant\DiscountType;
use App\Observers\Tenant\CouponObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

#[ObservedBy(CouponObserver::class)]
class Coupon extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'coupons';

    protected $fillable = [
        'code',
        'description',
        'discount_type',
        'discount_value',
        'min_purchase_amount',
        'max_discount_amount',
        'usage_limit',
        'usage_count',
        'usage_limit_per_customer',
        'valid_from',
        'valid_until',
        'applicable_to',
        'is_active',
    ];

    protected $casts = [
        'discount_type' => DiscountType::class,
        'applicable_to' => CouponApplicabilityType::class,
        'discount_value' => 'decimal:2',
        'min_purchase_amount' => 'decimal:2',
        'max_discount_amount' => 'decimal:2',
        'usage_count' => 'integer',
        'usage_limit' => 'integer',
        'usage_limit_per_customer' => 'integer',
        'valid_from' => 'date',
        'valid_until' => 'date',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Relationships
     */

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'coupon_products')
            ->withPivot('product_variant_id')
            ->withTimestamps();
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(ProductCategory::class, 'coupon_categories', 'coupon_id', 'category_id')
            ->withTimestamps();
    }

    public function brands(): BelongsToMany
    {
        return $this->belongsToMany(ProductBrand::class, 'coupon_brands', 'coupon_id', 'brand_id')
            ->withTimestamps();
    }

    /**
     * Scopes
     */

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeValid(Builder $query): Builder
    {
        $now = now();
        return $query->where('valid_from', '<=', $now)
            ->where('valid_until', '>=', $now);
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->active()->valid();
    }

    public function scopeByCode(Builder $query, string $code): Builder
    {
        return $query->where('code', $code);
    }

    public function scopeNotExpired(Builder $query): Builder
    {
        return $query->where('valid_until', '>=', now());
    }

    public function scopeNotExhausted(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->whereNull('usage_limit')
                ->orWhereRaw('usage_count < usage_limit');
        });
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if (empty($search)) {
            return $query;
        }

        return $query->where(function ($q) use ($search) {
            $q->where('code', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%");
        });
    }

    public function scopeFilterByStatus(Builder $query, ?string $status): Builder
    {
        if (empty($status)) {
            return $query;
        }

        return match ($status) {
            'active' => $query->active()->valid(),
            'inactive' => $query->where('is_active', false),
            'expired' => $query->where('valid_until', '<', now()),
            'upcoming' => $query->where('valid_from', '>', now()),
            default => $query,
        };
    }

    public function scopeFilterByApplicability(Builder $query, ?string $applicability): Builder
    {
        if (empty($applicability)) {
            return $query;
        }

        return $query->where('applicable_to', $applicability);
    }

    /**
     * Accessors & Mutators
     */

    public function getIsExpiredAttribute(): bool
    {
        return $this->valid_until < now();
    }

    public function getIsValidAttribute(): bool
    {
        $now = now();
        return $this->valid_from <= $now && $this->valid_until >= $now;
    }

    public function getIsExhaustedAttribute(): bool
    {
        if ($this->usage_limit === null) {
            return false;
        }

        return $this->usage_count >= $this->usage_limit;
    }

    public function getRemainingUsageAttribute(): ?int
    {
        if ($this->usage_limit === null) {
            return null;
        }

        return max(0, $this->usage_limit - $this->usage_count);
    }

    public function getStatusTextAttribute(): string
    {
        if (!$this->is_active) {
            return 'Inactive';
        }

        if ($this->is_expired) {
            return 'Expired';
        }

        if ($this->valid_from > now()) {
            return 'Upcoming';
        }

        if ($this->is_exhausted) {
            return 'Exhausted';
        }

        return 'Active';
    }

    /**
     * Business Logic Methods
     */

    public function canBeUsed(): bool
    {
        return $this->is_active
            && $this->is_valid
            && !$this->is_exhausted;
    }

    public function canBeEdited(): bool
    {
        // Cannot edit if already used
        return $this->usage_count === 0;
    }

    public function canChangeApplicabilityType(): bool
    {
        // Cannot change if already has related data
        return $this->products()->count() === 0
            && $this->categories()->count() === 0
            && $this->brands()->count() === 0;
    }

    public function incrementUsage(): void
    {
        $this->increment('usage_count');
    }

    public function decrementUsage(): void
    {
        if ($this->usage_count > 0) {
            $this->decrement('usage_count');
        }
    }
}
