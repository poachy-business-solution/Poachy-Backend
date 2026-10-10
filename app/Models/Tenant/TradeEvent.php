<?php

namespace App\Models\Tenant;

use App\Enums\Tenant\TradeEventType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One thing that happened with a trade partner: goods taken either way,
 * brought back either way, or money paid either way.
 */
class TradeEvent extends Model
{
    protected $table = 'trade_events';

    protected $fillable = [
        'trade_partner_id',
        'store_id',
        'trade_line_id',
        'type',
        'product_id',
        'quantity',
        'amount',
        'payment_method',
        'payment_reference',
        'cash_movement_id',
        'recorded_by',
        'occurred_at',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'type' => TradeEventType::class,
            'quantity' => 'decimal:4',
            'amount' => 'decimal:2',
            'occurred_at' => 'datetime',
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

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
