<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One M-Pesa B2C transfer from Poachy to a shop, settling its available ledger lines.
 *
 * @property int $id
 * @property string $tenant_id
 * @property string $reference
 * @property string|null $phone
 * @property string $amount
 * @property int $lines_count
 * @property string $status
 * @property int $attempts
 * @property Carbon|null $next_attempt_at
 * @property string|null $conversation_id
 * @property string|null $originator_conversation_id
 * @property string|null $mpesa_receipt
 * @property string|null $failure_reason
 * @property Carbon|null $paid_at
 * @property Carbon $created_at
 */
class MarketplacePayout extends Model
{
    public const PROCESSING = 'processing';

    public const PAID = 'paid';

    /** Failed; re-sent automatically at next_attempt_at. */
    public const FAILED = 'failed';

    /** Safaricom timed out: the money may or may not have moved — a person checks before any resend. */
    public const NEEDS_CHECK = 'needs_check';

    /** Out of automatic retries. */
    public const FAILED_FINAL = 'failed_final';

    protected $connection = 'central';

    protected $fillable = [
        'tenant_id', 'reference', 'phone', 'amount', 'lines_count', 'status', 'attempts', 'next_attempt_at',
        'conversation_id', 'originator_conversation_id', 'mpesa_receipt', 'failure_reason', 'paid_at',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'next_attempt_at' => 'datetime', 'paid_at' => 'datetime'];
    }

    /** @return HasMany<MarketplacePayoutLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(MarketplacePayoutLine::class, 'payout_id');
    }
}
