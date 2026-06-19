<?php

namespace App\Jobs\Tenant;

use App\Models\Tenant\Sale;
use App\Services\Tenant\Sales\SalesDailyAggregateService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class UpdateDailyAggregatesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 120;
    public $backoff = [30, 60, 120];

    protected string $tenantId;
    protected int $saleId;

    /**
     * Create a new job instance.
     */
    public function __construct(string $tenantId, int $saleId)
    {
        $this->tenantId = $tenantId;
        $this->saleId = $saleId;

        // Queue on sync-normal priority
        $this->onQueue('sync-normal');
    }

    /**
     * Execute the job.
     */
    public function handle(SalesDailyAggregateService $service): void
    {
        // Initialize tenancy
        tenancy()->initialize($this->tenantId);

        try {
            // Load sale with necessary relationships
            $sale = Sale::with([
                'items.product.category',
                'items.productVariant',
                'items.bundle',
            ])->findOrFail($this->saleId);

            // Update aggregates
            $service->updateFromSale($sale);

            Log::info('Daily aggregates job completed', [
                'tenant_id' => $this->tenantId,
                'sale_id' => $this->saleId,
                'sale_number' => $sale->sale_number,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update daily aggregates', [
                'tenant_id' => $this->tenantId,
                'sale_id' => $this->saleId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e; // Re-throw for retry mechanism
        } finally {
            // End tenancy context
            tenancy()->end();
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Daily aggregates job failed permanently', [
            'tenant_id' => $this->tenantId,
            'sale_id' => $this->saleId,
            'error' => $exception->getMessage(),
        ]);
    }
}
