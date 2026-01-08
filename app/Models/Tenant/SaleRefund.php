<?php

namespace App\Models\Tenant;

use App\Enums\Tenant\RefundMethod;
use App\Enums\Tenant\RefundReason;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class SaleRefund extends Model
{
    use HasFactory;

    protected $table = 'sale_refunds';

    protected $fillable = [
        'refund_number',
        'original_sale_id',
        'store_id',
        'customer_id',
        'refund_date',
        'refund_amount',
        'refund_method',
        'reason',
        'notes',
        'processed_by',
    ];

    protected $casts = [
        'refund_date' => 'date',
        'refund_amount' => 'decimal:2',
        'refund_method' => RefundMethod::class,
        'reason' => RefundReason::class,
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // ============================================
    // BOOT
    // ============================================

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($refund) {
            if (!$refund->refund_number) {
                $refund->refund_number = self::generateRefundNumber($refund->store_id);
            }
        });
    }

    // ============================================
    // RELATIONSHIPS
    // ============================================

    /**
     * Original sale being refunded
     */
    public function originalSale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'original_sale_id');
    }

    /**
     * Store where refund was processed
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Customer receiving the refund
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * User who processed the refund
     */
    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    /**
     * Refund line items
     */
    public function items(): HasMany
    {
        return $this->hasMany(SaleRefundItem::class, 'refund_id');
    }

    // ============================================
    // ACCESSORS
    // ============================================

    /**
     * Get formatted refund amount
     */
    public function getFormattedAmountAttribute(): string
    {
        return 'KES ' . number_format($this->refund_amount, 2);
    }

    /**
     * Get refund method label
     */
    public function getRefundMethodLabelAttribute(): string
    {
        return $this->refund_method->label();
    }

    /**
     * Get reason label
     */
    public function getReasonLabelAttribute(): string
    {
        return $this->reason->label();
    }

    /**
     * Get processor name
     */
    public function getProcessorNameAttribute(): ?string
    {
        return $this->processedByUser?->name;
    }

    /**
     * Get total items refunded count
     */
    public function getTotalItemsCountAttribute(): int
    {
        return $this->items()->count();
    }

    /**
     * Get total quantity refunded
     */
    public function getTotalQuantityRefundedAttribute(): float
    {
        return (float) $this->items()->sum('quantity_refunded');
    }

    // ============================================
    // SCOPES
    // ============================================

    /**
     * Scope to filter by store
     */
    public function scopeByStore(Builder $query, int $storeId): Builder
    {
        return $query->where('store_id', $storeId);
    }

    /**
     * Scope to filter by customer
     */
    public function scopeByCustomer(Builder $query, int $customerId): Builder
    {
        return $query->where('customer_id', $customerId);
    }

    /**
     * Scope to filter by original sale
     */
    public function scopeBySale(Builder $query, int $saleId): Builder
    {
        return $query->where('original_sale_id', $saleId);
    }

    /**
     * Scope to filter by refund method
     */
    public function scopeByMethod(Builder $query, RefundMethod $method): Builder
    {
        return $query->where('refund_method', $method);
    }

    /**
     * Scope to filter by reason
     */
    public function scopeByReason(Builder $query, RefundReason $reason): Builder
    {
        return $query->where('reason', $reason);
    }

    /**
     * Scope to get refunds within date range
     */
    public function scopeByDateRange(Builder $query, string $startDate, string $endDate): Builder
    {
        return $query->whereBetween('refund_date', [
            Carbon::parse($startDate)->startOfDay(),
            Carbon::parse($endDate)->endOfDay(),
        ]);
    }

    /**
     * Scope to get refunds from today
     */
    public function scopeToday(Builder $query): Builder
    {
        return $query->whereDate('refund_date', today());
    }

    /**
     * Scope to get refunds from this week
     */
    public function scopeThisWeek(Builder $query): Builder
    {
        return $query->whereBetween('refund_date', [
            now()->startOfWeek(),
            now()->endOfWeek(),
        ]);
    }

    /**
     * Scope to get refunds from this month
     */
    public function scopeThisMonth(Builder $query): Builder
    {
        return $query->whereMonth('refund_date', now()->month)
            ->whereYear('refund_date', now()->year);
    }

    /**
     * Scope to search by refund number
     */
    public function scopeSearch(Builder $query, string $search): Builder
    {
        return $query->where('refund_number', 'like', "%{$search}%")
            ->orWhereHas('customer', function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%");
            });
    }

    // ============================================
    // STATIC METHODS
    // ============================================

    /**
     * Generate refund number
     */
    public static function generateRefundNumber(int $storeId): string
    {
        $prefix = TenantSalesSettings::current()->refund_receipt_prefix ?? 'REF';
        $year = now()->year;

        $lastRefund = self::where('store_id', $storeId)
            ->whereYear('created_at', $year)
            ->orderByDesc('id')
            ->first();

        $nextNumber = $lastRefund ? ((int) substr($lastRefund->refund_number, -6)) + 1 : 1;

        return sprintf('%s-%d-%s-%06d', $prefix, $year, str_pad($storeId, 3, '0', STR_PAD_LEFT), $nextNumber);
    }

    /**
     * Get refund statistics for date range
     */
    public static function getStatistics(string $startDate, string $endDate, ?int $storeId = null): array
    {
        $query = self::byDateRange($startDate, $endDate);

        if ($storeId) {
            $query->byStore($storeId);
        }

        $refunds = $query->get();

        return [
            'total_refunds' => $refunds->count(),
            'total_amount' => $refunds->sum('refund_amount'),
            'average_amount' => $refunds->avg('refund_amount'),
            'total_items' => $refunds->sum(function ($refund) {
                return $refund->items()->count();
            }),
            'by_method' => $refunds->groupBy('refund_method')->map->count(),
            'by_reason' => $refunds->groupBy('reason')->map->count(),
        ];
    }

    /**
     * Get refunds summary by reason
     */
    public static function getSummaryByReason(string $startDate, string $endDate): array
    {
        return self::byDateRange($startDate, $endDate)
            ->select('reason')
            ->selectRaw('COUNT(*) as count')
            ->selectRaw('SUM(refund_amount) as total')
            ->groupBy('reason')
            ->get()
            ->map(function ($item) {
                return [
                    'reason' => $item->reason->label(),
                    'count' => $item->count,
                    'total' => $item->total,
                ];
            })
            ->toArray();
    }
}
