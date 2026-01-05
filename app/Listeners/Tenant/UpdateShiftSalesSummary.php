<?php

namespace App\Listeners\Tenant;

use App\Events\Tenant\ShiftEnded;
use App\Jobs\Tenant\CalculateShiftSalesSummaryJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class UpdateShiftSalesSummary implements ShouldQueue
{
    use InteractsWithQueue;

    public $queue = 'sync-normal';
    public $tries = 3;
    public $backoff = [30, 60, 120];

    /**
     * Handle the event.
     */
    public function handle(ShiftEnded $event): void
    {
        try {
            // Dispatch job to calculate sales summary
            CalculateShiftSalesSummaryJob::dispatch(
                tenant()->id,
                $event->assignment->id
            );

            Log::info('Dispatched sales summary calculation job', [
                'assignment_id' => $event->assignment->id,
                'tenant_id' => tenant()->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to dispatch sales summary calculation job', [
                'assignment_id' => $event->assignment->id,
                'error' => $e->getMessage(),
                'tenant_id' => tenant()->id,
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(ShiftEnded $event, \Throwable $exception): void
    {
        Log::error('UpdateShiftSalesSummary listener failed permanently', [
            'assignment_id' => $event->assignment->id,
            'error' => $exception->getMessage(),
            'tenant_id' => tenant()->id,
        ]);
    }
}
