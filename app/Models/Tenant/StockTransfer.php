<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Observers\Tenant\StockTransferObserver;
use App\Traits\Tenant\HasAuditLogging;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;

#[ObservedBy([StockTransferObserver::class])]
class StockTransfer extends Model
{
    use HasFactory, HasAuditLogging;

    protected $fillable = [
        'transfer_number',
        'from_store_id',
        'to_store_id',
        'status', // pending, approved, in_transit, completed, cancelled
        'transfer_date',
        'expected_arrival_date',
        'actual_arrival_date',
        'requested_by',
        'approved_by',
        'approved_at',
        'sent_by',
        'sent_at',
        'received_by',
        'received_at',
        'notes',
        'rejection_reason',
    ];

    protected $casts = [
        'transfer_date' => 'date',
        'expected_arrival_date' => 'date',
        'actual_arrival_date' => 'date',
        'approved_at' => 'datetime',
        'sent_at' => 'datetime',
        'received_at' => 'datetime',
    ];

    /**
     * RELATIONSHIPS
     */

    public function fromStore(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'from_store_id');
    }

    public function toStore(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'to_store_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockTransferItem::class, 'transfer_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /**
     * SCOPES
     */

    public function scopeByStore($query, int $storeId, string $direction = 'all')
    {
        if ($direction === 'outbound') {
            return $query->where('from_store_id', $storeId);
        } elseif ($direction === 'inbound') {
            return $query->where('to_store_id', $storeId);
        }

        return $query->where(function ($q) use ($storeId) {
            $q->where('from_store_id', $storeId)
                ->orWhere('to_store_id', $storeId);
        });
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeInTransit($query)
    {
        return $query->where('status', 'in_transit');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * ACCESSORS
     */

    public function getIsPendingAttribute(): bool
    {
        return $this->status === 'pending';
    }

    public function getIsApprovedAttribute(): bool
    {
        return $this->status === 'approved';
    }

    public function getIsInTransitAttribute(): bool
    {
        return $this->status === 'in_transit';
    }

    public function getIsCompletedAttribute(): bool
    {
        return $this->status === 'completed';
    }

    public function getIsCancelledAttribute(): bool
    {
        return $this->status === 'cancelled';
    }

    public function getCanBeCancelledAttribute(): bool
    {
        return in_array($this->status, ['pending', 'approved']);
    }

    public function getCanBeApprovedAttribute(): bool
    {
        return $this->status === 'pending';
    }

    public function getCanBeSentAttribute(): bool
    {
        return $this->status === 'approved';
    }

    public function getCanBeReceivedAttribute(): bool
    {
        return $this->status === 'in_transit';
    }
}
