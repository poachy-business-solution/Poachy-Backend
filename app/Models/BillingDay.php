<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A branch's billed trading day. Unique per (tenant, store, date).
 *
 * @property int $id
 * @property string $tenant_id
 * @property int $store_id
 * @property Carbon $date
 * @property string $amount
 * @property bool $is_main_branch
 * @property int|null $billing_ledger_id
 */
class BillingDay extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $connection = 'central';

    protected $fillable = ['tenant_id', 'store_id', 'date', 'amount', 'is_main_branch', 'billing_ledger_id'];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'amount' => 'decimal:2',
            'is_main_branch' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function ledgerEntry(): BelongsTo
    {
        return $this->belongsTo(BillingLedgerEntry::class, 'billing_ledger_id');
    }
}
