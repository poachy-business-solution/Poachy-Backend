<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A buyer's return after handover: requested → item back at the shop (received) → the shop
 * inspects within 48 h (accept / partial / reject; silence escalates to Poachy) → the buyer may
 * dispute a rejection or partial within 48 h → Poachy decides → refund.
 *
 * @property int $id
 * @property string $reference
 * @property int $order_id
 * @property string $tenant_id
 * @property int $customer_id
 * @property array<int, array<string, mixed>> $items
 * @property string $reason_code
 * @property string|null $description
 * @property array<int, string>|null $buyer_evidence
 * @property bool $shop_at_fault
 * @property string $requested_amount
 * @property string $status
 * @property string|null $shop_decision
 * @property string|null $refund_amount
 * @property string|null $shop_reason
 * @property array<int, string>|null $shop_evidence
 * @property Carbon|null $received_at
 * @property Carbon|null $inspection_due_at
 * @property Carbon|null $inspected_at
 * @property Carbon|null $restocked_at
 * @property Carbon|null $dispute_until
 * @property Carbon|null $disputed_at
 * @property string|null $dispute_reason
 * @property array<int, string>|null $dispute_evidence
 * @property Carbon|null $escalated_at
 * @property string|null $admin_decision
 * @property string|null $admin_note
 * @property string|null $decided_by
 * @property Carbon|null $decided_at
 * @property Carbon|null $closed_at
 * @property Carbon $created_at
 */
class MarketplaceReturnCase extends Model
{
    public const REQUESTED = 'requested';

    public const RECEIVED = 'received';

    public const REJECTED = 'rejected';

    public const DISPUTED = 'disputed';

    public const ESCALATED = 'escalated';

    public const REFUNDING = 'refunding';

    public const REFUNDED = 'refunded';

    public const CLOSED = 'closed';

    public const EXPIRED = 'expired';

    public const CANCELLED = 'cancelled';

    /** Still waiting on someone. */
    public const OPEN = [self::REQUESTED, self::RECEIVED, self::REJECTED, self::DISPUTED, self::ESCALATED, self::REFUNDING];

    public const REASONS = ['changed_mind', 'faulty', 'wrong_item', 'not_as_described', 'damaged'];

    protected $connection = 'central';

    protected $fillable = [
        'reference', 'order_id', 'tenant_id', 'customer_id', 'items', 'reason_code', 'description', 'buyer_evidence', 'shop_at_fault', 'perishable',
        'requested_amount', 'status', 'shop_decision', 'refund_amount', 'shop_reason', 'shop_evidence', 'received_at', 'inspection_due_at',
        'inspected_at', 'restocked_at', 'dispute_until', 'disputed_at', 'dispute_reason', 'dispute_evidence', 'escalated_at',
        'admin_decision', 'admin_note', 'decided_by', 'decided_at', 'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'items' => 'array', 'buyer_evidence' => 'array', 'shop_evidence' => 'array', 'dispute_evidence' => 'array',
            'shop_at_fault' => 'boolean', 'perishable' => 'boolean', 'requested_amount' => 'decimal:2', 'refund_amount' => 'decimal:2',
            'received_at' => 'datetime', 'inspection_due_at' => 'datetime', 'inspected_at' => 'datetime', 'restocked_at' => 'datetime',
            'dispute_until' => 'datetime', 'disputed_at' => 'datetime', 'escalated_at' => 'datetime', 'decided_at' => 'datetime', 'closed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<MarketplaceOrder, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(MarketplaceOrder::class, 'order_id');
    }

    /** @return HasMany<MarketplaceRefund, $this> */
    public function refunds(): HasMany
    {
        return $this->hasMany(MarketplaceRefund::class, 'return_case_id');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN, true);
    }
}
