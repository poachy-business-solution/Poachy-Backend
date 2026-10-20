<?php

namespace App\Services\Central\Payouts;

use App\Helpers\PhoneNumber;
use App\Models\BusinessDetail;
use App\Models\MarketplacePayout;
use App\Models\MarketplacePayoutLine;
use App\Services\Shared\Messaging\Messenger;
use App\Services\Shared\Mpesa\MpesaService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * The nightly payout batch: every shop's available ledger lines become one M-Pesa B2C transfer
 * to the shop's payout number. Results arrive on a signed callback; failures are re-sent on a
 * back-off and then handed to a person; a timeout is never re-sent automatically (the money may
 * have moved).
 */
class PayoutService
{
    public function __construct(
        private readonly MpesaService $mpesa,
        private readonly Messenger $messenger,
    ) {}

    /**
     * @return array{created: int, sent: int, retried: int, skipped: array<string, string>}
     */
    public function runBatch(?Carbon $asOf = null, bool $retriesOnly = false): array
    {
        $asOf ??= now();
        $summary = ['created' => 0, 'sent' => 0, 'retried' => 0, 'skipped' => []];

        $tenants = $retriesOnly ? collect() : MarketplacePayoutLine::query()->available($asOf)->distinct()->pluck('tenant_id');
        foreach ($tenants as $tenantId) {
            $payout = $this->createPayout((string) $tenantId, $asOf, $reason);
            if ($payout === null) {
                $summary['skipped'][(string) $tenantId] = $reason;

                continue;
            }
            $summary['created']++;
            $summary['sent'] += $this->send($payout) ? 1 : 0;
        }

        $due = MarketplacePayout::query()
            ->where('status', MarketplacePayout::FAILED)
            ->where('next_attempt_at', '<=', $asOf)
            ->get();
        foreach ($due as $payout) {
            $summary['retried']++;
            $this->send($payout);
        }

        return $summary;
    }

    /**
     * Gather a shop's available lines into one payout, in whole shillings (cents carry forward
     * as a pair of adjustment lines). Null, with the reason, when nothing should be sent tonight.
     */
    public function createPayout(string $tenantId, Carbon $asOf, ?string &$reason = null): ?MarketplacePayout
    {
        $phone = $this->payoutPhone($tenantId);
        if ($phone === null) {
            $reason = 'no payout number';
            $this->alertShopOnce($tenantId, 'payout_no_number', 'Poachy has money to pay you but no M-Pesa number for payouts. Add one in your business account.');

            return null;
        }

        return DB::connection('central')->transaction(function () use ($tenantId, $asOf, $phone, &$reason) {
            $lines = MarketplacePayoutLine::query()->where('tenant_id', $tenantId)->available($asOf)->lockForUpdate()->get();
            $total = round((float) $lines->sum('net'), 2);
            $transferFee = (float) config('payouts.fees.transfer_fee', 0);
            $whole = (int) floor($total - $transferFee);

            if ($lines->isEmpty() || $whole < (int) config('payouts.min_amount', 10)) {
                $reason = $lines->isEmpty() ? 'nothing available' : 'below the minimum';

                return null;
            }

            $payout = MarketplacePayout::query()->create([
                'tenant_id' => $tenantId,
                'reference' => 'PAY-'.$asOf->copy()->setTimezone('Africa/Nairobi')->format('Ymd').'-'.Str::upper(Str::random(6)),
                'phone' => $phone,
                'amount' => $whole,
                'status' => MarketplacePayout::PROCESSING,
            ]);

            MarketplacePayoutLine::query()->whereIn('id', $lines->pluck('id'))->update(['payout_id' => $payout->id]);

            if ($transferFee > 0) {
                $this->adjustment($tenantId, $payout->id, 'M-Pesa transfer fee', -$transferFee);
            }

            // Cents and anything below a whole shilling wait for the next payout.
            $carry = round($total - $transferFee - $whole, 2);
            if ($carry > 0) {
                $this->adjustment($tenantId, $payout->id, 'Carried to the next payout', -$carry);
                $this->adjustment($tenantId, null, 'Carried from '.$payout->reference, $carry);
            }

            $payout->update(['lines_count' => $payout->lines()->count()]);

            return $payout->fresh();
        });
    }

    /** Send (or re-send) a payout through the B2C gateway. */
    public function send(MarketplacePayout $payout): bool
    {
        $payout->increment('attempts');

        try {
            $result = $this->mpesa->gateway()->b2c(
                phone: (string) $payout->phone,
                amount: (float) $payout->amount,
                occasion: $payout->reference,
                remarks: 'Poachy marketplace payout',
                resultUrl: $this->callbackUrl('result'),
                timeoutUrl: $this->callbackUrl('timeout'),
            );
        } catch (Throwable $e) {
            $this->fail($payout, 'Could not start the transfer: '.$e->getMessage());

            return false;
        }

        $payout->update([
            'status' => MarketplacePayout::PROCESSING,
            'conversation_id' => $result['conversation_id'] ?? null,
            'originator_conversation_id' => $result['originator_conversation_id'] ?? null,
            'next_attempt_at' => null,
            'failure_reason' => null,
        ]);
        Log::info('Payout sent', ['payout' => $payout->reference, 'amount' => $payout->amount, 'attempt' => $payout->attempts]);

        return true;
    }

    /**
     * Safaricom's B2C result. Only a processing payout with this ConversationID is touched, and a
     * success must carry the payout's own amount.
     *
     * @param  array<string, mixed>  $payload
     */
    public function handleResult(array $payload): ?MarketplacePayout
    {
        $result = (array) ($payload['Result'] ?? []);
        $payout = $this->processingFor((string) ($result['ConversationID'] ?? ''));
        if ($payout === null) {
            return null;
        }

        if ((string) ($result['ResultCode'] ?? '') !== '0') {
            $this->fail($payout, (string) ($result['ResultDesc'] ?? 'The transfer failed.'));

            return $payout->fresh();
        }

        $params = collect($result['ResultParameters']['ResultParameter'] ?? [])->pluck('Value', 'Key');
        if ((int) floor((float) $params->get('TransactionAmount', -1)) !== (int) floor((float) $payout->amount)) {
            $payout->update(['status' => MarketplacePayout::NEEDS_CHECK, 'failure_reason' => 'The amount Safaricom reported does not match this payout.']);
            $this->alertFinance($payout, 'amount mismatch');

            return $payout->fresh();
        }

        $payout->update([
            'status' => MarketplacePayout::PAID,
            'mpesa_receipt' => (string) ($params->get('TransactionReceipt') ?? $result['TransactionID'] ?? ''),
            'paid_at' => now(),
            'failure_reason' => null,
        ]);
        $this->messenger->sms(
            (string) $payout->phone,
            'Poachy paid you KSh '.number_format((float) $payout->amount).' for your online orders. M-Pesa ref '.$payout->mpesa_receipt.' · '.$payout->reference,
            'payout_paid',
            $payout->tenant_id,
        );
        Log::info('Payout paid', ['payout' => $payout->reference, 'receipt' => $payout->mpesa_receipt]);

        return $payout->fresh();
    }

    /**
     * Safaricom's timeout: the result is unknown, so nothing is re-sent automatically.
     *
     * @param  array<string, mixed>  $payload
     */
    public function handleTimeout(array $payload): ?MarketplacePayout
    {
        $result = (array) ($payload['Result'] ?? $payload);
        $payout = $this->processingFor((string) ($result['ConversationID'] ?? ''));
        if ($payout === null) {
            return null;
        }

        $payout->update(['status' => MarketplacePayout::NEEDS_CHECK, 'failure_reason' => 'Safaricom timed out; check the transfer before sending again.']);
        $this->alertFinance($payout, 'timeout');

        return $payout->fresh();
    }

    /** The secret path segment Safaricom calls back on (derived from the app key, never stored). */
    public function callbackToken(): string
    {
        return substr(hash_hmac('sha256', 'marketplace-payout-callback', (string) config('app.key')), 0, 40);
    }

    private function callbackUrl(string $kind): string
    {
        return rtrim((string) config('app.url'), '/')."/api/v1/central/mpesa/b2c/payout/{$kind}/".$this->callbackToken();
    }

    private function processingFor(string $conversationId): ?MarketplacePayout
    {
        if ($conversationId === '') {
            return null;
        }

        return MarketplacePayout::query()->where('conversation_id', $conversationId)->where('status', MarketplacePayout::PROCESSING)->first();
    }

    private function fail(MarketplacePayout $payout, string $reason): void
    {
        $delays = (array) config('payouts.retry_delays', [60, 360, 1440]);
        $retry = $delays[$payout->attempts - 1] ?? null;

        $payout->update([
            'status' => $retry === null ? MarketplacePayout::FAILED_FINAL : MarketplacePayout::FAILED,
            'next_attempt_at' => $retry === null ? null : now()->addMinutes((int) $retry),
            'failure_reason' => mb_substr($reason, 0, 500),
        ]);
        Log::warning('Payout failed', ['payout' => $payout->reference, 'attempt' => $payout->attempts, 'final' => $retry === null, 'reason' => $reason]);

        if ($retry === null) {
            $this->alertFinance($payout, 'out of retries');
            $this->messenger->toShop($payout->tenant_id, null, 'payout_failed',
                'Poachy could not send your payout of KSh '.number_format((float) $payout->amount).' ('.$payout->reference.'). Our team is on it; check your payout number in your business account.');
        }
    }

    private function adjustment(string $tenantId, ?int $payoutId, string $description, float $amount): void
    {
        MarketplacePayoutLine::query()->create([
            'tenant_id' => $tenantId, 'type' => MarketplacePayoutLine::ADJUSTMENT, 'description' => $description,
            'gross' => $amount, 'net' => $amount, 'eligible_at' => now(), 'payout_id' => $payoutId,
        ]);
    }

    private function payoutPhone(string $tenantId): ?string
    {
        $details = BusinessDetail::query()->where('tenant_id', $tenantId)->first(['payout_phone', 'business_phone']);
        $raw = $details?->payout_phone ?: $details?->business_phone;
        if (! filled($raw)) {
            return null;
        }

        try {
            return PhoneNumber::normalize((string) $raw);
        } catch (Throwable) {
            return null;
        }
    }

    private function alertFinance(MarketplacePayout $payout, string $what): void
    {
        Log::critical('Payout needs a person', ['payout' => $payout->reference, 'tenant_id' => $payout->tenant_id, 'why' => $what]);
        $to = (string) config('payouts.alert_email');
        if ($to === '') {
            return;
        }

        try {
            Mail::raw(
                "Payout {$payout->reference} (tenant {$payout->tenant_id}, KSh {$payout->amount}, phone {$payout->phone}) needs attention: {$what}.\nReason: {$payout->failure_reason}\nAttempts: {$payout->attempts}",
                fn ($message) => $message->to($to)->subject("Payout {$payout->reference}: {$what}"),
            );
        } catch (Throwable $e) {
            Log::error('Payout alert email failed', ['error' => $e->getMessage()]);
        }
    }

    private function alertShopOnce(string $tenantId, string $template, string $text): void
    {
        $key = "payout-alert:{$template}:{$tenantId}:".now()->toDateString();
        if (! cache()->store()->add($key, 1, now()->addDay())) {
            return;
        }
        $this->messenger->toShop($tenantId, null, $template, $text);
    }
}
