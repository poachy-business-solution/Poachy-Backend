<?php

namespace App\Models\Tenant;

use App\Enums\Tenant\RefundMethod;
use App\Enums\Tenant\RefundReason;
use App\Enums\Tenant\RefundStatus;
use App\Observers\Tenant\SaleRefundObserver;
use App\Traits\Tenant\HasAuditLogging;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy([SaleRefundObserver::class])]
class SaleRefund extends Model
{
    use HasFactory, HasAuditLogging;

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
        'status',
        'approved_by',
        'approved_at',
        'processed_at',
        'exchange_sale_id',
    ];

    protected function casts(): array
    {
        return [
            'refund_date' => 'date',
            'refund_amount' => 'decimal:2',
            'refund_method' => RefundMethod::class,
            'reason' => RefundReason::class,
            'status' => RefundStatus::class,
            'approved_at' => 'datetime',
            'processed_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    // ============================================
    // AUDIT LOGGING
    // ============================================

    public function getAuditableFields(): array
    {
        return [
            'refund_number',
            'original_sale_id',
            'refund_amount',
            'refund_method',
            'reason',
            'status',
            'processed_by',
            'approved_by',
        ];
    }

    public function getCriticalFields(): array
    {
        return [
            'refund_amount',
            'status',
            'refund_method',
            'reason',
        ];
    }

    // ============================================
    // BOOT
    // ============================================

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($refund) {
            if (!$refund->refund_number) {
                $refund->refund_number = self::generateRefundNumber($refund->store_id);
            }

            if (!$refund->refund_date) {
                $refund->refund_date = now()->toDateString();
            }
        });
    }

    // ============================================
    // RELATIONSHIPS
    // ============================================

    public function originalSale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'original_sale_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function exchangeSale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'exchange_sale_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleRefundItem::class, 'refund_id');
    }

    // ============================================
    // ACCESSORS
    // ============================================

    public function getFormattedAmountAttribute(): string
    {
        return 'KES ' . number_format($this->refund_amount, 2);
    }

    public function getRefundMethodLabelAttribute(): string
    {
        return $this->refund_method->label();
    }

    public function getReasonLabelAttribute(): string
    {
        return $this->reason->label();
    }

    public function getProcessorNameAttribute(): ?string
    {
        return $this->processedBy?->name;
    }

    public function getTotalItemsCountAttribute(): int
    {
        return $this->items()->count();
    }

    public function getTotalQuantityRefundedAttribute(): float
    {
        return (float) $this->items()->sum('quantity_refunded');
    }

    // ============================================
    // SCOPES
    // ============================================

    public function scopeByStore(Builder $query, int $storeId): Builder
    {
        return $query->where('store_id', $storeId);
    }

    public function scopeByCustomer(Builder $query, int $customerId): Builder
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeBySale(Builder $query, int $saleId): Builder
    {
        return $query->where('original_sale_id', $saleId);
    }

    public function scopeByMethod(Builder $query, RefundMethod $method): Builder
    {
        return $query->where('refund_method', $method);
    }

    public function scopeByReason(Builder $query, RefundReason $reason): Builder
    {
        return $query->where('reason', $reason);
    }

    public function scopeByStatus(Builder $query, RefundStatus $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', RefundStatus::COMPLETED);
    }

    public function scopeByDateRange(Builder $query, string $startDate, string $endDate): Builder
    {
        return $query->whereBetween('refund_date', [
            Carbon::parse($startDate)->startOfDay(),
            Carbon::parse($endDate)->endOfDay(),
        ]);
    }

    public function scopeToday(Builder $query): Builder
    {
        return $query->whereDate('refund_date', today());
    }

    public function scopeThisWeek(Builder $query): Builder
    {
        return $query->whereBetween('refund_date', [
            now()->startOfWeek(),
            now()->endOfWeek(),
        ]);
    }

    public function scopeThisMonth(Builder $query): Builder
    {
        return $query->whereMonth('refund_date', now()->month)
            ->whereYear('refund_date', now()->year);
    }

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

    public static function generateRefundNumber(?int $storeId = null): string
    {
        $year = now()->year;
        $month = str_pad(now()->month, 2, '0', STR_PAD_LEFT);
        $storePrefix = $storeId ? str_pad($storeId, 2, '0', STR_PAD_LEFT) : '00';

        $count = self::whereYear('created_at', $year)->count() + 1;
        $sequence = str_pad($count, 6, '0', STR_PAD_LEFT);

        return "REF-{$storePrefix}-{$year}{$month}-{$sequence}";
    }

    public static function getStatistics(string $startDate, string $endDate, ?int $storeId = null): array
    {
        $query = self::completed()->byDateRange($startDate, $endDate);

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

    public static function getSummaryByReason(string $startDate, string $endDate): array
    {
        return self::completed()
            ->byDateRange($startDate, $endDate)
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
