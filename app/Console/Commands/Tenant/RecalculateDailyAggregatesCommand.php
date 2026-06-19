<?php

namespace App\Console\Commands\Tenant;

use App\Jobs\Tenant\RecalculateDailyAggregatesJob;
use App\Models\Tenant\Store;
use Carbon\Carbon;
use Illuminate\Console\Command;

class RecalculateDailyAggregatesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'aggregates:recalculate 
                            {--date= : The date to recalculate (YYYY-MM-DD)}
                            {--store= : The store ID (optional, processes all stores if not provided)}
                            {--tenant= : The tenant ID (required)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalculate daily sales aggregates for a specific date and store';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dateInput = $this->option('date');
        $storeId = $this->option('store');
        $tenantId = $this->option('tenant');

        // Validate tenant
        if (!$tenantId) {
            $this->error('Tenant ID is required. Use --tenant=<tenant_id>');
            return self::FAILURE;
        }

        // Validate date
        if (!$dateInput) {
            $this->error('Date is required. Use --date=YYYY-MM-DD');
            return self::FAILURE;
        }

        try {
            $date = Carbon::parse($dateInput)->toDateString();
        } catch (\Exception $e) {
            $this->error('Invalid date format. Use YYYY-MM-DD');
            return self::FAILURE;
        }

        // Initialize tenancy
        try {
            tenancy()->initialize($tenantId);
        } catch (\Exception $e) {
            $this->error("Failed to initialize tenant: {$e->getMessage()}");
            return self::FAILURE;
        }

        // Get stores to process
        $stores = $storeId
            ? Store::where('id', $storeId)->get()
            : Store::where('is_active', true)->get();

        if ($stores->isEmpty()) {
            $this->error('No stores found');
            tenancy()->end();
            return self::FAILURE;
        }

        $this->info("Recalculating aggregates for {$date}...");
        $this->info("Tenant: {$tenantId}");
        $this->info("Stores: {$stores->count()}");

        $progressBar = $this->output->createProgressBar($stores->count());
        $progressBar->start();

        foreach ($stores as $store) {
            RecalculateDailyAggregatesJob::dispatch($tenantId, $date, $store->id);
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        $this->info('✓ Recalculation jobs queued successfully');
        $this->info('  Run "php artisan horizon:work" to process the queue');

        tenancy()->end();

        return self::SUCCESS;
    }
}
