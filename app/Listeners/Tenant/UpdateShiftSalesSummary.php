<?php

namespace App\Listeners\Tenant;

use App\Events\Tenant\SaleCompleted;
use App\Services\Tenant\Sales\ShiftSalesSummaryService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class UpdateShiftSalesSummary implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'sync-high';
    public int $tries = 3;
    public int $backoff = 5;

    public function __construct(
        protected ShiftSalesSummaryService $summaryService
    ) {}

    /**
     * Handle the event.
     */
    public function handle(SaleCompleted $event): void
    {
        $sale = $event->sale;

        // Only update if sale is linked to a shift
        if (!$sale->shift_assignment_id) {
            Log::info('Sale not linked to shift, skipping summary update', [
                'sale_id' => $sale->id,
                'sale_number' => $sale->sale_number,
            ]);
            return;
        }

        try {
            $this->summaryService->updateFromSale($sale);

            Log::info('Shift sales summary updated', [
                'sale_id' => $sale->id,
                'sale_number' => $sale->sale_number,
                'shift_assignment_id' => $sale->shift_assignment_id,
                'total_amount' => $sale->total_amount,
                // 'payment_method' => $sale->payment_method->value,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update shift sales summary', [
                'sale_id' => $sale->id,
                'shift_assignment_id' => $sale->shift_assignment_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e; // Re-throw to trigger retry
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(SaleCompleted $event, \Throwable $exception): void
    {
        Log::error('Shift sales summary update failed permanently', [
            'sale_id' => $event->sale->id,
            'shift_assignment_id' => $event->sale->shift_assignment_id,
            'error' => $exception->getMessage(),
        ]);

        // Could notify admins here
    }
}
