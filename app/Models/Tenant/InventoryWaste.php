<?php

namespace App\Models\Tenant;

use App\Enums\Tenant\WasteType;
use App\Enums\Tenant\WasteApprovalStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class InventoryWaste extends Model
{
    use HasFactory;

    protected $table = 'inventory_waste';

    protected $fillable = [
        'store_id',
        'product_id',
        'batch_id',
        'waste_type',
        'quantity_wasted',
        'cost_per_base_uom',
        'total_loss',
        'waste_date',
        'reason',
        'approval_status',
        'reported_by',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'waste_type' => WasteType::class,
        'approval_status' => WasteApprovalStatus::class,
        'quantity_wasted' => 'decimal:4',
        'cost_per_base_uom' => 'decimal:2',
        'total_loss' => 'decimal:2',
        'waste_date' => 'date',
        'approved_at' => 'datetime',
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

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ProductBatch::class, 'batch_id');
    }

    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // ============================================
    // SCOPES
    // ============================================

    public function scopePending(Builder $query): Builder
    {
        return $query->where('approval_status', WasteApprovalStatus::PENDING);
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('approval_status', WasteApprovalStatus::APPROVED);
    }

    public function scopeRejected(Builder $query): Builder
    {
        return $query->where('approval_status', WasteApprovalStatus::REJECTED);
    }

    public function scopeByStore(Builder $query, int $storeId): Builder
    {
        return $query->where('store_id', $storeId);
    }

    public function scopeByProduct(Builder $query, int $productId): Builder
    {
        return $query->where('product_id', $productId);
    }

    public function scopeByType(Builder $query, WasteType|string $type): Builder
    {
        $typeValue = $type instanceof WasteType ? $type->value : $type;
        return $query->where('waste_type', $typeValue);
    }

    public function scopeByDateRange(Builder $query, ?string $fromDate = null, ?string $toDate = null): Builder
    {
        if ($fromDate) {
            $query->whereDate('waste_date', '>=', $fromDate);
        }

        if ($toDate) {
            $query->whereDate('waste_date', '<=', $toDate);
        }

        return $query;
    }

    public function scopeWithDetails(Builder $query): Builder
    {
        return $query->with([
            'product:id,name,slug,sku,base_uom_id,primary_image',
            'product.baseUom:id,code,name',
            'batch:id,batch_number,expiry_date',
            'store:id,name,code',
            'reportedBy:id,name,email',
            'approvedBy:id,name,email',
        ]);
    }

    public function scopeRecent(Builder $query): Builder
    {
        return $query->orderBy('waste_date', 'desc');
    }

    // ============================================
    // ACCESSORS
    // ============================================

    public function getIsPendingAttribute(): bool
    {
        return $this->approval_status === WasteApprovalStatus::PENDING;
    }

    public function getIsApprovedAttribute(): bool
    {
        return $this->approval_status === WasteApprovalStatus::APPROVED;
    }

    public function getIsRejectedAttribute(): bool
    {
        return $this->approval_status === WasteApprovalStatus::REJECTED;
    }

    public function getCanBeApprovedAttribute(): bool
    {
        return $this->approval_status->canBeApproved();
    }

    public function getCanBeRejectedAttribute(): bool
    {
        return $this->approval_status->canBeRejected();
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->product->name;
    }

    public function getAgeInDaysAttribute(): int
    {
        return $this->waste_date->diffInDays(now());
    }

    // ============================================
    // HELPER METHODS
    // ============================================

    public function approve(int $userId): bool
    {
        if (!$this->can_be_approved) {
            throw new \RuntimeException('Waste record cannot be approved in current status');
        }

        return $this->update([
            'approval_status' => WasteApprovalStatus::APPROVED,
            'approved_by' => $userId,
            'approved_at' => now(),
        ]);
    }

    public function reject(int $userId, ?string $reason = null): bool
    {
        if (!$this->can_be_rejected) {
            throw new \RuntimeException('Waste record cannot be rejected in current status');
        }

        return $this->update([
            'approval_status' => WasteApprovalStatus::REJECTED,
            'approved_by' => $userId,
            'approved_at' => now(),
            'reason' => $reason ?? $this->reason,
        ]);
    }

    /**
     * Calculate financial impact
     */
    public function calculateTotalLoss(): void
    {
        $totalLoss = $this->quantity_wasted * $this->cost_per_base_uom;
        $this->update(['total_loss' => $totalLoss]);
    }
}
