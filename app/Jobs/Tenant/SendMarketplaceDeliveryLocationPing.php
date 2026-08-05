<?php

namespace App\Jobs\Tenant;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Fire-and-forget GPS location ping for an in-progress delivery. Deliberately
 * NOT part of the SyncQueueOutbound/ACK machinery — pings are high-frequency
 * and ephemeral (only the latest matters, a dropped one is meaningless), the
 * opposite of what that persistent, retried, ACK'd system is for.
 */
class SendMarketplaceDeliveryLocationPing implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 15;

    public int $tries = 1;

    public function __construct(
        public int $centralOrderId,
        public float $latitude,
        public float $longitude,
    ) {}

    public function handle(): void
    {
        $url = config('services.central_api.url')."/api/v1/central/marketplace-orders/{$this->centralOrderId}/delivery/location";

        try {
            $response = Http::timeout(10)
                ->withToken(config('services.central_api.token'))
                ->post($url, [
                    'latitude' => $this->latitude,
                    'longitude' => $this->longitude,
                ]);

            if (! $response->successful()) {
                Log::warning('Marketplace delivery location ping rejected by central', [
                    'central_order_id' => $this->centralOrderId,
                    'status' => $response->status(),
                ]);
            }
        } catch (\Exception $e) {
            // Best-effort — a dropped ping is meaningless, never worth retrying
            // or failing loudly over.
            Log::warning('Marketplace delivery location ping failed to send', [
                'central_order_id' => $this->centralOrderId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
