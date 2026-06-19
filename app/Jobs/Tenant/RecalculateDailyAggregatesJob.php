<?php

namespace App\Jobs\Tenant;

use App\Services\Tenant\Sales\SalesDailyAggregateService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RecalculateDailyAggregatesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 2;
    public $timeout = 300; // 5 minutes
    public $backoff = [60, 120];

    protected string $tenantId;
    protected string $aggregateDate;
    protected int $storeId;

    /**
     * Create a new job instance.
     */
    public function __construct(string $tenantId, string $aggregateDate, int $storeId)
    {
        $this->tenantId = $tenantId;
        $this->aggregateDate = $aggregateDate;
        $this->storeId = $storeId;

        // Queue on sync-low (not time-sensitive)
        $this->onQueue('sync-low');
    }

    /**
     * Execute the job.
     */
    public function handle(SalesDailyAggregateService $service): void
    {
        // Initialize tenancy
        tenancy()->initialize($this->tenantId);

        try {
            $date = Carbon::parse($this->aggregateDate);

            $aggregates = $service->recalculateForDate($date, $this->storeId);

            Log::info('Daily aggregates recalculated successfully', [
                'tenant_id' => $this->tenantId,
                'aggregate_date' => $this->aggregateDate,
                'store_id' => $this->storeId,
                'records_created' => $aggregates->count(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to recalculate daily aggregates', [
                'tenant_id' => $this->tenantId,
                'aggregate_date' => $this->aggregateDate,
                'store_id' => $this->storeId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        } finally {
            tenancy()->end();
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Recalculation job failed permanently', [
            'tenant_id' => $this->tenantId,
            'aggregate_date' => $this->aggregateDate,
            'store_id' => $this->storeId,
            'error' => $exception->getMessage(),
        ]);
    }
}
