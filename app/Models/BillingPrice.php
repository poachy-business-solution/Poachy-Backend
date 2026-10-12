<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One version of the trading-day price list, valid from `effective_from` until the next row.
 *
 * @property int $id
 * @property Carbon $effective_from
 * @property string $first_branch_day
 * @property string $extra_branch_day
 * @property string $first_branch_cap
 * @property string $extra_branch_cap
 * @property int $trial_days
 * @property string $min_topup
 * @property int $low_balance_days
 * @property int $grace_days
 */
class BillingPrice extends Model
{
    use HasFactory;

    protected $connection = 'central';

    protected $fillable = [
        'effective_from', 'first_branch_day', 'extra_branch_day', 'first_branch_cap', 'extra_branch_cap',
        'trial_days', 'min_topup', 'low_balance_days', 'grace_days', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'first_branch_day' => 'decimal:2',
            'extra_branch_day' => 'decimal:2',
            'first_branch_cap' => 'decimal:2',
            'extra_branch_cap' => 'decimal:2',
            'min_topup' => 'decimal:2',
            'trial_days' => 'integer',
            'low_balance_days' => 'integer',
            'grace_days' => 'integer',
        ];
    }

    /** The price list in force on a (Nairobi) date. */
    public static function forDate(CarbonInterface|string $date): ?self
    {
        $day = is_string($date) ? $date : $date->toDateString();

        return static::query()->whereDate('effective_from', '<=', $day)->orderByDesc('effective_from')->first();
    }

    /** The day rate for a branch. */
    public function dayRate(bool $isMainBranch): float
    {
        return (float) ($isMainBranch ? $this->first_branch_day : $this->extra_branch_day);
    }

    /** The monthly cap for a branch. */
    public function monthlyCap(bool $isMainBranch): float
    {
        return (float) ($isMainBranch ? $this->first_branch_cap : $this->extra_branch_cap);
    }
}
