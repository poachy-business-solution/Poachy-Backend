<?php

namespace App\Jobs\Tenant;

use App\Services\Tenant\Sales\SalesDailyAggregateService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class UpdateUniqueCustomerCountJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 60;
    public $backoff = [15, 30, 60];

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

        // Queue on sync-low priority
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
            $service->updateUniqueCustomerCount($this->aggregateDate, $this->storeId);

            Log::info('Unique customer count updated', [
                'tenant_id' => $this->tenantId,
                'aggregate_date' => $this->aggregateDate,
                'store_id' => $this->storeId,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update unique customer count', [
                'tenant_id' => $this->tenantId,
                'aggregate_date' => $this->aggregateDate,
                'store_id' => $this->storeId,
                'error' => $e->getMessage(),
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
        Log::error('Unique customer count job failed permanently', [
            'tenant_id' => $this->tenantId,
            'aggregate_date' => $this->aggregateDate,
            'store_id' => $this->storeId,
            'error' => $exception->getMessage(),
        ]);
    }
}
