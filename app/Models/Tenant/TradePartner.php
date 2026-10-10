<?php

namespace App\Models\Tenant;

use App\Enums\Tenant\TradeDirection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A neighbour shop this shop lends goods to and borrows goods from.
 */
class TradePartner extends Model
{
    use SoftDeletes;

    protected $table = 'trade_partners';

    protected $fillable = [
        'name',
        'phone',
        'note',
        'supplier_id',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(TradeLine::class);
    }

    public function openLines(): HasMany
    {
        return $this->hasMany(TradeLine::class)->where('status', TradeLine::STATUS_OPEN)->orderBy('opened_at')->orderBy('id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(TradeEvent::class);
    }

    /**
     * The supplier record goods borrowed from this shop are received from,
     * so their agreed price becomes the stock's cost.
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * What they owe me, what I owe them, and the goods still out.
     *
     * @return array{they_owe: float, you_owe: float, net: float, goods_out_quantity: float, goods_out_value: float, open_lines: int, oldest_open_at: string|null}
     */
    public function balance(): array
    {
        $lines = $this->relationLoaded('openLines') ? $this->openLines : $this->openLines()->get();

        $theyOwe = 0.0;
        $youOwe = 0.0;
        $goodsOutQuantity = 0.0;
        $goodsOutValue = 0.0;

        foreach ($lines as $line) {
            if ($line->direction === TradeDirection::LENT) {
                $theyOwe += $line->outstandingAmount();
                $goodsOutQuantity += $line->outstandingQuantity();
                $goodsOutValue += $line->outstandingQuantity() * (float) ($line->unit_cost ?? $line->unit_price);
            } else {
                $youOwe += $line->outstandingAmount();
            }
        }

        return [
            'they_owe' => round($theyOwe, 2),
            'you_owe' => round($youOwe, 2),
            'net' => round($theyOwe - $youOwe, 2),
            'goods_out_quantity' => round($goodsOutQuantity, 4),
            'goods_out_value' => round($goodsOutValue, 2),
            'open_lines' => $lines->count(),
            'oldest_open_at' => $lines->min('opened_at')?->toISOString(),
        ];
    }
}
