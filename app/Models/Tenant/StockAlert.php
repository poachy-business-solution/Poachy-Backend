<?php

namespace App\Models\Tenant;

use App\Enums\Tenant\StockAlertType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class StockAlert extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'product_id',
        'product_variant_id',
        'alert_type',
        'current_quantity',
        'threshold_quantity',
        'is_resolved',
        'resolved_at',
        'resolved_by',
        'notified_users',
        'notes',
    ];

    protected $casts = [
        'alert_type' => StockAlertType::class,
        'current_quantity' => 'decimal:4',
        'threshold_quantity' => 'decimal:4',
        'is_resolved' => 'boolean',
        'resolved_at' => 'datetime',
        'notified_users' => 'array',
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

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    // ============================================
    // SCOPES
    // ============================================

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_resolved', false);
    }

    public function scopeResolved(Builder $query): Builder
    {
        return $query->where('is_resolved', true);
    }

    public function scopeByStore(Builder $query, int $storeId): Builder
    {
        return $query->where('store_id', $storeId);
    }

    public function scopeByProduct(Builder $query, int $productId): Builder
    {
        return $query->where('product_id', $productId);
    }

    public function scopeByType(Builder $query, StockAlertType|string $type): Builder
    {
        $typeValue = $type instanceof StockAlertType ? $type->value : $type;
        return $query->where('alert_type', $typeValue);
    }

    public function scopeLowStock(Builder $query): Builder
    {
        return $query->where('alert_type', StockAlertType::LOW_STOCK);
    }

    public function scopeOutOfStock(Builder $query): Builder
    {
        return $query->where('alert_type', StockAlertType::OUT_OF_STOCK);
    }

    public function scopeWithDetails(Builder $query): Builder
    {
        return $query->with([
            'product:id,name,slug,sku,base_uom_id,reorder_level,primary_image',
            'product.baseUom:id,code,name',
            'productVariant:id,product_id,variant_name,sku',
            'store:id,name,code',
            'resolvedBy:id,name,email',
        ]);
    }

    public function scopeRecent(Builder $query): Builder
    {
        return $query->orderBy('created_at', 'desc');
    }

    // ============================================
    // ACCESSORS
    // ============================================

    public function getDisplayNameAttribute(): string
    {
        if ($this->productVariant) {
            return "{$this->product->name} - {$this->productVariant->variant_name}";
        }

        return $this->product->name;
    }

    public function getSeverityAttribute(): string
    {
        return $this->alert_type === StockAlertType::OUT_OF_STOCK ? 'critical' : 'warning';
    }

    public function getAgeInDaysAttribute(): int
    {
        return $this->created_at->diffInDays(now());
    }

    // ============================================
    // HELPER METHODS
    // ============================================

    public function resolve(?string $notes = null, ?int $userId = null): bool
    {
        return $this->update([
            'is_resolved' => true,
            'resolved_at' => now(),
            'resolved_by' => $userId ?? Auth::id(),
            'notes' => $notes ?? $this->notes,
        ]);
    }

    public function markAsNotified(array $userIds): void
    {
        $this->update([
            'notified_users' => array_unique(array_merge($this->notified_users ?? [], $userIds)),
        ]);
    }

    public function wasUserNotified(int $userId): bool
    {
        return in_array($userId, $this->notified_users ?? []);
    }

    /**
     * Check if alert is still valid (should remain active)
     */
    public function isStillValid(): bool
    {
        if ($this->is_resolved) {
            return false;
        }

        $inventory = Inventory::getForProduct(
            $this->product_id,
            $this->store_id,
            $this->product_variant_id
        );

        if (!$inventory) {
            return false;
        }

        return match ($this->alert_type) {
            StockAlertType::LOW_STOCK => $inventory->quantity_available <= $this->threshold_quantity && $inventory->quantity_available > 0,
            StockAlertType::OUT_OF_STOCK => $inventory->quantity_available <= 0,
        };
    }
}
