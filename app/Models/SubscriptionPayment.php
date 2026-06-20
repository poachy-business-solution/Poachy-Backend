<?php

namespace App\Models;

use App\Enums\Central\SubscriptionPaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionPayment extends Model
{
    protected $connection = 'central';

    protected $table = 'subscription_payments';

    protected $fillable = [
        'tenant_id',
        'subscription_plan_id',
        'customer_phone',        // nullable for C2B (comes from MSISDN in callback)
        'amount',
        'payment_status',
        'payment_type',          // 'stk' or 'c2b'
        'bill_ref_number',       // C2B: the tenant account number (BillRefNumber)
        'transaction_reference', // STK: CheckoutRequestID | C2B: TransID
        'provider_reference',    // M-Pesa receipt number (on success)
        'initiated_at',
        'completed_at',
        'failed_at',
        'failure_reason',
        'failure_code',
        'payment_metadata',
        'business_subscription_id',
    ];

    protected function casts(): array
    {
        return [
            'amount'           => 'decimal:2',
            'payment_status'   => SubscriptionPaymentStatus::class,
            'initiated_at'     => 'datetime',
            'completed_at'     => 'datetime',
            'failed_at'        => 'datetime',
            'payment_metadata' => 'array',
        ];
    }

    // =========================================================================
    // Relationships
    // =========================================================================

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'id');
    }

    public function subscriptionPlan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function businessSubscription(): BelongsTo
    {
        return $this->belongsTo(BusinessSubscription::class, 'business_subscription_id');
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    public function isCompleted(): bool
    {
        return $this->payment_status === SubscriptionPaymentStatus::Completed;
    }

    public function isFailed(): bool
    {
        return $this->payment_status === SubscriptionPaymentStatus::Failed;
    }

    public function isProcessing(): bool
    {
        return $this->payment_status === SubscriptionPaymentStatus::Processing;
    }

    public function markAsCompleted(string $providerReference): bool
    {
        return $this->update([
            'payment_status'     => SubscriptionPaymentStatus::Completed,
            'provider_reference' => $providerReference,
            'completed_at'       => now(),
        ]);
    }

    public function markAsFailed(string $reason, ?string $code = null): bool
    {
        return $this->update([
            'payment_status' => SubscriptionPaymentStatus::Failed,
            'failed_at'      => now(),
            'failure_reason' => $reason,
            'failure_code'   => $code,
        ]);
    }
}
