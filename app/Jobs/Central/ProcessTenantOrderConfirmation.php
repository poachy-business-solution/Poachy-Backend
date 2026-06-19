<?php

namespace App\Jobs\Central;

use App\Services\Central\Marketplace\MarketplaceOrderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessTenantOrderConfirmation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 180;

    public int $tries = 10;

    public int $maxExceptions = 10;

    /** @var array<int, int> */
    public array $backoff = [60, 120, 300, 600];

    public function __construct(
        public int $orderId,
        public string $status,
        public ?string $reason = null,
        public array $tenantResponse = [],
    ) {}

    public function handle(MarketplaceOrderService $orderService): void
    {
        if ($this->status === 'confirmed') {
            $orderService->confirmOrderFromTenant($this->orderId, $this->tenantResponse);

            Log::info('Tenant order confirmation processed', [
                'order_id' => $this->orderId,
                'status'   => 'confirmed',
            ]);
        } else {
            $orderService->handleReservationFailure($this->orderId, [
                'reason' => $this->reason ?? 'Reservation failed by tenant',
            ]);

            Log::info('Tenant reservation failure processed', [
                'order_id' => $this->orderId,
                'reason'   => $this->reason,
            ]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessTenantOrderConfirmation job failed', [
            'order_id' => $this->orderId,
            'status'   => $this->status,
            'error'    => $exception->getMessage(),
        ]);
    }
}
