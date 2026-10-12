<?php

namespace App\Console\Commands\Tenant;

use App\Models\Tenant;
use App\Services\Tenant\Billing\SaleDaysReporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Each shop reports which branches sold on which days (last 7 days + today) to central.
 * Runs hourly; the 00:40 run lands before the 01:00 billing job.
 */
class ReportSaleDaysCommand extends Command
{
    protected $signature = 'billing:report-sale-days {--tenant= : Only this tenant id}';

    protected $description = 'Report branch trading days from every shop to central (for trading-day billing)';

    public function handle(SaleDaysReporter $reporter): int
    {
        $tenants = Tenant::query()->when($this->option('tenant'), fn ($q, $id) => $q->whereKey($id))->get();
        $failed = 0;

        foreach ($tenants as $tenant) {
            try {
                $accepted = $tenant->run(fn () => $reporter->report((string) $tenant->getKey()));
                $this->line("{$tenant->getKey()}: {$accepted} branch-day(s) reported");
            } catch (Throwable $e) {
                $failed++;
                Log::error('Sale-days report failed', ['tenant_id' => $tenant->getKey(), 'error' => $e->getMessage()]);
                $this->error("{$tenant->getKey()}: {$e->getMessage()}");
            }
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
