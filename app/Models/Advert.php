<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A shop's paid placement: a product boost, a featured shop, near-you or a home spot.
 *
 * @property int $id
 * @property string $tenant_id
 * @property string $type_key
 * @property int|null $marketplace_product_id
 * @property string|null $county
 * @property string $price
 * @property int $days
 * @property string $status
 * @property string $paid_with
 * @property int|null $billing_topup_id
 * @property int|null $billing_ledger_id
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property int $views
 * @property int $clicks
 * @property string|null $stopped_reason
 * @property Carbon $created_at
 */
class Advert extends Model
{
    public const PENDING_PAYMENT = 'pending_payment';

    public const ACTIVE = 'active';

    public const ENDED = 'ended';

    public const STOPPED = 'stopped';

    public const PAYMENT_FAILED = 'payment_failed';

    protected $connection = 'central';

    protected $fillable = [
        'tenant_id', 'type_key', 'marketplace_product_id', 'county', 'price', 'days', 'status', 'paid_with', 'billing_topup_id',
        'billing_ledger_id', 'starts_at', 'ends_at', 'views', 'clicks', 'stopped_reason', 'created_by', 'idempotency_key',
    ];

    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'days' => 'integer', 'starts_at' => 'datetime', 'ends_at' => 'datetime', 'views' => 'integer', 'clicks' => 'integer'];
    }

    /** @return BelongsTo<AdvertType, $this> */
    public function type(): BelongsTo
    {
        return $this->belongsTo(AdvertType::class, 'type_key', 'key');
    }

    /** @return BelongsTo<MarketplaceProduct, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(MarketplaceProduct::class, 'marketplace_product_id');
    }

    /** Paid and inside its dates. */
    public function scopeRunning(Builder $query): Builder
    {
        return $query->where('status', self::ACTIVE)->where('starts_at', '<=', now())->where('ends_at', '>', now());
    }

    /** The attribution tag on links and orders this advert brings ("ad12"). */
    public function ref(): string
    {
        return 'ad'.$this->id;
    }
}
