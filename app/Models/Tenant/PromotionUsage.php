<?php

namespace App\Models\Tenant;

use App\Observers\Tenant\PromotionUsageObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromotionUsage extends Model
{
    use HasFactory;

    protected $table = 'promotion_usage';

    protected $fillable = [
        'promotion_id',
        'customer_id',
        'sale_id',
        'discount_applied',
        'promotion_details',
        'used_at',
    ];

    protected $casts = [
        'discount_applied' => 'decimal:2',
        'promotion_details' => 'array',
        'used_at' => 'datetime',
    ];

    // ============================================
    // RELATIONSHIPS
    // ============================================

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }
}
