<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShiftSwapRequest extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'requester_assignment_id',
        'target_assignment_id',
        'requester_id',
        'target_user_id',
        'reason',
        'manager_id',
        'manager_note',
        'swapped_at',
    ];

    protected $casts = [
        'swapped_at' => 'datetime',
    ];

    // ========================================
    // RELATIONSHIPS
    // ========================================

    public function requesterAssignment(): BelongsTo
    {
        return $this->belongsTo(ShiftAssignment::class, 'requester_assignment_id');
    }

    public function targetAssignment(): BelongsTo
    {
        return $this->belongsTo(ShiftAssignment::class, 'target_assignment_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    // ========================================
    // SCOPES
    // ========================================

    public function scopeForUser($query, int $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('requester_id', $userId)
                ->orWhere('target_user_id', $userId);
        });
    }

    public function scopeSwapped($query)
    {
        return $query->whereNotNull('swapped_at');
    }

    public function scopeRecent($query)
    {
        return $query->orderBy('swapped_at', 'desc');
    }

    // ========================================
    // ACCESSORS
    // ========================================

    public function getIsSwappedAttribute(): bool
    {
        return $this->swapped_at !== null;
    }
}
