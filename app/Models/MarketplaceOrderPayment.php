<?php

namespace App\Models;

use App\Enums\Central\MarketplacePaymentMethod;
use App\Enums\Central\MarketplacePaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MarketplaceOrderPayment extends Model
{
    use SoftDeletes;

    protected $connection = 'central';

    protected $table = 'marketplace_order_payments';

    protected $fillable = [
        'order_id',
        'payment_method',
        'payment_provider',
        'amount',
        'payment_status',
        'transaction_reference',
        'provider_reference',
        'initiated_at',
        'completed_at',
        'failed_at',
        'failure_reason',
        'failure_code',
        'is_refunded',
        'refunded_amount',
        'refunded_at',
        'refund_reference',
        'payment_metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount'            => 'decimal:2',
            'payment_method'    => MarketplacePaymentMethod::class,
            'payment_status'    => MarketplacePaymentStatus::class,
            'initiated_at'      => 'datetime',
            'completed_at'      => 'datetime',
            'failed_at'         => 'datetime',
            'is_refunded'       => 'boolean',
            'refunded_amount'   => 'decimal:2',
            'refunded_at'       => 'datetime',
            'payment_metadata'  => 'array',
        ];
    }

    // =========================================================================
    // Relationships
    // =========================================================================

    public function order(): BelongsTo
    {
        return $this->belongsTo(MarketplaceOrder::class, 'order_id');
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    public function isCompleted(): bool
    {
        return $this->payment_status === MarketplacePaymentStatus::Completed;
    }

    public function isFailed(): bool
    {
        return $this->payment_status === MarketplacePaymentStatus::Failed;
    }

    public function markAsCompleted(?string $transactionReference = null, ?string $providerReference = null): bool
    {
        $data = [
            'payment_status' => MarketplacePaymentStatus::Completed,
            'completed_at'   => now(),
        ];

        if ($transactionReference) {
            $data['transaction_reference'] = $transactionReference;
        }

        if ($providerReference) {
            $data['provider_reference'] = $providerReference;
        }

        return $this->update($data);
    }

    public function markAsFailed(string $reason, ?string $code = null): bool
    {
        return $this->update([
            'payment_status' => MarketplacePaymentStatus::Failed,
            'failed_at'      => now(),
            'failure_reason'  => $reason,
            'failure_code'    => $code,
        ]);
    }

    public function markAsRefunded(float $amount, ?string $refundReference = null): bool
    {
        return $this->update([
            'payment_status'  => MarketplacePaymentStatus::Refunded,
            'is_refunded'     => true,
            'refunded_amount' => $amount,
            'refunded_at'     => now(),
            'refund_reference' => $refundReference,
        ]);
    }
}
