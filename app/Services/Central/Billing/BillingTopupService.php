<?php

namespace App\Services\Central\Billing;

use App\Enums\Central\BillingLedgerType;
use App\Events\Central\Billing\BillingBalanceCredited;
use App\Exceptions\MpesaException;
use App\Helpers\PhoneNumber;
use App\Mail\Central\Billing\BillingReceiptMail;
use App\Models\BillingAccount;
use App\Models\BillingLedgerEntry;
use App\Models\BillingPrice;
use App\Models\BillingTopup;
use App\Models\BusinessDetail;
use App\Models\CentralPaymentLog;
use App\Models\Tenant;
use App\Services\Shared\Messaging\Messenger;
use App\Services\Shared\Mpesa\MpesaService;
use Illuminate\Support\Facades\Log;

/**
 * Money into a shop's Poachy balance: Paybill (C2B) payments to its POA account and STK prompts.
 * Every credit is idempotent on the M-Pesa receipt number and sends a receipt email.
 */
class BillingTopupService
{
    /** Daraja's per-transaction ceiling for STK. */
    public const MAX_STK_AMOUNT = 150000;

    public function __construct(
        private BillingLedgerService $ledger,
        private MpesaService $mpesa,
        private Messenger $messenger,
    ) {}

    /** The current minimum top-up (KSh 10 at launch). */
    public function minimumTopup(): float
    {
        return (float) (BillingPrice::forDate(now('Africa/Nairobi'))?->min_topup ?? 10);
    }

    /**
     * Credit a confirmed M-Pesa payment. Safe to call twice for the same receipt.
     */
    public function creditFromMpesa(
        string $tenantId,
        float $amount,
        string $receipt,
        string $source,
        ?string $phone = null,
        ?string $payerName = null,
    ): BillingLedgerEntry {
        $account = BillingAccount::query()->where('tenant_id', $tenantId)->first()
            ?? app(BillingAccountService::class)->openActive($tenantId);

        $alreadyCredited = BillingLedgerEntry::query()->where('idempotency_key', "mpesa:{$receipt}")->exists();

        $entry = $this->ledger->post(
            account: $account,
            type: BillingLedgerType::Topup,
            amount: round($amount, 2),
            description: $source === 'mpesa_stk' ? 'M-Pesa top-up (prompt)' : 'M-Pesa top-up (Paybill)',
            source: $source,
            reference: $receipt,
            idempotencyKey: "mpesa:{$receipt}",
            createdBy: 'system',
            meta: array_filter(['phone' => $phone ? PhoneNumber::mask($phone) : null, 'payer' => $payerName]),
        );

        if (! $alreadyCredited) {
            BillingBalanceCredited::dispatch($account->fresh(), $entry);
            $this->sendReceipt($tenantId, $entry, $source);
        }

        return $entry;
    }

    /**
     * Send an STK prompt for a top-up. With an idempotency key, a repeated request returns the
     * first top-up instead of prompting again.
     *
     * @throws MpesaException
     */
    public function startStk(
        string $tenantId,
        float $amount,
        string $phone,
        string $initiatedBy,
        ?string $idempotencyKey = null,
        ?int $paymentLinkId = null,
    ): BillingTopup {
        $key = $idempotencyKey !== null ? "{$tenantId}:{$idempotencyKey}" : null;

        if ($key !== null && ($existing = BillingTopup::query()->where('idempotency_key', $key)->first())) {
            return $existing;
        }

        $tenant = Tenant::query()->findOrFail($tenantId);
        $topup = BillingTopup::query()->create([
            'tenant_id' => $tenantId,
            'amount' => round($amount),
            'phone' => PhoneNumber::normalize($phone),
            'status' => BillingTopup::PENDING,
            'idempotency_key' => $key,
            'initiated_by' => $initiatedBy,
            'payment_link_id' => $paymentLinkId,
        ]);

        try {
            $stk = $this->mpesa->initiateSTKPush(
                phoneNumber: $topup->phone,
                amount: (float) $topup->amount,
                accountReference: (string) $tenant->mpesa_paybill_account,
                transactionDesc: 'Poachy topup',
                callbackUrl: config('mpesa.billing_stk_callback_url') ?: rtrim((string) config('app.url'), '/').'/api/v1/central/mpesa/stk/billing/callback',
            );
        } catch (MpesaException $e) {
            $topup->update(['status' => BillingTopup::FAILED, 'failure_reason' => $e->getMessage()]);
            throw $e;
        }

        $topup->update([
            'status' => BillingTopup::PROCESSING,
            'checkout_request_id' => $stk['checkout_request_id'],
            'merchant_request_id' => $stk['merchant_request_id'],
        ]);

        CentralPaymentLog::record('billing_topup', $topup->id, 'stk_initiated', [
            'tenant_id' => $tenantId,
            'amount' => (float) $topup->amount,
            'customer_phone' => PhoneNumber::mask($topup->phone),
            'transaction_reference' => $stk['checkout_request_id'],
        ]);

        return $topup->fresh();
    }

    /**
     * Apply an STK callback (output of MpesaService::parseSTKCallbackPayload). Idempotent.
     *
     * @param  array{transaction_reference: string|null, status: string, provider_reference: string|null, failure_reason: string|null, failure_code: string|null}  $parsed
     */
    public function handleStkCallback(array $parsed): ?BillingTopup
    {
        $topup = BillingTopup::query()->where('checkout_request_id', $parsed['transaction_reference'])->first();

        if ($topup === null) {
            Log::channel('mpesa')->warning('Billing STK callback for an unknown checkout request', ['checkout_request_id' => $parsed['transaction_reference']]);

            return null;
        }

        if ($topup->isFinal()) {
            return $topup;
        }

        if ($parsed['status'] !== 'success' || ! $parsed['provider_reference']) {
            $topup->update(['status' => BillingTopup::FAILED, 'failure_reason' => $parsed['failure_reason'] ?? 'Payment not completed']);
            CentralPaymentLog::record('billing_topup', $topup->id, 'payment_failed', [
                'tenant_id' => $topup->tenant_id,
                'result_code' => $parsed['failure_code'],
                'result_description' => $parsed['failure_reason'],
            ]);

            return $topup->fresh();
        }

        // The credit is idempotent on the receipt, so a repeated callback cannot double it.
        $entry = $this->creditFromMpesa($topup->tenant_id, (float) $topup->amount, (string) $parsed['provider_reference'], 'mpesa_stk', $topup->phone);
        $topup->update(['status' => BillingTopup::COMPLETED, 'mpesa_receipt' => $parsed['provider_reference'], 'billing_ledger_id' => $entry->id]);

        if ($topup->payment_link_id !== null) {
            app(PaymentLinkService::class)->markUsed($topup->payment_link_id);
        }
        CentralPaymentLog::record('billing_topup', $topup->id, 'payment_completed', [
            'tenant_id' => $topup->tenant_id,
            'amount' => (float) $topup->amount,
            'provider_reference' => $parsed['provider_reference'],
        ]);

        return $topup->fresh();
    }

    private function sendReceipt(string $tenantId, BillingLedgerEntry $entry, string $source): void
    {
        try {
            $tenant = Tenant::query()->find($tenantId);
            $shopName = BusinessDetail::query()->where('tenant_id', $tenantId)->value('business_name') ?? $tenant?->displayName() ?? 'your shop';

            $this->messenger->toShop($tenantId, new BillingReceiptMail(
                shopName: $shopName,
                amount: (float) $entry->amount,
                receipt: (string) $entry->reference,
                newBalance: (float) $entry->balance_after,
                accountNumber: (string) ($tenant?->mpesa_paybill_account ?? ''),
                paidAt: now('Africa/Nairobi')->format('j M Y, H:i'),
                method: $source === 'mpesa_stk' ? 'M-Pesa prompt' : 'M-Pesa Paybill',
            ));
        } catch (\Throwable $e) {
            Log::error('Billing receipt email failed', ['tenant_id' => $tenantId, 'error' => $e->getMessage()]);
        }
    }
}
