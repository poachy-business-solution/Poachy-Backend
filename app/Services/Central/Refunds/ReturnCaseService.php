<?php

namespace App\Services\Central\Refunds;

use App\Enums\Central\OrderStatus;
use App\Models\BusinessDetail;
use App\Models\MarketplaceOrder;
use App\Models\MarketplacePayoutLine;
use App\Models\MarketplaceProduct;
use App\Models\MarketplaceReturnCase;
use App\Services\Shared\Messaging\Messenger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Returns after handover. The buyer asks within the shop's window (never for perishables,
 * hygiene goods or airtime) and brings the item back; the shop inspects within 48 h (silence
 * escalates to Poachy); a rejection or partial refund can be disputed within 48 h; Poachy
 * decides. Shop at fault: the buyer gets the items and any delivery fee back. Changed mind: the
 * buyer bears the trip and their share of the payment fee.
 */
class ReturnCaseService
{
    public function __construct(
        private readonly RefundService $refunds,
        private readonly Messenger $messenger,
    ) {}

    /**
     * @param  array<int, array{order_item_id: int, quantity: float}>  $items
     * @param  array<int, UploadedFile>  $photos
     */
    public function open(MarketplaceOrder $order, array $items, string $reasonCode, ?string $description, array $photos = []): MarketplaceReturnCase
    {
        $this->assertReturnable($order);
        $order->loadMissing('items');

        $lines = [];
        $perishable = false;
        foreach ($items as $wanted) {
            $item = $order->items->firstWhere('id', (int) $wanted['order_item_id']);
            $quantity = (float) $wanted['quantity'];
            if ($item === null || $quantity <= 0 || $quantity > (float) $item->quantity) {
                throw ValidationException::withMessages(['items' => 'Choose items and quantities from this order.']);
            }
            if ($this->matches((int) $item->marketplace_product_id, 'non_returnable_keywords')) {
                throw ValidationException::withMessages(['items' => "{$item->product_name} can't be returned (hygiene goods, cosmetics and airtime never are)."]);
            }
            $perishable = $perishable || $this->matches((int) $item->marketplace_product_id, 'perishable_keywords');
            // An order line's subtotal already includes its VAT (checkout adds it on top).
            $unit = round((float) $item->subtotal / max((float) $item->quantity, 0.0001), 2);
            $lines[] = ['order_item_id' => $item->id, 'name' => $item->product_name, 'quantity' => $quantity, 'unit_amount' => $unit, 'amount' => round($unit * $quantity, 2)];
        }
        if ($lines === []) {
            throw ValidationException::withMessages(['items' => 'Choose at least one item.']);
        }

        if ($perishable) {
            $this->assertPerishableReturnable($order, $reasonCode, $photos);
        }

        $shopAtFault = $reasonCode !== 'changed_mind';
        $amount = $this->requestedAmount($order, $lines, $shopAtFault);

        return DB::connection('central')->transaction(function () use ($order, $lines, $reasonCode, $description, $photos, $shopAtFault, $amount, $perishable) {
            $case = MarketplaceReturnCase::query()->create([
                'reference' => 'RET-'.Str::upper(Str::random(6)),
                'order_id' => $order->id, 'tenant_id' => $order->tenant_id, 'customer_id' => $order->customer_id,
                'items' => $lines, 'reason_code' => $reasonCode, 'description' => $description, 'perishable' => $perishable,
                'shop_at_fault' => $shopAtFault, 'requested_amount' => $amount, 'status' => MarketplaceReturnCase::REQUESTED,
            ]);
            $case->update(['buyer_evidence' => $this->store($case, 'buyer', $photos)]);

            $this->messenger->toShop($order->tenant_id, null, 'return_requested',
                "Return {$case->reference} for order {$order->order_number}: the buyer is bringing ".count($lines).' item(s) back. Check them in the Poachy app within 48 hours of receiving them.');

            return $case->fresh();
        });
    }

    /** The shop has the item back: the 48 h inspection clock starts. */
    public function received(MarketplaceReturnCase $case): MarketplaceReturnCase
    {
        $this->assertStatus($case, [MarketplaceReturnCase::REQUESTED]);
        $case->update([
            'status' => MarketplaceReturnCase::RECEIVED,
            'received_at' => now(),
            'inspection_due_at' => now()->addHours((int) config('returns.inspection_hours', 48)),
        ]);

        return $case->fresh();
    }

    /**
     * The shop's inspection. Accept refunds now; partial or reject waits 48 h for a dispute.
     *
     * @param  array<int, UploadedFile>  $photos
     */
    public function inspect(MarketplaceReturnCase $case, string $decision, ?float $amount, ?string $reason, array $photos = []): MarketplaceReturnCase
    {
        $this->assertStatus($case, [MarketplaceReturnCase::RECEIVED]);
        $requested = (float) $case->requested_amount;
        $refund = match ($decision) {
            'accept' => $requested,
            'partial' => (float) $amount,
            default => 0.0,
        };
        if ($decision === 'partial' && ($refund <= 0 || $refund >= $requested)) {
            throw ValidationException::withMessages(['refund_amount' => 'A partial refund is more than 0 and less than KSh '.number_format($requested, 2).'.']);
        }
        if ($decision !== 'accept' && ! filled($reason)) {
            throw ValidationException::withMessages(['reason' => 'Say why, so the buyer understands.']);
        }

        $case->update([
            'shop_decision' => $decision, 'refund_amount' => round($refund, 2), 'shop_reason' => $reason,
            'shop_evidence' => $this->store($case, 'shop', $photos), 'inspected_at' => now(),
            'status' => $decision === 'accept' ? MarketplaceReturnCase::REFUNDING : MarketplaceReturnCase::REJECTED,
            'dispute_until' => $decision === 'accept' ? null : now()->addHours((int) config('returns.dispute_hours', 48)),
        ]);

        if ($decision === 'accept') {
            $this->refund($case->fresh(), $refund, 'return');
        } else {
            $this->tellBuyer($case, $decision === 'partial'
                ? 'The shop offers KSh '.number_format($refund, 2)." for return {$case->reference}: {$reason}. Not happy? Dispute it within 48 hours in your Poachy account."
                : "The shop did not accept return {$case->reference}: {$reason}. Not happy? Dispute it within 48 hours in your Poachy account.");
        }

        return $case->fresh();
    }

    /**
     * @param  array<int, UploadedFile>  $photos
     */
    public function dispute(MarketplaceReturnCase $case, string $reason, array $photos = []): MarketplaceReturnCase
    {
        $this->assertStatus($case, [MarketplaceReturnCase::REJECTED]);
        if ($case->dispute_until !== null && $case->dispute_until->isPast()) {
            throw ValidationException::withMessages(['case' => 'The 48 hours to dispute this decision have passed.']);
        }
        $case->update(['status' => MarketplaceReturnCase::DISPUTED, 'disputed_at' => now(), 'dispute_reason' => $reason,
            'dispute_evidence' => $this->store($case, 'dispute', $photos)]);
        Log::info('Return disputed', ['case' => $case->reference]);

        return $case->fresh();
    }

    /** Poachy's decision on a disputed or escalated return. */
    public function decide(MarketplaceReturnCase $case, string $decision, ?float $amount, ?string $note, string $decidedBy): MarketplaceReturnCase
    {
        $this->assertStatus($case, [MarketplaceReturnCase::DISPUTED, MarketplaceReturnCase::ESCALATED]);
        $requested = (float) $case->requested_amount;
        $refund = match ($decision) {
            'refund_full' => $requested,
            'refund_partial' => (float) $amount,
            'no_refund' => 0.0,
            default => throw ValidationException::withMessages(['decision' => 'Unknown decision.']),
        };
        if ($decision === 'refund_partial' && ($refund <= 0 || $refund >= $requested)) {
            throw ValidationException::withMessages(['refund_amount' => 'A partial refund is more than 0 and less than the requested amount.']);
        }

        $case->update(['admin_decision' => $decision, 'admin_note' => $note, 'decided_by' => $decidedBy, 'decided_at' => now(), 'refund_amount' => round($refund, 2)]);
        $refund > 0 ? $this->refund($case->fresh(), $refund, 'dispute') : $this->close($case, 'Poachy decided: no refund. '.($note ?? ''));

        return $case->fresh();
    }

    public function cancel(MarketplaceReturnCase $case): MarketplaceReturnCase
    {
        $this->assertStatus($case, [MarketplaceReturnCase::REQUESTED]);
        $case->update(['status' => MarketplaceReturnCase::CANCELLED, 'closed_at' => now()]);

        return $case->fresh();
    }

    /**
     * The clocks: no inspection in 48 h → Poachy; dispute window over → the shop's decision
     * stands; never brought back → expired.
     *
     * @return array{escalated: int, settled: int, expired: int}
     */
    public function runTimers(?Carbon $now = null): array
    {
        $now ??= now();
        $done = ['escalated' => 0, 'settled' => 0, 'expired' => 0];

        foreach (MarketplaceReturnCase::query()->where('status', MarketplaceReturnCase::RECEIVED)->where('inspection_due_at', '<', $now)->get() as $case) {
            $case->update(['status' => MarketplaceReturnCase::ESCALATED, 'escalated_at' => $now]);
            Log::warning('Return escalated: shop did not inspect in time', ['case' => $case->reference]);
            $done['escalated']++;
        }

        foreach (MarketplaceReturnCase::query()->where('status', MarketplaceReturnCase::REJECTED)->where('dispute_until', '<', $now)->get() as $case) {
            (float) $case->refund_amount > 0
                ? $this->refund($case, (float) $case->refund_amount, 'return')
                : $this->close($case, "The shop's decision stands.");
            $done['settled']++;
        }

        $cutoff = $now->copy()->subDays((int) config('returns.bring_back_days', 7));
        $done['expired'] = MarketplaceReturnCase::query()->where('status', MarketplaceReturnCase::REQUESTED)->where('created_at', '<', $cutoff)
            ->update(['status' => MarketplaceReturnCase::EXPIRED, 'closed_at' => $now]);
        // Perishables not brought back in time lapse much sooner: food kept at home can't be checked.
        $done['expired'] += MarketplaceReturnCase::query()->where('status', MarketplaceReturnCase::REQUESTED)->where('perishable', true)
            ->where('created_at', '<', $now->copy()->subHours((int) config('returns.perishable_bring_back_hours', 24)))
            ->update(['status' => MarketplaceReturnCase::EXPIRED, 'closed_at' => $now]);

        return $done;
    }

    /**
     * Accepted items go back on sale — except perishables, which are written off (item 51).
     */
    public function shouldRestock(MarketplaceReturnCase $case, string $decision): bool
    {
        return $decision !== 'reject' && $case->restocked_at === null && ! $case->perishable;
    }

    /**
     * Whether an order can still be returned, and why not.
     */
    public function assertReturnable(MarketplaceOrder $order): void
    {
        if ($order->order_status !== OrderStatus::Completed) {
            throw ValidationException::withMessages(['order' => 'Only collected or delivered orders can be returned. Cancel an order that has not reached you yet.']);
        }
        $window = (int) BusinessDetail::query()->where('tenant_id', $order->tenant_id)->value('return_window_days');
        if ($window <= 0) {
            throw ValidationException::withMessages(['order' => 'This shop does not take returns.']);
        }
        $handedOver = MarketplacePayoutLine::query()->where('order_id', $order->id)->where('type', MarketplacePayoutLine::SALE)->value('handed_over_at');
        $since = $handedOver ? Carbon::parse($handedOver) : $order->updated_at;
        if ($since->copy()->addDays($window)->isPast()) {
            throw ValidationException::withMessages(['order' => "This shop takes returns within {$window} days of collection or delivery; that time has passed."]);
        }
        if (MarketplaceReturnCase::query()->where('order_id', $order->id)->whereIn('status', MarketplaceReturnCase::OPEN)->exists()) {
            throw ValidationException::withMessages(['order' => 'This order already has an open return.']);
        }
    }

    /**
     * @param  array<int, array{amount: float}>  $lines
     */
    private function requestedAmount(MarketplaceOrder $order, array $lines, bool $shopAtFault): float
    {
        $items = array_sum(array_column($lines, 'amount'));
        $goods = (float) $order->subtotal + (float) $order->tax_amount;
        $everything = $goods > 0 && $items >= $goods - 0.01;

        if ($shopAtFault) {
            // The shop's mistake: items back, and the delivery fee too when the whole order returns.
            return round($items + ($everything ? (float) $order->delivery_fee : 0), 2);
        }

        // Changed mind: the buyer bears their share of the payment fee (and the trip).
        $line = MarketplacePayoutLine::query()->where('order_id', $order->id)->where('type', MarketplacePayoutLine::SALE)->first();
        $fee = $line && (float) $line->gross > 0 ? (float) $line->processing_fee * $items / (float) $line->gross : 0;

        return round(max(0, $items - $fee), 2);
    }

    /**
     * A perishable return: asked within `perishable_hours` of handover, for a fault on arrival (the
     * shop can't resell food that was taken home), with a photo of how it arrived.
     *
     * @param  array<int, UploadedFile>  $photos
     */
    private function assertPerishableReturnable(MarketplaceOrder $order, string $reasonCode, array $photos): void
    {
        $hours = (int) config('returns.perishable_hours', 24);
        $handedOver = MarketplacePayoutLine::query()->where('order_id', $order->id)->where('type', MarketplacePayoutLine::SALE)->value('handed_over_at');
        $since = $handedOver ? Carbon::parse($handedOver) : $order->updated_at;
        if ($since->copy()->addHours($hours)->isPast()) {
            throw ValidationException::withMessages(['order' => "Fresh food and drinks can be returned within {$hours} hours of collection or delivery; that time has passed."]);
        }
        if ($reasonCode === 'changed_mind') {
            throw ValidationException::withMessages(['reason_code' => 'Fresh food and drinks come back only if something was wrong when they arrived (wrong, damaged, spoiled or not as described).']);
        }
        if ($photos === []) {
            throw ValidationException::withMessages(['photos' => 'Add a photo showing how the item arrived.']);
        }
    }

    private function matches(int $marketplaceProductId, string $list): bool
    {
        $product = MarketplaceProduct::on('central')->with('marketplaceCategory:id,name')->find($marketplaceProductId);
        $names = mb_strtolower(trim(($product?->marketplaceCategory?->name ?? '').' '.($product?->tenant_category_name ?? '')));
        foreach ((array) config("returns.{$list}", []) as $keyword) {
            if ($names !== '' && str_contains($names, (string) $keyword)) {
                return true;
            }
        }

        return false;
    }

    private function refund(MarketplaceReturnCase $case, float $amount, string $kind): void
    {
        $case->update(['status' => MarketplaceReturnCase::REFUNDING]);
        $this->refunds->start($case->order, $amount, $kind, "Return {$case->reference}", $case);
    }

    private function close(MarketplaceReturnCase $case, string $message): void
    {
        $case->update(['status' => MarketplaceReturnCase::CLOSED, 'closed_at' => now()]);
        $this->tellBuyer($case, "Return {$case->reference} is closed. {$message}");
    }

    private function tellBuyer(MarketplaceReturnCase $case, string $text): void
    {
        $phone = $case->order?->customer?->phone;
        if (filled($phone)) {
            $this->messenger->sms((string) $phone, $text, 'return_update', $case->tenant_id);
        }
    }

    /**
     * @param  array<int, UploadedFile>  $photos
     * @return array<int, string>
     */
    private function store(MarketplaceReturnCase $case, string $who, array $photos): array
    {
        $paths = [];
        foreach (array_slice($photos, 0, (int) config('returns.max_photos', 4)) as $photo) {
            $paths[] = $photo->store("{$case->tenant_id}/{$case->reference}/{$who}", 'returns');
        }

        return $paths;
    }

    /**
     * @param  array<int, string>  $allowed
     */
    private function assertStatus(MarketplaceReturnCase $case, array $allowed): void
    {
        if (! in_array($case->status, $allowed, true)) {
            throw ValidationException::withMessages(['case' => "Return {$case->reference} is {$case->status}; that step is not possible now."]);
        }
    }
}
