<?php

namespace App\Models;

use App\Enums\Central\BillingStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A shop's prepaid Poachy balance and billing state.
 *
 * @property int $id
 * @property string $tenant_id
 * @property string $balance
 * @property BillingStatus $status
 * @property Carbon|null $trial_ends_at
 * @property Carbon|null $grace_until
 * @property Carbon|null $limited_since
 * @property Carbon|null $low_balance_notified_at
 */
class BillingAccount extends Model
{
    use HasFactory;

    protected $connection = 'central';

    protected $fillable = ['tenant_id', 'balance', 'status', 'trial_ends_at', 'grace_until', 'limited_since', 'low_balance_notified_at'];

    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
            'status' => BillingStatus::class,
            'trial_ends_at' => 'datetime',
            'grace_until' => 'datetime',
            'limited_since' => 'datetime',
            'low_balance_notified_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'id');
    }

    public function ledger(): HasMany
    {
        return $this->hasMany(BillingLedgerEntry::class);
    }

    public function days(): HasMany
    {
        return $this->hasMany(BillingDay::class, 'tenant_id', 'tenant_id');
    }

    /** Still inside the free trial at a moment (default now). */
    public function isInTrial(?\DateTimeInterface $at = null): bool
    {
        return $this->trial_ends_at !== null && $this->trial_ends_at->greaterThan($at ?? now());
    }
}
