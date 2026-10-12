<?php

namespace App\Models;

use App\Enums\Central\BillingLedgerType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One line of a shop's billing statement. Append-only; amount > 0 is money in.
 *
 * @property int $id
 * @property string $tenant_id
 * @property int $billing_account_id
 * @property BillingLedgerType $type
 * @property string $amount
 * @property string $balance_after
 * @property string $description
 * @property string $source
 * @property string|null $reference
 * @property string|null $idempotency_key
 * @property string|null $created_by
 * @property array<string, mixed>|null $meta
 * @property Carbon $created_at
 */
class BillingLedgerEntry extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $connection = 'central';

    protected $table = 'billing_ledger';

    protected $fillable = [
        'tenant_id', 'billing_account_id', 'type', 'amount', 'balance_after', 'description', 'source',
        'reference', 'idempotency_key', 'created_by', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'type' => BillingLedgerType::class,
            'amount' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'meta' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(BillingAccount::class, 'billing_account_id');
    }
}
