<?php

namespace App\Models;

use App\Enums\Central\MarketplaceReservationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A reserve & collect hold: a buyer's PCH- code for one item at one shop, valid 24 h, redeemed
 * on the POS when they pay at the counter. Lives in the central DB; readable from tenant context.
 *
 * @property int $id
 * @property string $code
 * @property string $tenant_id
 * @property int $customer_id
 * @property int $marketplace_product_id
 * @property int|null $tenant_product_id
 * @property int|null $tenant_variant_id
 * @property int|null $tenant_bundle_id
 * @property string $product_name
 * @property string|null $product_slug
 * @property string|null $product_image
 * @property float $quantity
 * @property float $unit_price
 * @property string|null $uom_code
 * @property MarketplaceReservationStatus $status
 * @property string|null $ref
 * @property string|null $buyer_name
 * @property string|null $buyer_phone
 * @property Carbon $expires_at
 * @property Carbon|null $held_at
 * @property string|null $failed_reason
 * @property Carbon|null $cancelled_at
 * @property Carbon|null $redeemed_at
 * @property int|null $sale_id
 * @property string|null $sale_number
 * @property int|null $redeemed_by
 * @property string $idempotency_key
 */
class MarketplaceReservation extends Model
{
    public const HOLD_HOURS = 24;

    /** Unambiguous characters for codes read out at a counter (no 0/O, 1/I). */
    private const CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    protected $connection = 'central';

    protected $table = 'marketplace_reservations';

    protected $fillable = [
        'code', 'tenant_id', 'customer_id', 'marketplace_product_id', 'tenant_product_id', 'tenant_variant_id',
        'tenant_bundle_id', 'product_name', 'product_slug', 'product_image', 'quantity', 'unit_price', 'uom_code',
        'status', 'ref', 'buyer_name', 'buyer_phone', 'expires_at', 'held_at', 'failed_reason', 'cancelled_at',
        'redeemed_at', 'sale_id', 'sale_number', 'redeemed_by', 'idempotency_key',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'status' => MarketplaceReservationStatus::class,
            'expires_at' => 'datetime',
            'held_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'redeemed_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(MarketplaceCustomer::class, 'customer_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(MarketplaceProduct::class, 'marketplace_product_id');
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(BusinessDetail::class, 'tenant_id', 'tenant_id');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [MarketplaceReservationStatus::Pending->value, MarketplaceReservationStatus::Active->value]);
    }

    public function scopeForTenant(Builder $query, string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    /** Held and not yet past its time: the cashier may redeem it. */
    public function isRedeemable(): bool
    {
        return $this->status === MarketplaceReservationStatus::Active && $this->expires_at->isFuture();
    }

    public function total(): float
    {
        return round((float) $this->quantity * (float) $this->unit_price, 2);
    }

    /** Where the buyer came from, for the shop's attribution: own link, advert, or the marketplace. */
    public function source(): string
    {
        if ($this->ref === null || $this->ref === '') {
            return 'marketplace';
        }

        return Str::startsWith($this->ref, 'ad') ? 'advert' : 'own_link';
    }

    /** A fresh PCH-XXXX code that no reservation uses (five characters if four keep colliding). */
    public static function generateCode(): string
    {
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $length = $attempt < 10 ? 4 : 5;
            $code = 'PCH-';

            for ($i = 0; $i < $length; $i++) {
                $code .= self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)];
            }

            if (! static::on('central')->where('code', $code)->exists()) {
                return $code;
            }
        }

        throw new \RuntimeException('Could not allocate a reservation code.');
    }
}
