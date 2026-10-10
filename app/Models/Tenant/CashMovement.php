<?php

namespace App\Models\Tenant;

use App\Enums\Tenant\CashMovementReason;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cash put into or taken out of a till drawer outside a sale — a float
 * top-up, the owner taking cash home, a supplier paid from the till.
 */
class CashMovement extends Model
{
    public const DIRECTION_IN = 'in';

    public const DIRECTION_OUT = 'out';

    protected $table = 'cash_movements';

    protected $fillable = [
        'store_id',
        'shift_assignment_id',
        'recorded_by',
        'direction',
        'reason',
        'amount',
        'supplier_id',
        'note',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'reason' => CashMovementReason::class,
            'amount' => 'decimal:2',
            'occurred_at' => 'datetime',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function shiftAssignment(): BelongsTo
    {
        return $this->belongsTo(ShiftAssignment::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * The amount with its effect on the drawer: positive in, negative out.
     */
    public function signedAmount(): float
    {
        return $this->direction === self::DIRECTION_IN ? (float) $this->amount : -(float) $this->amount;
    }
}
