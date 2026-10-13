<?php

namespace App\Models\Tenant;

use App\Observers\Tenant\SaleItemObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ObservedBy([SaleItemObserver::class])]
class SaleItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'sale_items';

    protected $fillable = [
        'sale_id',
        'product_id',
        'product_variant_id',
        'bundle_id',
        'description',
        'uom_id',
        'quantity',
        'quantity_in_base_uom',
        'unit_price',
        'list_unit_price',
        'is_giveaway',
        'price_override_reason',
        'unit_cost',
        'discount_amount',
        'tax_rate_id',
        'tax_amount',
        'subtotal',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'quantity_in_base_uom' => 'decimal:4',
        'unit_price' => 'decimal:2',
        'list_unit_price' => 'decimal:2',
        'is_giveaway' => 'boolean',
        'unit_cost' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    protected $appends = [
        'line_total_before_tax',
        'effective_unit_price',
        'profit',
    ];

    // ============================================
    // RELATIONSHIPS
    // ============================================

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function bundle(): BelongsTo
    {
        return $this->belongsTo(ProductBundle::class, 'bundle_id');
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'uom_id');
    }

    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class, 'tax_rate_id');
    }

    // ============================================
    // ACCESSORS
    // ============================================

    /**
     * Get line total before tax and discount
     */
    public function getLineTotalBeforeTaxAttribute(): float
    {
        return $this->quantity * $this->unit_price;
    }

    /**
     * Get effective unit price after discount
     */
    public function getEffectiveUnitPriceAttribute(): float
    {
        if ($this->quantity == 0) {
            return 0;
        }

        return ($this->line_total_before_tax - $this->discount_amount) / $this->quantity;
    }

    /**
     * Whether what this line cost the shop is known. Open (unlisted) items and
     * products sold before any buying price was recorded have no cost.
     */
    public function getCostKnownAttribute(): bool
    {
        return $this->unit_cost !== null && ! $this->isOpenItem();
    }

    /**
     * Profit for this line (revenue after discount and tax minus cost), or null
     * when the cost is unknown — never 100% by default.
     */
    public function getProfitAttribute(): ?float
    {
        if (! $this->cost_known) {
            return null;
        }

        return round((float) $this->subtotal - ((float) $this->unit_cost * (float) $this->quantity), 2);
    }

    /**
     * Profit as a share of what the customer paid for the line, in percent.
     */
    public function getProfitMarginAttribute(): ?float
    {
        $profit = $this->profit;

        if ($profit === null || (float) $this->subtotal <= 0) {
            return null;
        }

        return ($profit / (float) $this->subtotal) * 100;
    }

    /**
     * Get display name (product or variant name)
     */
    public function getDisplayNameAttribute(): string
    {
        if ($this->isOpenItem()) {
            return $this->description ?? 'Unlisted item';
        }

        if ($this->bundle_id) {
            return $this->bundle->bundle_name ?? 'Unknown Bundle';
        }

        if ($this->productVariant) {
            return "{$this->product->name} - {$this->productVariant->variant_name}";
        }

        return $this->product->name;
    }

    // ============================================
    // HELPER METHODS
    // ============================================

    /**
     * What the customer actually paid per unit, after discounts and including tax.
     */
    public function netUnitPrice(): float
    {
        return (float) $this->quantity > 0
            ? (float) $this->subtotal / (float) $this->quantity
            : (float) $this->unit_price;
    }

    /**
     * An unlisted item rung up by description and price, with no product or bundle.
     */
    public function isOpenItem(): bool
    {
        return $this->product_id === null && $this->bundle_id === null;
    }

    /**
     * Check if item is a bundle
     */
    public function isBundle(): bool
    {
        return $this->bundle_id !== null;
    }

    /**
     * Check if item has variant
     */
    public function hasVariant(): bool
    {
        return $this->product_variant_id !== null;
    }

    /**
     * Check if item has discount applied
     */
    public function hasDiscount(): bool
    {
        return $this->discount_amount > 0;
    }

    /**
     * Get discount percentage
     */
    public function getDiscountPercentage(): float
    {
        if ($this->line_total_before_tax == 0) {
            return 0;
        }

        return ($this->discount_amount / $this->line_total_before_tax) * 100;
    }

    /**
     * Calculate subtotal manually (for verification)
     */
    public function calculateSubtotal(): float
    {
        // Subtotal = (quantity × unit_price) - discount + tax
        $lineTotal = $this->quantity * $this->unit_price;
        $afterDiscount = $lineTotal - $this->discount_amount;
        $subtotal = $afterDiscount + $this->tax_amount;

        return round($subtotal, 2);
    }

    /**
     * Verify subtotal integrity
     */
    public function verifySubtotal(): bool
    {
        return abs($this->subtotal - $this->calculateSubtotal()) < 0.01; // Allow 1 cent tolerance
    }
}
