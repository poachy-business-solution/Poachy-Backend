<?php

namespace App\Jobs\Tenant;

use App\Services\Tenant\Inventory\ExpiryAlertService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CheckBatchExpiriesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 300; // 5 minutes
    public string $queue = 'sync-high';
    public array $backoff = [10, 30, 60];

    public function __construct()
    {
        $this->onQueue('sync-high');
    }

    /**
     * Execute the job.
     */
    public function handle(ExpiryAlertService $expiryAlertService): void
    {
        try {
            Log::info('Starting batch expiry check job', [
                'tenant_id' => tenant()->id,
            ]);

            // First, mark expired batches
            $markedCount = $expiryAlertService->markExpiredBatches();

            // Then check all stores for alerts
            $results = $expiryAlertService->checkAllStores();

            Log::info('Batch expiry check job completed', [
                'tenant_id' => tenant()->id,
                'batches_marked_expired' => $markedCount,
                'stores_checked' => $results['stores_checked'],
                'alerts_generated' => $results['total_alerts_generated'],
            ]);
        } catch (\Exception $e) {
            Log::error('Batch expiry check job failed', [
                'tenant_id' => tenant()->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::critical('Batch expiry check job failed permanently', [
            'tenant_id' => tenant()->id ?? 'unknown',
            'error' => $exception->getMessage(),
        ]);
    }

    public function tags(): array
    {
        return [
            'tenant:' . (tenant()->id ?? 'unknown'),
            'inventory',
            'expiry-alerts',
            'scheduled',
        ];
    }
}
