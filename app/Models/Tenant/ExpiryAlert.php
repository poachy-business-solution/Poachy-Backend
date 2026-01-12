<?php

namespace App\Models\Tenant;

use App\Enums\Tenant\ExpiryAlertLevel;
use App\Enums\Tenant\ResolutionAction;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ExpiryAlert extends Model
{
    use HasFactory;

    protected $fillable = [
        'batch_id',
        'alert_level',
        'alert_date',
        'days_until_expiry',
        'is_resolved',
        'resolution_action',
        'resolved_at',
        'resolved_by',
        'notes',
    ];

    protected $casts = [
        'alert_level' => ExpiryAlertLevel::class,
        'alert_date' => 'date',
        'is_resolved' => 'boolean',
        'resolution_action' => ResolutionAction::class,
        'resolved_at' => 'datetime',
    ];

    // ============================================
    // RELATIONSHIPS
    // ============================================

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ProductBatch::class, 'batch_id');
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

    public function scopeByLevel(Builder $query, ExpiryAlertLevel|string $level): Builder
    {
        $levelValue = $level instanceof ExpiryAlertLevel ? $level->value : $level;
        return $query->where('alert_level', $levelValue);
    }

    public function scopeWarning(Builder $query): Builder
    {
        return $query->where('alert_level', ExpiryAlertLevel::WARNING);
    }

    public function scopeUrgent(Builder $query): Builder
    {
        return $query->where('alert_level', ExpiryAlertLevel::URGENT);
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('alert_level', ExpiryAlertLevel::EXPIRED);
    }

    public function scopeByStore(Builder $query, int $storeId): Builder
    {
        return $query->whereHas('batch', function ($q) use ($storeId) {
            $q->where('store_id', $storeId);
        });
    }

    public function scopeWithDetails(Builder $query): Builder
    {
        return $query->with([
            'batch:id,batch_number,product_id,product_variant_id,store_id,quantity_remaining_in_base_uom,expiry_date,is_expired',
            'batch.product:id,name,slug,sku,base_uom_id,primary_image',
            'batch.product.baseUom:id,code,name',
            'batch.productVariant:id,product_id,variant_name,sku',
            'batch.store:id,name,code',
            'resolvedBy:id,name,email',
        ]);
    }

    public function scopeRecent(Builder $query): Builder
    {
        return $query->orderBy('alert_date', 'desc');
    }

    public function scopeByPriority(Builder $query): Builder
    {
        return $query->orderByRaw("
            CASE alert_level
                WHEN 'expired' THEN 3
                WHEN 'urgent' THEN 2
                WHEN 'warning' THEN 1
            END DESC
        ")->orderBy('days_until_expiry', 'asc');
    }

    // ============================================
    // ACCESSORS
    // ============================================

    public function getSeverityAttribute(): string
    {
        return match ($this->alert_level) {
            ExpiryAlertLevel::EXPIRED => 'critical',
            ExpiryAlertLevel::URGENT => 'high',
            ExpiryAlertLevel::WARNING => 'medium',
        };
    }

    public function getAgeInDaysAttribute(): int
    {
        return $this->alert_date->diffInDays(now());
    }

    public function getDisplayNameAttribute(): string
    {
        $batch = $this->batch;
        $productName = $batch->product->name;

        if ($batch->productVariant) {
            $productName .= " - {$batch->productVariant->variant_name}";
        }

        return "{$productName} (Batch: {$batch->batch_number})";
    }

    // ============================================
    // HELPER METHODS
    // ============================================

    public function resolve(
        ResolutionAction $action,
        ?string $notes = null,
        ?int $userId = null
    ): bool {
        return $this->update([
            'is_resolved' => true,
            'resolution_action' => $action,
            'resolved_at' => now(),
            'resolved_by' => $userId ?? Auth::id(),
            'notes' => $notes ?? $this->notes,
        ]);
    }

    /**
     * Check if alert is still valid
     */
    public function isStillValid(): bool
    {
        if ($this->is_resolved) {
            return false;
        }

        $batch = $this->batch;

        if (!$batch || $batch->quantity_remaining_in_base_uom <= 0) {
            return false;
        }

        // Alert remains valid if batch still exists and has stock
        return true;
    }

    /**
     * Recalculate days until expiry
     */
    public function updateDaysUntilExpiry(): void
    {
        if ($this->batch && $this->batch->expiry_date) {
            $daysUntilExpiry = now()->diffInDays($this->batch->expiry_date, false);
            $this->update(['days_until_expiry' => $daysUntilExpiry]);
        }
    }
}
