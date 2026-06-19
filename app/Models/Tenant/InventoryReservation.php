<?php

namespace App\Models\Tenant;

use App\Enums\Tenant\ReservationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class InventoryReservation extends Model
{
    use HasFactory;

    protected $table = 'inventory_reservations';

    protected $fillable = [
        'inventory_id',
        'reference_type',
        'reference_id',
        'quantity_reserved',
        'reserved_until',
        'status',
        'cancellation_reason',
        'cancelled_by',
    ];

    protected $casts = [
        'quantity_reserved' => 'decimal:4',
        'reserved_until' => 'datetime',
        'status' => ReservationStatus::class,
    ];

    // ============================================
    // RELATIONSHIPS
    // ============================================

    public function inventory(): BelongsTo
    {
        return $this->belongsTo(Inventory::class);
    }

    /**
     * Get the related order/transaction (polymorphic)
     */
    public function reference(): MorphTo
    {
        return $this->morphTo('reference', 'reference_type', 'reference_id');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    // ============================================
    // SCOPES
    // ============================================

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', ReservationStatus::ACTIVE);
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('status', ReservationStatus::ACTIVE)
            ->where('reserved_until', '<', now());
    }

    public function scopeByStore(Builder $query, int $storeId): Builder
    {
        return $query->whereHas('inventory', function ($q) use ($storeId) {
            $q->where('store_id', $storeId);
        });
    }

    public function scopeByProduct(Builder $query, int $productId): Builder
    {
        return $query->whereHas('inventory', function ($q) use ($productId) {
            $q->where('product_id', $productId);
        });
    }

    public function scopeWithDetails(Builder $query): Builder
    {
        return $query->with([
            'inventory.product:id,name,sku,base_uom_id',
            'inventory.product.baseUom:id,code,name',
            'inventory.productVariant:id,variant_name,sku',
            'inventory.store:id,name,code',
            'cancelledBy:id,name',
        ]);
    }

    // ============================================
    // ACCESSORS 
    // ============================================

    public function getIsExpiredAttribute(): bool
    {
        return $this->status === ReservationStatus::ACTIVE
            && $this->reserved_until < now();
    }

    public function getIsActiveAttribute(): bool
    {
        return $this->status === ReservationStatus::ACTIVE
            && $this->reserved_until >= now();
    }

    public function getCanBeCancelledAttribute(): bool
    {
        return $this->status === ReservationStatus::ACTIVE;
    }

    public function getRemainingMinutesAttribute(): ?int
    {
        if (!$this->is_active) {
            return null;
        }

        return max(0, now()->diffInMinutes($this->reserved_until, false));
    }

    // ============================================
    // HELPER METHODS 
    // ============================================

    public function shouldExpire(): bool
    {
        return $this->status === ReservationStatus::ACTIVE
            && $this->reserved_until < now();
    }

    public function markAsExpired(): bool
    {
        $this->status = ReservationStatus::EXPIRED;
        return $this->save();
    }

    public function markAsFulfilled(): bool
    {
        $this->status = ReservationStatus::FULFILLED;
        return $this->save();
    }

    public function cancel(string $reason, ?int $cancelledBy = null): bool
    {
        $this->status = ReservationStatus::CANCELLED;
        $this->cancellation_reason = $reason;
        $this->cancelled_by = $cancelledBy ?? Auth::id();
        return $this->save();
    }

    public function getReferenceDetails(): array
    {
        return [
            'type' => class_basename($this->reference_type),
            'id' => $this->reference_id,
        ];
    }
}
