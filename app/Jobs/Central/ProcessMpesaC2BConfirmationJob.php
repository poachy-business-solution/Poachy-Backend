<?php

namespace App\Jobs\Central;

use App\Exceptions\MpesaException;
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
     * @param  string  $paymentType  'subscription' or 'marketplace'
     */
    public function __construct(
        public readonly array $parsedPayload,
        public readonly string $paymentType,
    ) {}

    public function handle(
        SubscriptionPaymentService $subscriptionService,
        MarketplacePaymentService $marketplaceService,
    ): void {
        Log::channel('mpesa')->info('Processing C2B confirmation job', [
            'payment_type'    => $this->paymentType,
            'transaction_id'  => $this->parsedPayload['transaction_id'],
            'bill_ref_number' => $this->parsedPayload['bill_ref_number'],
            'amount'          => $this->parsedPayload['amount'],
        ]);

        match ($this->paymentType) {
            'subscription' => $subscriptionService->processC2BConfirmation($this->parsedPayload),
            'marketplace'  => $marketplaceService->processC2BConfirmation($this->parsedPayload),
            default        => throw new \UnexpectedValueException("Unknown payment type: {$this->paymentType}"),
        };
    }

    public function failed(\Throwable $exception): void
    {
        Log::channel('mpesa')->error('C2B confirmation job permanently failed', [
            'payment_type'    => $this->paymentType,
            'transaction_id'  => $this->parsedPayload['transaction_id'] ?? null,
            'bill_ref_number' => $this->parsedPayload['bill_ref_number'] ?? null,
            'error'           => $exception->getMessage(),
        ]);
    }
}
