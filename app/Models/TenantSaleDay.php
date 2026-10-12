<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A branch that recorded at least one sale on a Nairobi calendar day (reported by the shop).
 *
 * @property int $id
 * @property string $tenant_id
 * @property int $store_id
 * @property Carbon $date
 * @property int $sales_count
 * @property bool $is_main_branch
 * @property Carbon $reported_at
 */
class TenantSaleDay extends Model
{
    protected $connection = 'central';

    protected $fillable = ['tenant_id', 'store_id', 'date', 'sales_count', 'is_main_branch', 'reported_at'];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'sales_count' => 'integer',
            'is_main_branch' => 'boolean',
            'reported_at' => 'datetime',
        ];
    }
}
