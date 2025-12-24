<?php

namespace App\Models\Tenant;

use App\Enums\Tenant\InventoryMovementType;
use App\Observers\Tenant\InventoryMovementObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

#[ObservedBy([InventoryMovementObserver::class])]
class InventoryMovement extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'inventory_movements';

    protected $fillable = [
        'store_id',
        'product_id',
        'product_variant_id',
        'movement_type',
        'uom_id',
        'quantity',
        'quantity_in_base_uom',
        'unit_cost',
        'unit_cost_in_base_uom',
        'total_cost',
        'reference_type',
        'reference_id',
        'balance_after',
        'notes',
        'created_by_user',
    ];

    protected $casts = [
        'movement_type' => InventoryMovementType::class,
        'quantity' => 'decimal:4',
        'quantity_in_base_uom' => 'decimal:4',
        'unit_cost' => 'decimal:2',
        'unit_cost_in_base_uom' => 'decimal:2',
        'total_cost' => 'decimal:2',
        'balance_after' => 'decimal:4',
    ];

    // ============================================
    // RELATIONSHIPS
    // ============================================

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'uom_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user');
    }

    /**
     * Get the source transaction (polymorphic)
     */
    public function reference(): MorphTo
    {
        return $this->morphTo('reference', 'reference_type', 'reference_id');
    }

    // ============================================
    // SCOPES
    // ============================================

    public function scopeByStore(Builder $query, int $storeId): Builder
    {
        return $query->where('store_id', $storeId);
    }

    public function scopeByProduct(Builder $query, int $productId): Builder
    {
        return $query->where('product_id', $productId);
    }

    public function scopeByVariant(Builder $query, ?int $variantId = null): Builder
    {
        return $query->where('product_variant_id', $variantId);
    }

    /**
     * Scope to filter by movement type
     */
    public function scopeByType(Builder $query, InventoryMovementType|string $type): Builder
    {
        $typeValue = $type instanceof InventoryMovementType ? $type->value : $type;
        return $query->where('movement_type', $typeValue);
    }

    public function scopeByDateRange(Builder $query, ?string $fromDate = null, ?string $toDate = null): Builder
    {
        if ($fromDate) {
            $query->whereDate('created_at', '>=', $fromDate);
        }

        if ($toDate) {
            $query->whereDate('created_at', '<=', $toDate);
        }

        return $query;
    }

    public function scopePositive(Builder $query): Builder
    {
        return $query->where('quantity_in_base_uom', '>', 0);
    }

    public function scopeNegative(Builder $query): Builder
    {
        return $query->where('quantity_in_base_uom', '<', 0);
    }

    public function scopeWithDetails(Builder $query): Builder
    {
        return $query->with([
            'product:id,name,slug,sku,base_uom_id',
            'product.baseUom:id,code,name',
            'productVariant:id,product_id,variant_name,sku',
            'store:id,name,code',
            'uom:id,code,name,type',
            'createdByUser:id,name,email',
        ]);
    }

    public function scopeRecent(Builder $query): Builder
    {
        return $query->orderBy('created_at', 'desc');
    }

    // ============================================
    // ACCESSORS
    // ============================================

    public function getIsPositiveAttribute(): bool
    {
        return $this->quantity_in_base_uom > 0;
    }

    public function getIsNegativeAttribute(): bool
    {
        return $this->quantity_in_base_uom < 0;
    }

    public function getFormattedQuantityAttribute(): string
    {
        $sign = $this->is_positive ? '+' : '';
        return "{$sign}{$this->quantity} {$this->uom->code}";
    }

    public function getFormattedBaseQuantityAttribute(): string
    {
        $sign = $this->is_positive ? '+' : '';
        $baseUom = $this->product->baseUom->code ?? 'units';
        return "{$sign}{$this->quantity_in_base_uom} {$baseUom}";
    }

    public function getDirectionAttribute(): string
    {
        return $this->is_positive ? 'in' : 'out';
    }

    // ============================================
    // HELPER METHODS 
    // ============================================

    public function getSourceTransactionDetails(): ?array
    {
        if (!$this->reference_type || !$this->reference_id) {
            return null;
        }

        return [
            'type' => class_basename($this->reference_type),
            'id' => $this->reference_id,
            'reference' => $this->reference,
        ];
    }

    public function hasCostData(): bool
    {
        return $this->unit_cost_in_base_uom !== null && $this->total_cost !== null;
    }

    public function getAbsoluteQuantity(): float
    {
        return abs($this->quantity_in_base_uom);
    }
}
