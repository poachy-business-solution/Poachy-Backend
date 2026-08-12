<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ProductBarcodeSuggestion extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'suggested_barcodeable_type',
        'suggested_barcodeable_id',
        'barcode',
        'barcode_type',
        'status',
        'is_primary',
        'supplier_id',
        'region',
        'store_id',
        'metadata',
        'notes',
        'submitted_by',
        'reviewed_by',
        'reviewed_at',
        'rejection_reason',
        'approved_barcode_id',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'metadata' => 'array',
        'reviewed_at' => 'datetime',
    ];

    protected $attributes = [
        'barcode_type' => 'INTERNAL',
        'status' => self::STATUS_PENDING,
        'is_primary' => false,
    ];

    public function suggestedBarcodeable(): MorphTo
    {
        return $this->morphTo();
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function approvedBarcode(): BelongsTo
    {
        return $this->belongsTo(ProductBarcode::class, 'approved_barcode_id');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }
}
