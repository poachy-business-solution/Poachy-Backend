<?php

namespace App\Jobs\Tenant;

use App\Models\Tenant;
use App\Services\Tenant\Shift\ShiftNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendShiftRemindersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public array $backoff = [60, 120, 300];

    public function __construct(
        protected string $tenantId,
        protected ?int $hoursBefore = null,
    ) {
        $this->onQueue('sync-low');
    }

    public function handle(ShiftNotificationService $notifications): void
    {
        $tenant = Tenant::find($this->tenantId);

        if (! $tenant) {
            Log::error('Tenant not found for SendShiftRemindersJob', [
                'tenant_id' => $this->tenantId,
            ]);

            return;
        }

        tenancy()->initialize($tenant);

        try {
            $sent = $notifications->sendUpcomingShiftReminders(
                $this->hoursBefore ?? config('shift.reminder_hours_before', 2)
            );

            if ($sent > 0) {
                Log::info('Shift reminder notifications dispatched', [
                    'tenant_id' => $this->tenantId,
                    'sent' => $sent,
                ]);
            }
        } finally {
            tenancy()->end();
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SendShiftRemindersJob failed permanently', [
            'tenant_id' => $this->tenantId,
            'error' => $exception->getMessage(),
        ]);
    }
}
