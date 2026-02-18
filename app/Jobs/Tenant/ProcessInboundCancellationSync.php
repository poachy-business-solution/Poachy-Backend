<?php

namespace App\Jobs\Tenant;

use App\Services\Tenant\Inventory\StockReservationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessInboundCancellationSync implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 180;

    public int $tries = 10;

    public int $maxExceptions = 10;

    /** @var array<int, int> */
    public array $backoff = [60, 120, 300, 600];

    public function __construct(
        public array $orderPayload,
    ) {}

    public function handle(StockReservationService $reservationService): void
    {
        $orderId = $this->orderPayload['order_id'];
        $reason  = 'Order cancelled';

        $reservationService->releaseAllReservationsForReference(
            'MarketplaceOrder',
            $orderId,
            $reason,
        );

        Log::info('Inbound cancellation processed — reservations released', [
            'order_id'  => $orderId,
            'reason'    => $reason,
            'tenant_id' => tenant()->id ?? 'unknown',
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessInboundCancellationSync job failed', [
            'order_id' => $this->orderPayload['order_id'] ?? null,
            'error'    => $exception->getMessage(),
        ]);
    }
}
