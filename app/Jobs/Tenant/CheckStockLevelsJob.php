<?php

namespace App\Jobs\Tenant;

use App\Services\Tenant\Inventory\StockAlertService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CheckStockLevelsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 300; // 5 minutes
    public string $queue = 'sync-high';
    public array $backoff = [10, 30, 60];

    public function __construct()
    {
        // Set queue on construction as well
        $this->onQueue('sync-high');
    }

    /**
     * Execute the job.
     */
    public function handle(StockAlertService $stockAlertService): void
    {
        try {
            Log::info('Starting stock level check job', [
                'tenant_id' => tenant()->id,
            ]);

            $results = $stockAlertService->checkAllStores();

            Log::info('Stock level check job completed', [
                'tenant_id' => tenant()->id,
                'stores_checked' => $results['stores_checked'],
                'alerts_generated' => $results['total_alerts_generated'],
            ]);
        } catch (\Exception $e) {
            Log::error('Stock level check job failed', [
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
        Log::critical('Stock level check job failed permanently', [
            'tenant_id' => tenant()->id ?? 'unknown',
            'error' => $exception->getMessage(),
        ]);
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'tenant:' . (tenant()->id ?? 'unknown'),
            'inventory',
            'stock-alerts',
            'scheduled',
        ];
    }
}
