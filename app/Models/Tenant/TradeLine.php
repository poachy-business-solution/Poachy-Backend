<?php

namespace App\Models\Tenant;

use App\Enums\Tenant\TradeDirection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Goods of one product lent to or borrowed from a trade partner at an agreed
 * price. Open until brought back or paid for (or a mix of both).
 */
class TradeLine extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_SETTLED = 'settled';

    protected $table = 'trade_lines';

    protected $fillable = [
        'trade_partner_id',
        'store_id',
        'direction',
        'product_id',
        'quantity',
        'unit_price',
        'unit_cost',
        'quantity_returned',
        'amount_paid',
        'status',
        'opened_at',
        'settled_at',
        'recorded_by',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'direction' => TradeDirection::class,
            'quantity' => 'decimal:4',
            'unit_price' => 'decimal:2',
            'unit_cost' => 'decimal:2',
            'quantity_returned' => 'decimal:4',
            'amount_paid' => 'decimal:2',
            'opened_at' => 'datetime',
            'settled_at' => 'datetime',
        ];
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(TradePartner::class, 'trade_partner_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Quantity not yet brought back.
     */
    public function outstandingQuantity(): float
    {
        return max(0, round((float) $this->quantity - (float) $this->quantity_returned, 4));
    }

    /**
     * Money still owed on this line: goods not brought back, less what's been paid.
     */
    public function outstandingAmount(): float
    {
        return max(0, round($this->outstandingQuantity() * (float) $this->unit_price - (float) $this->amount_paid, 2));
    }

    /**
     * Close the line once nothing is owed on it.
     */
    public function settleIfDone(): void
    {
        if ($this->outstandingAmount() < 0.005 && $this->status === self::STATUS_OPEN) {
            $this->status = self::STATUS_SETTLED;
            $this->settled_at = now();
        }
    }
}
