<?php

namespace App\Console\Commands\Tenant;

use App\Jobs\Tenant\CheckBatchExpiriesJob;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckBatchExpiriesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'inventory:check-batch-expiries 
                            {--tenant= : Specific tenant ID to check (optional)}
                            {--sync : Run synchronously instead of queuing}
                            {--queue= : Queue name to dispatch to (default: sync-normal)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check product batch expiries and generate alerts for expiring or expired batches';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $specificTenantId = $this->option('tenant');
        $runSync = $this->option('sync');
        $queueName = $this->option('queue') ?? 'sync-normal';

        try {
            if ($specificTenantId) {
                // Run for specific tenant
                $this->info("Checking batch expiries for tenant: {$specificTenantId}");
                $this->processForTenant($specificTenantId, $runSync, $queueName);
            } else {
                // Run for all tenants
                $this->info('Checking batch expiries for all tenants...');
                $this->processForAllTenants($runSync, $queueName);
            }

            $this->info('✓ Batch expiry check completed successfully');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('✗ Failed to check batch expiries: ' . $e->getMessage());
            Log::error('CheckBatchExpiriesCommand failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return Command::FAILURE;
        }
    }

    /**
     * Process batch expiries for a specific tenant
     */
    protected function processForTenant(string $tenantId, bool $runSync, string $queueName): void
    {
        $tenant = Tenant::find($tenantId);

        if (!$tenant) {
            throw new \RuntimeException("Tenant not found: {$tenantId}");
        }

        $tenant->run(function () use ($runSync, $queueName, $tenantId) {
            if ($runSync) {
                // Run synchronously
                $this->line("  → Running synchronously for tenant {$tenantId}");

                $job = new CheckBatchExpiriesJob();
                $job->handle(app(\App\Services\Tenant\Inventory\ExpiryAlertService::class));

                $this->info("  ✓ Completed for tenant {$tenantId}");
            } else {
                // Dispatch to queue
                $this->line("  → Queuing job for tenant {$tenantId} on queue: {$queueName}");

                CheckBatchExpiriesJob::dispatch()
                    ->onQueue($queueName);

                $this->info("  ✓ Job queued for tenant {$tenantId}");
            }
        });
    }

    /**
     * Process batch expiries for all tenants
     */
    protected function processForAllTenants(bool $runSync, string $queueName): void
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

        foreach ($tenants as $tenant) {
            try {
                $tenant->run(function () use ($runSync, $queueName, $tenant) {
                    if ($runSync) {
                        $job = new CheckBatchExpiriesJob();
                        $job->handle(app(\App\Services\Tenant\Inventory\ExpiryAlertService::class));
                    } else {
                        CheckBatchExpiriesJob::dispatch()
                            ->onQueue($queueName);
                    }
                });

                $processed++;
            } catch (\Exception $e) {
                $failed++;
                Log::error('Failed to process tenant for batch expiries', [
                    'tenant_id' => $tenant->id,
                    'error' => $e->getMessage(),
                ]);
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        // Summary
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Tenants', $totalTenants],
                ['Successfully Processed', $processed],
                ['Failed', $failed],
                ['Execution Mode', $runSync ? 'Synchronous' : 'Queued'],
                ['Queue', $runSync ? 'N/A' : $queueName],
            ]
        );
    }
}
