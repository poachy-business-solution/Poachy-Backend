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

/** Reserve & collect, shop side: the buyer cancelled or the 24 h passed — free the stock. */
class ProcessInboundReservationRelease implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;

    public int $tries = 5;

    /** @var array<int, int> */
    public array $backoff = [30, 60, 120];

    /** @param  array<string, mixed>  $payload */
    public function __construct(public array $payload) {}

    public function handle(StockReservationService $reservationService): void
    {
        $reservationId = (int) $this->payload['reservation_id'];
        $outboundSyncId = $this->payload['_outbound_sync_id'] ?? null;

        $released = $reservationService->releaseAllReservationsForReference(
            ProcessInboundReservationHold::REFERENCE_TYPE,
            $reservationId,
            $this->payload['reason'] ?? 'Reservation released',
        );

        $this->ack($reservationId, $outboundSyncId);

        Log::info('Reservation hold released', ['reservation_id' => $reservationId, 'released' => $released, 'tenant_id' => tenant()->id ?? 'unknown']);
    }

    private function ack(int $reservationId, ?int $outboundSyncId): void
    {
        if (! $outboundSyncId) {
            return;
        }

        try {
            Http::withToken(config('services.central_api.token'))
                ->timeout(30)
                ->post(config('services.central_api.url').'/api/v1/central/sync/inbound/outbound-sync-ack', [
                    'outbound_sync_id' => $outboundSyncId,
                    'tenant_id' => tenant()->id ?? null,
                    'status' => 'completed',
                    'tenant_response' => ['reservation_id' => $reservationId],
                ]);
        } catch (\Exception $e) {
            Log::error('Failed to ack reservation release', ['reservation_id' => $reservationId, 'error' => $e->getMessage()]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessInboundReservationRelease job failed', ['reservation_id' => $this->payload['reservation_id'] ?? null, 'error' => $exception->getMessage()]);
    }
}
