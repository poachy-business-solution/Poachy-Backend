<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One entry in what Poachy owes a shop: a handed-over order (gross − fees − commission), a
 * clawback for a refund after payout (negative), or a rounding adjustment.
 *
 * @property int $id
 * @property string $tenant_id
 * @property int|null $order_id
 * @property string $type
 * @property string $description
 * @property string $gross
 * @property string $processing_fee
 * @property string $commission_rate
 * @property string $commission_amount
 * @property string $net
 * @property string|null $source
 * @property string|null $payment_method
 * @property Carbon|null $handed_over_at
 * @property Carbon $eligible_at
 * @property int|null $payout_id
 */
class MarketplacePayoutLine extends Model
{
    public const SALE = 'sale';

    public const CLAWBACK = 'clawback';

    public const ADJUSTMENT = 'adjustment';

    protected $connection = 'central';

    protected $fillable = [
        'tenant_id', 'order_id', 'type', 'description', 'gross', 'processing_fee', 'commission_rate', 'commission_amount',
        'net', 'source', 'payment_method', 'handed_over_at', 'eligible_at', 'payout_id',
    ];

    protected function casts(): array
    {
        return [
            'gross' => 'decimal:2', 'processing_fee' => 'decimal:2', 'commission_rate' => 'decimal:2',
            'commission_amount' => 'decimal:2', 'net' => 'decimal:2', 'handed_over_at' => 'datetime', 'eligible_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<MarketplacePayout, $this> */
    public function payout(): BelongsTo
    {
        return $this->belongsTo(MarketplacePayout::class, 'payout_id');
    }

    /** @return BelongsTo<MarketplaceOrder, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(MarketplaceOrder::class, 'order_id');
    }

    /** Not in any payout yet and past the hold. */
    public function scopeAvailable(Builder $query, ?Carbon $at = null): Builder
    {
        return $query->whereNull('payout_id')->where('eligible_at', '<=', $at ?? now());
    }

    /** Not in any payout yet and still inside the hold after handover. */
    public function scopeHeld(Builder $query, ?Carbon $at = null): Builder
    {
        return $query->whereNull('payout_id')->where('eligible_at', '>', $at ?? now());
    }
}
