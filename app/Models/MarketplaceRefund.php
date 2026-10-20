<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Money going back to a buyer, through the gateway that took it: an M-Pesa reversal (whole
 * payment), an M-Pesa B2C to the buyer (part of a payment) or a Paystack refund (card).
 *
 * @property int $id
 * @property int $order_id
 * @property int|null $payment_id
 * @property string $tenant_id
 * @property int|null $return_case_id
 * @property string $kind
 * @property string $amount
 * @property string $method
 * @property string $status
 * @property string|null $reason
 * @property string|null $phone
 * @property string|null $provider_reference
 * @property string|null $conversation_id
 * @property string|null $failure_reason
 * @property Carbon|null $completed_at
 * @property string $idempotency_key
 */
class MarketplaceRefund extends Model
{
    public const PROCESSING = 'processing';

    public const COMPLETED = 'completed';

    public const FAILED = 'failed';

    protected $connection = 'central';

    protected $fillable = [
        'order_id', 'payment_id', 'tenant_id', 'return_case_id', 'kind', 'amount', 'method', 'status', 'reason', 'phone',
        'provider_reference', 'conversation_id', 'failure_reason', 'completed_at', 'idempotency_key',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'completed_at' => 'datetime'];
    }

    /** @return BelongsTo<MarketplaceOrder, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(MarketplaceOrder::class, 'order_id');
    }

    /** @return BelongsTo<MarketplaceReturnCase, $this> */
    public function returnCase(): BelongsTo
    {
        return $this->belongsTo(MarketplaceReturnCase::class, 'return_case_id');
    }
}
