<?php

namespace App\Services\Central\Payouts;

use App\Enums\Central\MarketplacePaymentStatus;
use App\Models\MarketplaceOrder;
use App\Models\MarketplacePayoutLine;
use Illuminate\Support\Facades\Log;

/**
 * What Poachy owes each shop. A paid online order earns a ledger line when it is handed over
 * (collected or delivered); it becomes payable `payouts.hold_hours` later.
 */
class PayoutLedgerService
{
    public function __construct(private readonly CommissionService $commission) {}

    /**
     * Record a handover. Idempotent: one sale line per order. Orders paid at the shop (not
     * through Poachy) earn nothing here.
     */
    public function recordHandover(MarketplaceOrder $order): ?MarketplacePayoutLine
    {
        $payment = $order->payments()->where('payment_status', MarketplacePaymentStatus::Completed->value)->latest('id')->first();
        if ($payment === null) {
            return null;
        }

        $existing = MarketplacePayoutLine::query()->where('order_id', $order->id)->where('type', MarketplacePayoutLine::SALE)->first();
        if ($existing !== null) {
            return $existing;
        }

        $gross = round((float) $payment->amount, 2);
        $method = $payment->payment_method->value;
        $feePercent = $method === 'card' ? (float) config('payouts.fees.card_percent') : (float) config('payouts.fees.mpesa_percent');
        $fee = round($gross * $feePercent / 100, 2);
        $rate = $this->commission->rateFor($order);
        $commission = $this->commission->amountFor($order, $rate);
        $handedOver = now();

        $line = MarketplacePayoutLine::query()->create([
            'tenant_id' => $order->tenant_id,
            'order_id' => $order->id,
            'type' => MarketplacePayoutLine::SALE,
            'description' => "Order {$order->order_number}",
            'gross' => $gross,
            'processing_fee' => $fee,
            'commission_rate' => $rate,
            'commission_amount' => $commission,
            'net' => round($gross - $fee - $commission, 2),
            'source' => $order->source(),
            'payment_method' => $method,
            'handed_over_at' => $handedOver,
            'eligible_at' => $handedOver->copy()->addHours((int) config('payouts.hold_hours', 24)),
        ]);

        Log::info('Payout line recorded at handover', ['order_id' => $order->id, 'tenant_id' => $order->tenant_id, 'net' => $line->net]);

        return $line;
    }

    /**
     * Take a refund back from the shop's next payouts. Only for orders that earned a payout line
     * (handed over): before handover the shop was never owed the money. Payable at once, so the
     * next batch nets it off; one clawback per refund.
     */
    public function recordClawback(MarketplaceOrder $order, float $amount, string $reason, ?int $refundId = null): ?MarketplacePayoutLine
    {
        $earned = MarketplacePayoutLine::query()->where('order_id', $order->id)->where('type', MarketplacePayoutLine::SALE)->exists();
        if (! $earned || $amount <= 0) {
            return null;
        }

        return MarketplacePayoutLine::query()->firstOrCreate(
            ['order_id' => $order->id, 'type' => MarketplacePayoutLine::CLAWBACK, 'refund_id' => $refundId],
            [
                'tenant_id' => $order->tenant_id,
                'description' => "Refund on order {$order->order_number}: {$reason}",
                'gross' => -abs($amount),
                'net' => -abs($amount),
                'eligible_at' => now(),
            ],
        );
    }
}
