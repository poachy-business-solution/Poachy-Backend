<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * The marketplace commission rate from a date on (0% at launch).
 *
 * @property int $id
 * @property string $rate_percent
 * @property Carbon $effective_from
 * @property string|null $notes
 */
class MarketplaceCommissionRate extends Model
{
    protected $connection = 'central';

    protected $fillable = ['rate_percent', 'effective_from', 'notes'];

    protected function casts(): array
    {
        return ['rate_percent' => 'decimal:2', 'effective_from' => 'date'];
    }
}
