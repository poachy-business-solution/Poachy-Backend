<?php

namespace App\Jobs\Central;

use App\Models\CentralPaymentLog;
use App\Models\Tenant;
use App\Services\Central\Billing\BillingTopupService;
use App\Services\Central\Marketplace\MarketplacePaymentService;
use App\Services\Central\Subscription\SubscriptionPaymentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessMpesaC2BConfirmationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;

    public int $tries = 5;

    /** @var array<int, int> */
    public array $backoff = [30, 60, 120, 300];

    /**
     * @param  array<string, mixed>  $parsedPayload  Output of MpesaService::parseC2BPayload()
     * @param  string  $paymentType  'billing' (shop top-up), 'marketplace', or legacy 'subscription'
     */
    public function __construct(
        public readonly array $parsedPayload,
        public readonly string $paymentType,
    ) {}

    public function handle(
        SubscriptionPaymentService $subscriptionService,
        MarketplacePaymentService $marketplaceService,
        BillingTopupService $topups,
    ): void {
        Log::channel('mpesa')->info('Processing C2B confirmation job', [
            'payment_type' => $this->paymentType,
            'transaction_id' => $this->parsedPayload['transaction_id'],
            'bill_ref_number' => $this->parsedPayload['bill_ref_number'],
            'amount' => $this->parsedPayload['amount'],
        ]);

        match ($this->paymentType) {
            'billing' => $this->creditShop($topups),
            // Jobs queued before trading-day billing replaced plan payments.
            'subscription' => $subscriptionService->processC2BConfirmation($this->parsedPayload),
            'marketplace' => $marketplaceService->processC2BConfirmation($this->parsedPayload),
            default => throw new \UnexpectedValueException("Unknown payment type: {$this->paymentType}"),
        };
    }

    /**
     * Credit a Paybill payment to the shop whose POA account it names. A payment to an unknown
     * account (Safaricom skipped validation) is logged as unmatched for an admin to assign.
     */
    private function creditShop(BillingTopupService $topups): void
    {
        $payload = $this->parsedPayload;
        $tenantId = Tenant::on('central')->where('mpesa_paybill_account', strtoupper(trim((string) $payload['bill_ref_number'])))->value('id');

        if ($tenantId === null) {
            Log::channel('mpesa')->warning('C2B payment to an unknown shop account — left unmatched', [
                'bill_ref_number' => $payload['bill_ref_number'],
                'transaction_id' => $payload['transaction_id'],
            ]);
            CentralPaymentLog::record('unmatched', 0, 'c2b_unmatched', [
                'amount' => $payload['amount'],
                'transaction_reference' => $payload['transaction_id'],
                'provider_reference' => $payload['transaction_id'],
                'raw_payload' => array_merge($payload, ['phone' => null]),
            ]);

            return;
        }

        $topups->creditFromMpesa(
            tenantId: (string) $tenantId,
            amount: (float) $payload['amount'],
            receipt: (string) $payload['transaction_id'],
            source: 'mpesa_c2b',
            phone: $payload['phone'] ?? null,
            payerName: $payload['first_name'] ?? null,
        );
    }

    public function failed(\Throwable $exception): void
    {
        Log::channel('mpesa')->error('C2B confirmation job permanently failed', [
            'payment_type' => $this->paymentType,
            'transaction_id' => $this->parsedPayload['transaction_id'] ?? null,
            'bill_ref_number' => $this->parsedPayload['bill_ref_number'] ?? null,
            'error' => $exception->getMessage(),
        ]);
    }
}
