<?php

namespace App\Jobs\Tenant;

use App\Services\Tenant\Inventory\StockReservationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
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
        $orderId        = $this->orderPayload['order_id'];
        $outboundSyncId = $this->orderPayload['_outbound_sync_id'] ?? null;
        $reason         = 'Order cancelled';

        $reservationService->releaseAllReservationsForReference(
            'MarketplaceOrder',
            $orderId,
            $reason,
        );

        $this->respondToCentral($orderId, 'completed', null, $outboundSyncId);

        Log::info('Inbound cancellation processed — reservations released', [
            'order_id'  => $orderId,
            'reason'    => $reason,
            'tenant_id' => tenant()->id ?? 'unknown',
        ]);
    }

    private function respondToCentral(
        ?int $orderId,
        string $status,
        ?string $reason = null,
        ?int $outboundSyncId = null,
    ): void {
        if (! $outboundSyncId) {
            return;
        }

        $centralUrl = config('services.central_api.url') . '/api/v1/central/sync/inbound/outbound-sync-ack';
        $token      = config('services.central_api.token');

        try {
            Http::withToken($token)
                ->timeout(30)
                ->post($centralUrl, [
                    'outbound_sync_id' => $outboundSyncId,
                    'tenant_id'        => tenant()->id ?? null,
                    'status'           => $status,
                    'reason'           => $reason,
                    'tenant_response'  => ['order_id' => $orderId],
                ]);
        } catch (\Exception $e) {
            Log::error('Failed to send outbound sync ack for cancellation', [
                'order_id'         => $orderId,
                'outbound_sync_id' => $outboundSyncId,
                'error'            => $e->getMessage(),
            ]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        $orderId        = $this->orderPayload['order_id'] ?? null;
        $outboundSyncId = $this->orderPayload['_outbound_sync_id'] ?? null;

        Log::error('ProcessInboundCancellationSync job failed', [
            'order_id' => $orderId,
            'error'    => $exception->getMessage(),
        ]);

        if ($outboundSyncId) {
            $this->respondToCentral($orderId, 'failed', $exception->getMessage(), $outboundSyncId);
        }
    }
}
