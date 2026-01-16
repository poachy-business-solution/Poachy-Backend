<?php

namespace App\Models\Tenant;

use App\Enums\Tenant\PromotionApplicabilityType;
use App\Enums\Tenant\PromotionType;
use App\Observers\Tenant\PromotionObserver;
use App\Traits\Tenant\HasAuditLogging;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ObservedBy(PromotionObserver::class)]
class Promotion extends Model
{
    use HasFactory, SoftDeletes, HasAuditLogging;

    protected $table = 'promotions';

    protected $fillable = [
        'name',
        'code',
        'description',
        'promotion_type',
        'discount_value',
        'buy_quantity',
        'get_quantity',
        'get_items_free',
        'get_items_discount_percentage',
        'min_purchase_amount',
        'max_discount_amount',
        'max_uses_per_customer',
        'total_usage_limit',
        'total_usage_count',
        'start_date',
        'end_date',
        'active_days',
        'active_time_start',
        'active_time_end',
        'applicable_store_ids',
        'applicable_customer_group_ids',
        'applicable_to',
        'show_on_website',
        'show_in_pos',
        'banner_image_url',
        'display_priority',
        'is_active',
        'auto_apply',
    ];

    protected $casts = [
        'promotion_type' => PromotionType::class,
        'applicable_to' => PromotionApplicabilityType::class,
        'discount_value' => 'decimal:2',
        'buy_quantity' => 'integer',
        'get_quantity' => 'integer',
        'get_items_free' => 'boolean',
        'get_items_discount_percentage' => 'decimal:2',
        'min_purchase_amount' => 'decimal:2',
        'max_discount_amount' => 'decimal:2',
        'max_uses_per_customer' => 'integer',
        'total_usage_limit' => 'integer',
        'total_usage_count' => 'integer',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'active_days' => 'array',
        'active_time_start' => 'datetime:H:i:s',
        'active_time_end' => 'datetime:H:i:s',
        'applicable_store_ids' => 'array',
        'applicable_customer_group_ids' => 'array',
        'show_on_website' => 'boolean',
        'show_in_pos' => 'boolean',
        'display_priority' => 'integer',
        'is_active' => 'boolean',
        'auto_apply' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Relationships
     */

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'promotion_products')
            ->withPivot('product_variant_id')
            ->withTimestamps();
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(ProductCategory::class, 'promotion_categories', 'promotion_id', 'category_id')
            ->withTimestamps();
    }

    public function brands(): BelongsToMany
    {
        return $this->belongsToMany(ProductBrand::class, 'promotion_brands', 'promotion_id', 'brand_id')
            ->withTimestamps();
    }

    /**
     * Scopes
     */

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeValid(Builder $query, ?Carbon $now = null): Builder
    {
        $now = $now ?? now();
        return $query->where('start_date', '<=', $now)
            ->where('end_date', '>=', $now);
    }

    public function scopeAvailable(Builder $query, ?Carbon $now = null): Builder
    {
        return $query->active()->valid($now);
    }

    public function scopeByCode(Builder $query, string $code): Builder
    {
        return $query->where('code', $code);
    }

    public function scopeNotExpired(Builder $query): Builder
    {
        return $query->where('end_date', '>=', now());
    }

    public function scopeNotExhausted(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->whereNull('total_usage_limit')
                ->orWhereRaw('total_usage_count < total_usage_limit');
        });
    }

    public function scopeAutoApply(Builder $query): Builder
    {
        return $query->where('auto_apply', true);
    }

    public function scopeShowInPos(Builder $query): Builder
    {
        return $query->where('show_in_pos', true);
    }

    public function scopeShowOnWebsite(Builder $query): Builder
    {
        return $query->where('show_on_website', true);
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('display_priority', '>', 0)
            ->orderByDesc('display_priority');
    }

    public function scopeApplicableToStore(Builder $query, int $storeId): Builder
    {
        return $query->where(function ($q) use ($storeId) {
            $q->whereNull('applicable_store_ids')
                ->orWhereJsonContains('applicable_store_ids', $storeId);
        });
    }

    public function scopeApplicableToCustomerGroup(Builder $query, array $customerGroupIds): Builder
    {
        return $query->where(function ($q) use ($customerGroupIds) {
            $q->whereNull('applicable_customer_group_ids');

            foreach ($customerGroupIds as $groupId) {
                $q->orWhereJsonContains('applicable_customer_group_ids', $groupId);
            }
        });
    }

    public function scopeCurrentlyRunning(Builder $query, ?Carbon $now = null): Builder
    {
        $now = $now ?? now();

        return $query->active()
            ->valid($now)
            ->where(function ($q) use ($now) {
                // Check active days if specified
                $q->whereNull('active_days')
                    ->orWhereJsonContains('active_days', strtolower($now->format('l')));
            })
            ->where(function ($q) use ($now) {
                // Check time window if specified
                $currentTime = $now->format('H:i:s');

                $q->where(function ($subQ) {
                    $subQ->whereNull('active_time_start')
                        ->whereNull('active_time_end');
                })
                    ->orWhere(function ($subQ) use ($currentTime) {
                        $subQ->where('active_time_start', '<=', $currentTime)
                            ->where('active_time_end', '>=', $currentTime);
                    });
            });
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if (empty($search)) {
            return $query;
        }

        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%");
        });
    }

    public function scopeFilterByType(Builder $query, ?string $type): Builder
    {
        if (empty($type)) {
            return $query;
        }

        return $query->where('promotion_type', $type);
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
        return $this->end_date < now();
    }

    public function getIsValidAttribute(): bool
    {
        $now = now();
        return $this->start_date <= $now && $this->end_date >= $now;
    }

    public function getIsExhaustedAttribute(): bool
    {
        if ($this->total_usage_limit === null) {
            return false;
        }

        return $this->total_usage_count >= $this->total_usage_limit;
    }

    public function getRemainingUsageAttribute(): ?int
    {
        if ($this->total_usage_limit === null) {
            return null;
        }

        return max(0, $this->total_usage_limit - $this->total_usage_count);
    }

    public function getStatusTextAttribute(): string
    {
        if (!$this->is_active) {
            return 'Inactive';
        }

        if ($this->is_expired) {
            return 'Expired';
        }

        if ($this->start_date > now()) {
            return 'Scheduled';
        }

        if ($this->is_exhausted) {
            return 'Exhausted';
        }

        if (!$this->isActiveNow()) {
            return 'Outside Active Hours';
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
            && !$this->is_exhausted
            && $this->isActiveNow();
    }

    public function canBeEdited(): bool
    {
        // Cannot edit if already used
        return $this->total_usage_count === 0;
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
        $this->increment('total_usage_count');
    }

    public function decrementUsage(): void
    {
        if ($this->total_usage_count > 0) {
            $this->decrement('total_usage_count');
        }
    }

    /**
     * Check if promotion is active right now (considering time and day constraints)
     */
    public function isActiveNow(?Carbon $now = null): bool
    {
        $now = $now ?? now();

        // Check if within date range
        if ($now < $this->start_date || $now > $this->end_date) {
            return false;
        }

        // Check active days if specified
        if ($this->active_days) {
            $currentDay = strtolower($now->format('l')); // 'monday', 'tuesday', etc.
            if (!in_array($currentDay, $this->active_days)) {
                return false;
            }
        }

        // Check time window if specified
        if ($this->active_time_start && $this->active_time_end) {
            $currentTime = $now->format('H:i:s');
            if ($currentTime < $this->active_time_start || $currentTime > $this->active_time_end) {
                return false;
            }
        }

        return $this->is_active;
    }

    /**
     * Check if promotion applies to a specific store
     */
    public function appliesToStore(int $storeId): bool
    {
        if ($this->applicable_store_ids === null) {
            return true; // NULL = all stores
        }

        return in_array($storeId, $this->applicable_store_ids);
    }

    /**
     * Check if promotion applies to customer groups
     */
    public function appliesToCustomerGroups(array $customerGroupIds): bool
    {
        if ($this->applicable_customer_group_ids === null) {
            return true; // NULL = all customers
        }

        return !empty(array_intersect($customerGroupIds, $this->applicable_customer_group_ids));
    }

    /**
     * Get formatted time window
     */
    public function getTimeWindowAttribute(): ?string
    {
        if (!$this->active_time_start || !$this->active_time_end) {
            return null;
        }

        return sprintf(
            '%s - %s',
            Carbon::parse($this->active_time_start)->format('H:i'),
            Carbon::parse($this->active_time_end)->format('H:i')
        );
    }

    /**
     * Get formatted active days
     */
    public function getActiveDaysFormattedAttribute(): ?string
    {
        if (!$this->active_days) {
            return null;
        }

        return implode(', ', array_map('ucfirst', $this->active_days));
    }
}
