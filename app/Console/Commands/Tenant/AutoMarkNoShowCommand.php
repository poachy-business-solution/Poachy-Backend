<?php

namespace App\Console\Commands\Tenant;

use App\Jobs\Tenant\AutoMarkNoShowJob;
use App\Models\Tenant;
use Illuminate\Console\Command;

class AutoMarkNoShowCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'shifts:auto-mark-noshow';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Auto-mark overdue shifts as no-show for all tenants';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting auto no-show detection for all tenants...');

        $tenants = Tenant::all();
        $dispatchedCount = 0;

        foreach ($tenants as $tenant) {
            try {
                AutoMarkNoShowJob::dispatch($tenant->id);
                $dispatchedCount++;

                $this->line("Dispatched job for tenant: {$tenant->id}");
            } catch (\Exception $e) {
                $this->error("Failed to dispatch job for tenant {$tenant->id}: {$e->getMessage()}");
            }
        }

        $this->info("Auto no-show detection jobs dispatched for {$dispatchedCount} tenants");

        return Command::SUCCESS;
    }
}
