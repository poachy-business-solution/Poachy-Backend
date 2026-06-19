<?php

namespace App\Models\Tenant;

use App\Observers\Tenant\ProductBundleObserver;
use App\Traits\Tenant\HasAuditLogging;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ObservedBy([ProductBundleObserver::class])]
class ProductBundle extends Model
{
    use HasFactory, HasAuditLogging, SoftDeletes;

    protected $table = 'product_bundles';

    protected $fillable = [
        'uuid',
        'bundle_name',
        'bundle_sku',
        'description',
        'images',
        'base_uom_id',
        'bundle_price',
        'calculated_individual_price',
        'discount_amount',
        'tax_rate_id',
        'is_available_online',
        'is_active',
        'online_price',
        'online_description',
    ];

    protected $casts = [
        'images' => 'array',
        'bundle_price' => 'decimal:2',
        'calculated_individual_price' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'online_price' => 'decimal:2',
        'is_available_online' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected $attributes = [
        'is_available_online' => false,
        'is_active' => true,
    ];

    // Relationships

    public function baseUom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'base_uom_id');
    }

    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class, 'tax_rate_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProductBundleItem::class, 'bundle_id');
    }

    // Scopes

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOnline($query)
    {
        return $query->where('is_available_online', true);
    }

    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('bundle_name', 'like', "%{$term}%")
                ->orWhere('bundle_sku', 'like', "%{$term}%")
                ->orWhere('description', 'like', "%{$term}%");
        });
    }

    // Accessors & Mutators

    public function getFormattedBundlePriceAttribute(): string
    {
        return 'KES ' . number_format($this->bundle_price, 2);
    }

    public function getFormattedOnlinePriceAttribute(): ?string
    {
        return $this->online_price
            ? 'KES ' . number_format($this->online_price, 2)
            : null;
    }

    public function getFormattedDiscountAttribute(): ?string
    {
        return $this->discount_amount
            ? 'KES ' . number_format($this->discount_amount, 2)
            : null;
    }

    public function getSavingsPercentageAttribute(): ?float
    {
        if (!$this->calculated_individual_price || $this->calculated_individual_price == 0) {
            return null;
        }

        $savings = $this->discount_amount ?? 0;
        return round(($savings / $this->calculated_individual_price) * 100, 2);
    }

    public function getImageCountAttribute(): int
    {
        return count($this->images ?? []);
    }

    public function getPrimaryImageAttribute(): ?string
    {
        $images = $this->images ?? [];
        return $images[0] ?? null;
    }

    // Helper Methods

    public function isActive(): bool
    {
        return $this->is_active === true;
    }

    public function isAvailableOnline(): bool
    {
        return $this->is_available_online === true;
    }

    /**
     * Check if bundle has minimum required items (2+)
     */
    public function hasMinimumItems(): bool
    {
        return $this->items()->count() >= 2;
    }

    /**
     * Check if all component products are active
     */
    public function allItemsActive(): bool
    {
        return $this->items()
            ->whereHas('product', fn($q) => $q->where('is_active', false))
            ->doesntExist();
    }

    /**
     * Check if bundle is available for sale
     */
    public function isAvailableForSale(): bool
    {
        return $this->is_active
            && $this->hasMinimumItems()
            && $this->allItemsActive();
    }

    /**
     * Calculate total individual price of all items
     */
    public function calculateIndividualPrice(): float
    {
        $total = 0;

        foreach ($this->items as $item) {
            $price = $item->product_variant_id
                ? ($item->variant?->computed_price ?? 0)
                : ($item->product?->base_selling_price ?? 0);

            $total += $price * $item->quantity;
        }

        return round($total, 2);
    }

    /**
     * Calculate savings amount
     */
    public function calculateSavings(): float
    {
        $individualPrice = $this->calculated_individual_price ?? $this->calculateIndividualPrice();
        return max(0, $individualPrice - $this->bundle_price);
    }

    /**
     * Recalculate pricing fields
     */
    public function recalculatePricing(): void
    {
        $this->calculated_individual_price = $this->calculateIndividualPrice();
        $this->discount_amount = $this->calculateSavings();
        $this->save();
    }
}
