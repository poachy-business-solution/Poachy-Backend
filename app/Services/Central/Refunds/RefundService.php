<?php

namespace App\Services\Central\Refunds;

use App\Enums\Central\MarketplacePaymentStatus;
use App\Helpers\PhoneNumber;
use App\Models\MarketplaceOrder;
use App\Models\MarketplaceOrderPayment;
use App\Models\MarketplaceRefund;
use App\Models\MarketplaceReturnCase;
use App\Services\Central\Payouts\PayoutLedgerService;
use App\Services\Shared\Card\CardGateway;
use App\Services\Shared\Messaging\Messenger;
use App\Services\Shared\Mpesa\MpesaService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Sends money back through the gateway that took it: card → Paystack refund; the whole of an
 * M-Pesa payment → reversal; part of one → B2C to the buyer's number. When the money has left,
 * the payment is marked refunded, the shop pays it back from its next payouts (if it was ever
 * owed the order) and the buyer is told. Idempotent per (kind, order, case, amount).
 */
class RefundService
{
    public function __construct(
        private readonly MpesaService $mpesa,
        private readonly CardGateway $cards,
        private readonly PayoutLedgerService $ledger,
        private readonly Messenger $messenger,
    ) {}

    public function start(MarketplaceOrder $order, float $amount, string $kind, string $reason, ?MarketplaceReturnCase $case = null, ?string $attempt = null): ?MarketplaceRefund
    {
        $amount = round($amount, 2);
        $payment = $order->payments()->whereIn('payment_status', [MarketplacePaymentStatus::Completed->value, MarketplacePaymentStatus::Refunded->value])->latest('id')->first();
        if ($payment === null || $amount <= 0) {
            return null;
        }

        $key = "{$kind}:{$order->id}:".($case?->id ?? 'order').':'.number_format($amount, 2, '.', '').($attempt !== null ? ":{$attempt}" : '');
        $existing = MarketplaceRefund::query()->where('idempotency_key', $key)->first();
        if ($existing !== null) {
            return $existing;
        }

        $alreadyBack = (float) MarketplaceRefund::query()->where('payment_id', $payment->id)->whereIn('status', [MarketplaceRefund::PROCESSING, MarketplaceRefund::COMPLETED])->sum('amount');
        $amount = min($amount, round((float) $payment->amount - $alreadyBack, 2));
        if ($amount <= 0) {
            return null;
        }

        $method = $this->methodFor($payment, $amount, $alreadyBack);
        $refund = MarketplaceRefund::query()->create([
            'order_id' => $order->id, 'payment_id' => $payment->id, 'tenant_id' => $order->tenant_id, 'return_case_id' => $case?->id,
            'kind' => $kind, 'amount' => $amount, 'method' => $method, 'status' => MarketplaceRefund::PROCESSING,
            'reason' => mb_substr($reason, 0, 500), 'phone' => $method === 'card' ? null : $this->buyerPhone($order, $payment),
            'idempotency_key' => $key,
        ]);

        try {
            match ($method) {
                'card' => $this->card($refund, $payment),
                'mpesa_reversal' => $this->reversal($refund, $payment),
                default => $this->b2c($refund, $order),
            };
        } catch (Throwable $e) {
            $this->fail($refund, $e->getMessage());
        }

        return $refund->fresh();
    }

    /**
     * Safaricom's reversal / B2C result for a refund.
     *
     * @param  array<string, mixed>  $payload
     */
    public function handleResult(array $payload): ?MarketplaceRefund
    {
        $result = (array) ($payload['Result'] ?? []);
        $refund = MarketplaceRefund::query()->where('conversation_id', (string) ($result['ConversationID'] ?? '-'))->where('status', MarketplaceRefund::PROCESSING)->first();
        if ($refund === null) {
            return null;
        }

        if ((string) ($result['ResultCode'] ?? '') !== '0') {
            $this->fail($refund, (string) ($result['ResultDesc'] ?? 'Safaricom refused the refund.'));

            return $refund->fresh();
        }

        $this->complete($refund, (string) ($result['TransactionID'] ?? ''));

        return $refund->fresh();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handleTimeout(array $payload): ?MarketplaceRefund
    {
        $result = (array) ($payload['Result'] ?? $payload);
        $refund = MarketplaceRefund::query()->where('conversation_id', (string) ($result['ConversationID'] ?? '-'))->where('status', MarketplaceRefund::PROCESSING)->first();
        if ($refund !== null) {
            // Unknown outcome: never re-sent automatically.
            $refund->update(['failure_reason' => 'Safaricom timed out; check before sending again.']);
            $this->alertFinance($refund, 'timeout');
        }

        return $refund;
    }

    /**
     * Paystack's refund.processed / refund.failed webhook. A real card refund is "pending" when
     * asked for and only settles later, so this is what completes it (and claws the shop back).
     * Matched on the refund id, else on the charge reference and amount.
     *
     * @param  array<string, mixed>  $data
     */
    public function handleCardRefundEvent(string $event, array $data): ?MarketplaceRefund
    {
        $refundId = (string) ($data['id'] ?? $data['refund_reference'] ?? '');
        $chargeReference = (string) ($data['transaction_reference'] ?? $data['transaction']['reference'] ?? '');
        $amount = isset($data['amount']) ? round(((float) $data['amount']) / 100, 2) : null;

        $open = fn () => MarketplaceRefund::query()->where('method', 'card')->where('status', MarketplaceRefund::PROCESSING);
        $refund = $refundId !== '' ? $open()->where('provider_reference', $refundId)->first() : null;

        if ($refund === null && $chargeReference !== '') {
            $paymentIds = MarketplaceOrderPayment::on('central')->where('transaction_reference', $chargeReference)->pluck('id');
            $refund = $open()->whereIn('payment_id', $paymentIds)
                ->when($amount !== null, fn ($q) => $q->where('amount', $amount))
                ->oldest('id')->first();
        }

        if ($refund === null) {
            Log::info('Paystack refund event for no refund in flight', ['event' => $event, 'refund_id' => $refundId, 'reference' => $chargeReference]);

            return null;
        }

        $event === 'refund.processed'
            ? $this->complete($refund, $refundId !== '' ? $refundId : $refund->provider_reference)
            : $this->fail($refund, 'Paystack could not refund the card ('.($data['status'] ?? 'failed').').');

        return $refund->fresh();
    }

    /** A person re-sends a refund the gateway refused (a new refund row; the failed one stays as history). */
    public function retry(MarketplaceRefund $failed): MarketplaceRefund
    {
        if ($failed->status !== MarketplaceRefund::FAILED) {
            throw new \RuntimeException("Refund #{$failed->id} is {$failed->status}; only failed refunds can be re-sent.");
        }
        $refund = $this->start($failed->order, (float) $failed->amount, $failed->kind, (string) $failed->reason, $failed->returnCase, "retry-{$failed->id}");
        if ($refund === null) {
            throw new \RuntimeException('Nothing left to refund on this payment.');
        }

        return $refund;
    }

    public function complete(MarketplaceRefund $refund, ?string $providerReference): void
    {
        if ($refund->status === MarketplaceRefund::COMPLETED) {
            return;
        }

        $refund->update(['status' => MarketplaceRefund::COMPLETED, 'provider_reference' => $providerReference ?: $refund->provider_reference, 'completed_at' => now(), 'failure_reason' => null]);

        $payment = MarketplaceOrderPayment::on('central')->find($refund->payment_id);
        $total = (float) MarketplaceRefund::query()->where('payment_id', $refund->payment_id)->where('status', MarketplaceRefund::COMPLETED)->sum('amount');
        $payment?->markAsRefunded($total, $refund->provider_reference);

        $order = $refund->order;
        $this->ledger->recordClawback($order, (float) $refund->amount, $refund->reason ?? $refund->kind, $refund->id);

        if ($refund->returnCase !== null && $refund->returnCase->status === MarketplaceReturnCase::REFUNDING) {
            $refund->returnCase->update(['status' => MarketplaceReturnCase::REFUNDED, 'closed_at' => now()]);
        }

        $phone = $refund->phone ?? $order->customer?->phone;
        if (filled($phone)) {
            $how = $refund->method === 'card' ? 'to your card' : 'to your M-Pesa';
            $this->messenger->sms((string) $phone, 'Poachy refunded KSh '.number_format((float) $refund->amount, 2)." {$how} for order {$order->order_number}.", 'refund_completed', $order->tenant_id);
        }
        Log::info('Refund completed', ['refund_id' => $refund->id, 'order_id' => $order->id, 'amount' => $refund->amount, 'method' => $refund->method]);
    }

    /** The secret path segment for refund callbacks (derived from the app key, never stored). */
    public function callbackToken(): string
    {
        return substr(hash_hmac('sha256', 'marketplace-refund-callback', (string) config('app.key')), 0, 40);
    }

    private function methodFor(MarketplaceOrderPayment $payment, float $amount, float $alreadyBack): string
    {
        if ($payment->payment_method->value === 'card') {
            return 'card';
        }

        // A reversal returns the whole transaction; a part goes by B2C to the buyer.
        $whole = $alreadyBack <= 0 && abs($amount - (float) $payment->amount) < 0.01;

        return $whole && filled($payment->provider_reference) ? 'mpesa_reversal' : 'mpesa_b2c';
    }

    private function card(MarketplaceRefund $refund, MarketplaceOrderPayment $payment): void
    {
        $result = $this->cards->refund((string) $payment->transaction_reference, (float) $refund->amount, (string) $refund->reason);
        $refund->update(['provider_reference' => (string) ($result['refund_id'] ?? '')]);
        if (($result['status'] ?? '') === 'processed') {
            $this->complete($refund, (string) ($result['refund_id'] ?? ''));
        }
    }

    private function reversal(MarketplaceRefund $refund, MarketplaceOrderPayment $payment): void
    {
        $result = $this->mpesa->gateway()->reverse((string) $payment->provider_reference, (float) $refund->amount, 'Poachy refund '.$refund->id,
            $this->callbackUrl('result'), $this->callbackUrl('timeout'));
        $refund->update(['conversation_id' => $result['conversation_id'] ?? null]);
    }

    private function b2c(MarketplaceRefund $refund, MarketplaceOrder $order): void
    {
        if (! filled($refund->phone)) {
            throw new \RuntimeException('No M-Pesa number to refund to.');
        }
        $result = $this->mpesa->gateway()->b2c((string) $refund->phone, (float) $refund->amount, "Refund {$order->order_number}", 'Poachy refund',
            $this->callbackUrl('result'), $this->callbackUrl('timeout'));
        $refund->update(['conversation_id' => $result['conversation_id'] ?? null]);
    }

    private function fail(MarketplaceRefund $refund, string $reason): void
    {
        $refund->update(['status' => MarketplaceRefund::FAILED, 'failure_reason' => mb_substr($reason, 0, 500)]);
        Log::warning('Refund failed', ['refund_id' => $refund->id, 'reason' => $reason]);
        $this->alertFinance($refund, 'failed');
    }

    private function buyerPhone(MarketplaceOrder $order, MarketplaceOrderPayment $payment): ?string
    {
        $raw = $payment->payment_metadata['phone_number'] ?? $order->customer?->phone;
        try {
            return filled($raw) ? PhoneNumber::normalize((string) $raw) : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function callbackUrl(string $kind): string
    {
        return rtrim((string) config('app.url'), '/')."/api/v1/central/mpesa/refund/{$kind}/".$this->callbackToken();
    }

    private function alertFinance(MarketplaceRefund $refund, string $what): void
    {
        Log::critical('Refund needs a person', ['refund_id' => $refund->id, 'order_id' => $refund->order_id, 'why' => $what]);
        $to = (string) config('payouts.alert_email');
        if ($to === '') {
            return;
        }
        try {
            Mail::raw("Refund #{$refund->id} (order {$refund->order_id}, KSh {$refund->amount}, {$refund->method}) needs attention: {$what}.\n{$refund->failure_reason}",
                fn ($m) => $m->to($to)->subject("Refund #{$refund->id}: {$what}"));
        } catch (Throwable $e) {
            Log::error('Refund alert email failed', ['error' => $e->getMessage()]);
        }
    }
}
