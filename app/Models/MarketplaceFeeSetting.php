<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Payout fees, hold and minimum from a date on (item 49, edited by finance in the admin).
 *
 * @property int $id
 * @property string $mpesa_fee_percent
 * @property string $card_fee_percent
 * @property string $transfer_fee
 * @property int $hold_hours
 * @property int $min_payout
 * @property Carbon $effective_from
 * @property string|null $notes
 */
class MarketplaceFeeSetting extends Model
{
    protected $connection = 'central';

    protected $fillable = ['mpesa_fee_percent', 'card_fee_percent', 'transfer_fee', 'hold_hours', 'min_payout', 'effective_from', 'notes'];

    protected function casts(): array
    {
        return [
            'mpesa_fee_percent' => 'decimal:2',
            'card_fee_percent' => 'decimal:2',
            'transfer_fee' => 'decimal:2',
            'hold_hours' => 'integer',
            'min_payout' => 'integer',
            'effective_from' => 'date',
        ];
    }
}
