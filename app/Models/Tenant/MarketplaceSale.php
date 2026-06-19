<?php

namespace App\Models\Tenant;

use App\Enums\Tenant\PaymentMethod;
use App\Enums\Tenant\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MarketplaceSale extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'marketplace_sales';

    protected $fillable = [
        'central_order_id',
        'sale_number',
        'store_id',
        'sale_date',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'total_amount',
        'payment_status',
        'amount_paid',
        'amount_due',
        'payment_method',
        'payment_reference',
        'fulfillment_type',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'subtotal'        => 'decimal:2',
            'tax_amount'      => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total_amount'    => 'decimal:2',
            'amount_paid'     => 'decimal:2',
            'amount_due'      => 'decimal:2',
            'payment_status'  => PaymentStatus::class,
            'payment_method'  => PaymentMethod::class,
            'sale_date'       => 'datetime',
        ];
    }

    // =========================================================================
    // Relationships
    // =========================================================================

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(MarketplaceSaleItem::class);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    public function isPaid(): bool
    {
        return $this->payment_status === PaymentStatus::PAID;
    }

    public function isCashOnDelivery(): bool
    {
        return $this->payment_method === PaymentMethod::CASH;
    }
}
