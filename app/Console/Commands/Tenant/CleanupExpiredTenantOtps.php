<?php

namespace App\Console\Commands\Tenant;

use App\Models\Tenant;
use App\Models\Tenant\TenantOtp;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CleanupExpiredTenantOtps extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'otp:cleanup-tenant
                            {--tenant= : Specific tenant ID to clean up (optional)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete expired and used tenant OTP codes, across all tenants';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $specificTenantId = $this->option('tenant');

        try {
            if ($specificTenantId) {
                $tenant = Tenant::find($specificTenantId);

                if (! $tenant) {
                    throw new \RuntimeException("Tenant not found: {$specificTenantId}");
                }

                $this->info("Cleaning up expired OTPs for tenant: {$specificTenantId}");
                $deleted = $this->cleanupForTenant($tenant);
                $this->info("✓ Deleted {$deleted} OTP code(s)");
            } else {
                $this->info('Cleaning up expired OTPs for all tenants...');
                $this->cleanupForAllTenants();
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('✗ Failed to clean up tenant OTPs: '.$e->getMessage());
            Log::error('CleanupExpiredTenantOtps failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return Command::FAILURE;
        }
    }

    /**
     * Clean up expired/used OTPs for a single tenant and return the number of rows deleted.
     */
    protected function cleanupForTenant(Tenant $tenant): int
    {
        return $tenant->run(function () {
            return TenantOtp::where(function ($query) {
                $query->where('is_used', true)
                    ->orWhere('expires_at', '<', now());
            })->delete();
        });
    }

    /**
     * Clean up expired/used OTPs across every tenant, reporting progress and a summary table.
     */
    protected function cleanupForAllTenants(): void
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
                $totalDeleted += $this->cleanupForTenant($tenant);
                $processed++;
            } catch (\Exception $e) {
                $failed++;
                Log::error('Failed to clean up OTPs for tenant', [
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
                ['OTP Codes Deleted', $totalDeleted],
            ]
        );

        Log::info('Tenant OTP cleanup finished', [
            'tenants_processed' => $processed,
            'tenants_failed' => $failed,
            'total_deleted' => $totalDeleted,
        ]);
    }
}
