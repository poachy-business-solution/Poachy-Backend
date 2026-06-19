<?php

namespace App\Console\Commands\Tenant;

use App\Services\Tenant\Inventory\StockReservationService;
use Illuminate\Console\Command;

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
    public function handle(StockReservationService $reservationService): int
    {
        $this->info('Starting stale reservation expiry process...');

        try {
            $expiredCount = $reservationService->expireStaleReservations();

            if ($expiredCount > 0) {
                $this->info("Successfully expired {$expiredCount} stale reservation(s).");
            } else {
                $this->info('No stale reservations found.');
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Error expiring reservations: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
