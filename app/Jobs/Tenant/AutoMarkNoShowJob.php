<?php

namespace App\Jobs\Tenant;

use App\Services\Tenant\Shift\ShiftAssignmentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AutoMarkNoShowJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 300;
    public $backoff = [60, 120, 300];

    protected string $tenantId;
    protected int $gracePeriodMinutes;

    /**
     * Create a new job instance.
     */
    public function __construct(string $tenantId, ?int $gracePeriodMinutes = null)
    {
        $this->tenantId = $tenantId;
        $this->gracePeriodMinutes = $gracePeriodMinutes ?? config('shift.no_show_grace_period_minutes', 30);

        // Set queue for this job
        $this->onQueue('sync-low');
    }

    /**
     * Execute the job.
     */
    public function handle(ShiftAssignmentService $assignmentService): void
    {
        // Initialize tenancy
        $tenant = \App\Models\Tenant::find($this->tenantId);

        if (!$tenant) {
            Log::error('Tenant not found for AutoMarkNoShowJob', [
                'tenant_id' => $this->tenantId,
            ]);
            return;
        }

        tenancy()->initialize($tenant);

        try {
            $count = $assignmentService->autoMarkNoShow($this->gracePeriodMinutes);

            if ($count > 0) {
                Log::info('Auto-marked no-show shifts', [
                    'tenant_id' => $this->tenantId,
                    'count' => $count,
                    'grace_period_minutes' => $this->gracePeriodMinutes,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to auto-mark no-show shifts', [
                'tenant_id' => $this->tenantId,
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
        Log::error('AutoMarkNoShowJob failed permanently', [
            'tenant_id' => $this->tenantId,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
