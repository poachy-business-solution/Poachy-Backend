<?php

namespace App\Console\Commands\Tenant;

use App\Models\Tenant;
use App\Models\Tenant\AuditLog;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PruneAuditLogsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'audit:prune-logs
                            {--days=730 : Days to retain audit logs before pruning}
                            {--tenant= : Specific tenant ID to prune (optional)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete audit log entries older than the retention window, across all tenants';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) $this->option('days');
        $cutoff = now()->subDays($days);
        $specificTenantId = $this->option('tenant');

        try {
            if ($specificTenantId) {
                $tenant = Tenant::find($specificTenantId);

                if (! $tenant) {
                    throw new \RuntimeException("Tenant not found: {$specificTenantId}");
                }

                $this->info("Pruning audit logs older than {$days} days for tenant: {$specificTenantId}");
                $deleted = $this->pruneForTenant($tenant, $cutoff);
                $this->info("✓ Deleted {$deleted} audit log(s)");
            } else {
                $this->info("Pruning audit logs older than {$days} days for all tenants...");
                $this->pruneForAllTenants($cutoff);
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('✗ Failed to prune audit logs: '.$e->getMessage());
            Log::error('PruneAuditLogsCommand failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return Command::FAILURE;
        }
    }

    /**
     * Prune audit logs for a single tenant and return the number of rows deleted.
     */
    protected function pruneForTenant(Tenant $tenant, Carbon $cutoff): int
    {
        return $tenant->run(function () use ($cutoff) {
            return AuditLog::where('created_at', '<', $cutoff)->delete();
        });
    }

    /**
     * Prune audit logs across every tenant, reporting progress and a summary table.
     */
    protected function pruneForAllTenants(Carbon $cutoff): void
    {
        $tenants = Tenant::all();
        $totalTenants = $tenants->count();

        if ($totalTenants === 0) {
            $this->warn('No tenants found');

            return;
        }

        $this->line("Found {$totalTenants} tenant(s) to process");
        $this->newLine();

        $progressBar = $this->output->createProgressBar($totalTenants);
        $progressBar->start();

        $processed = 0;
        $failed = 0;
        $totalDeleted = 0;

        foreach ($tenants as $tenant) {
            try {
                $totalDeleted += $this->pruneForTenant($tenant, $cutoff);
                $processed++;
            } catch (\Exception $e) {
                $failed++;
                Log::error('Failed to prune audit logs for tenant', [
                    'tenant_id' => $tenant->id,
                    'error' => $e->getMessage(),
                ]);
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Tenants', $totalTenants],
                ['Successfully Processed', $processed],
                ['Failed', $failed],
                ['Audit Logs Deleted', $totalDeleted],
            ]
        );

        Log::info('Audit log pruning finished', [
            'tenants_processed' => $processed,
            'tenants_failed' => $failed,
            'total_deleted' => $totalDeleted,
        ]);
    }
}
