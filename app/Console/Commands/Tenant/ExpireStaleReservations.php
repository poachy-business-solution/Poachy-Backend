<?php

namespace App\Console\Commands\Tenant;

use App\Models\Tenant;
use App\Services\Tenant\Inventory\StockReservationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ExpireStaleReservations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'inventory:expire-reservations
                          {--tenant= : Specific tenant ID to process}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Expire stale stock reservations that have passed their reserved_until timestamp';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $specificTenantId = $this->option('tenant');

        if ($specificTenantId) {
            $tenant = Tenant::find($specificTenantId);

            if (! $tenant) {
                $this->error("Tenant not found: {$specificTenantId}");

                return Command::FAILURE;
            }

            $this->info("Expiring stale reservations for tenant: {$specificTenantId}");
            $this->expireForTenant($tenant);

            return Command::SUCCESS;
        }

        $tenants = Tenant::all();

        if ($tenants->isEmpty()) {
            $this->warn('No tenants found');

            return Command::SUCCESS;
        }

        $this->info("Expiring stale reservations across {$tenants->count()} tenant(s)...");

        $totalExpired = 0;
        $failed = 0;

        foreach ($tenants as $tenant) {
            try {
                $totalExpired += $this->expireForTenant($tenant);
            } catch (\Exception $e) {
                $failed++;
                Log::error('Failed to expire stale reservations for tenant', [
                    'tenant_id' => $tenant->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Done. Expired {$totalExpired} reservation(s) across {$tenants->count()} tenant(s), {$failed} tenant(s) failed.");

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * Expire stale reservations for a single tenant, within that tenant's own
     * database context, and report the count back to the caller.
     */
    protected function expireForTenant(Tenant $tenant): int
    {
        $expiredCount = $tenant->run(
            fn () => app(StockReservationService::class)->expireStaleReservations()
        );

        if ($expiredCount > 0) {
            $this->line("  → Expired {$expiredCount} reservation(s) for tenant {$tenant->id}");
        }

        return $expiredCount;
    }
}
