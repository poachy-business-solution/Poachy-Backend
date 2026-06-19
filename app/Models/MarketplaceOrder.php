<?php

namespace App\Models;

use App\Enums\Central\FulfillmentType;
use App\Enums\Central\MarketplacePaymentStatus;
use App\Enums\Central\OrderStatus;
use App\Enums\Central\ReservationStatus;
use App\Observers\Central\MarketplaceOrderObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ObservedBy([MarketplaceOrderObserver::class])]
class MarketplaceOrder extends Model
{
    use SoftDeletes;

    protected $connection = 'central';

    protected $table = 'marketplace_orders';

    protected $fillable = [
        'order_number',
        'customer_id',
        'delivery_address_id',
        'tenant_id',
        'merchant_name',
        'tenant_store_id',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'delivery_fee',
        'total_amount',
        'fulfillment_type',
        'order_status',
        'reservation_status',
        'reservation_expires_at',
        'reservation_confirmed_at',
        'reservation_failed_reason',
        'payment_deadline_at',
        'customer_notes',
        'merchant_notes',
        'cancellation_reason',
        'cancelled_at',
        'cancelled_by',
        'checkout_idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'subtotal'                  => 'decimal:2',
            'tax_amount'                => 'decimal:2',
            'discount_amount'           => 'decimal:2',
            'delivery_fee'              => 'decimal:2',
            'total_amount'              => 'decimal:2',
            'fulfillment_type'          => FulfillmentType::class,
            'order_status'              => OrderStatus::class,
            'reservation_status'        => ReservationStatus::class,
            'reservation_expires_at'    => 'datetime',
            'reservation_confirmed_at'  => 'datetime',
            'payment_deadline_at'       => 'datetime',
            'cancelled_at'              => 'datetime',
        ];
    }

    // =========================================================================
    // Relationships
    // =========================================================================

    public function customer(): BelongsTo
    {
        return $this->belongsTo(MarketplaceCustomer::class, 'customer_id');
    }

    public function deliveryAddress(): BelongsTo
    {
        return $this->belongsTo(CustomerAddress::class, 'delivery_address_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(MarketplaceOrderItem::class, 'order_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(MarketplaceOrderPayment::class, 'order_id');
    }

    public function delivery(): HasOne
    {
        return $this->hasOne(MarketplaceOrderDelivery::class, 'order_id');
    }

    // =========================================================================
    // Scopes
    // =========================================================================

    public function scopeByCustomer(Builder $query, int $customerId): Builder
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeByTenant(Builder $query, string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeByStatus(Builder $query, OrderStatus $status): Builder
    {
        return $query->where('order_status', $status);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('order_status', OrderStatus::Pending);
    }

    public function scopeWithDetails(Builder $query): Builder
    {
        return $query->with([
            'items.marketplaceProduct:id,name,slug,primary_image,tenant_id',
            'payments',
            'delivery',
            'deliveryAddress',
        ]);
    }

    public function scopeExpiredReservations(Builder $query): Builder
    {
        return $query->where('reservation_status', ReservationStatus::Pending)
            ->where('reservation_expires_at', '<', now());
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Generate a unique order number.
     */
    public static function generateOrderNumber(): string
    {
        $year = now()->year;
        $nextId = (static::on('central')->withTrashed()->max('id') ?? 0) + 1;

        return 'MKT-ORD-' . $year . '-' . str_pad($nextId, 6, '0', STR_PAD_LEFT);
    }

    public function canBeCancelled(): bool
    {
        return $this->order_status->canBeCancelled();
    }

    public function cancel(string $reason, ?int $cancelledBy = null): bool
    {
        return $this->update([
            'order_status'        => OrderStatus::Cancelled,
            'cancellation_reason' => $reason,
            'cancelled_at'        => now(),
            'cancelled_by'        => $cancelledBy,
        ]);
    }

    public function getTotalPaid(): float
    {
        return (float) $this->payments()
            ->where('payment_status', MarketplacePaymentStatus::Completed)
            ->sum('amount');
    }

    public function isReservationConfirmed(): bool
    {
        return $this->reservation_status === ReservationStatus::Confirmed;
    }

    public function isPaymentDeadlinePassed(): bool
    {
        return $this->payment_deadline_at && $this->payment_deadline_at->isPast();
    }

    /**
     * Whether the order can accept payment (reservation confirmed and not terminal).
     */
    public function canAcceptPayment(): bool
    {
        return $this->reservation_status === ReservationStatus::Confirmed
            && ! $this->order_status->isTerminal()
            && ! $this->isPaymentDeadlinePassed();
    }
}
