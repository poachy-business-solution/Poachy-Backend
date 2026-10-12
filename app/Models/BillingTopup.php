<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An STK top-up request and its outcome.
 *
 * @property int $id
 * @property string $tenant_id
 * @property string $amount
 * @property string $phone
 * @property string $status
 * @property string|null $checkout_request_id
 * @property string|null $merchant_request_id
 * @property string|null $mpesa_receipt
 * @property string|null $failure_reason
 * @property string|null $idempotency_key
 * @property string|null $initiated_by
 * @property int|null $payment_link_id
 * @property int|null $billing_ledger_id
 * @property Carbon $created_at
 */
class BillingTopup extends Model
{
    public const PENDING = 'pending';

    public const PROCESSING = 'processing';

    public const COMPLETED = 'completed';

    public const FAILED = 'failed';

    protected $connection = 'central';

    protected $fillable = [
        'tenant_id', 'amount', 'phone', 'status', 'checkout_request_id', 'merchant_request_id', 'mpesa_receipt',
        'failure_reason', 'idempotency_key', 'initiated_by', 'payment_link_id', 'billing_ledger_id',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function ledgerEntry(): BelongsTo
    {
        return $this->belongsTo(BillingLedgerEntry::class, 'billing_ledger_id');
    }

    public function isFinal(): bool
    {
        return in_array($this->status, [self::COMPLETED, self::FAILED], true);
    }
}
