<?php

namespace App\Models\Tenant;

use App\Enums\Tenant\PaymentMethod;
use App\Observers\Tenant\SalePaymentObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// #[ObservedBy([SalePaymentObserver::class])]
class SalePayment extends Model
{
    use HasFactory;

    protected $table = 'sale_payments';

    protected $fillable = [
        'sale_id',
        'amount',
        'payment_method',
        'reference_number',
        'payment_date',
        'received_by_user_id',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_method' => PaymentMethod::class,
        'payment_date' => 'datetime',
    ];

    // ============================================
    // RELATIONSHIPS
    // ============================================

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by_user_id');
    }

    // ============================================
    // HELPER METHODS
    // ============================================

    /**
     * Check if payment is electronic (M-Pesa, card, bank transfer)
     */
    public function isElectronic(): bool
    {
        return in_array($this->payment_method, [
            PaymentMethod::MPESA,
            PaymentMethod::CARD,
            PaymentMethod::BANK_TRANSFER,
        ]);
    }

    /**
     * Check if payment requires reference
     */
    public function requiresReference(): bool
    {
        return $this->isElectronic();
    }
}
