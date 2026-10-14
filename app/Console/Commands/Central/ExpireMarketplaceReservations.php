<?php

namespace App\Console\Commands\Central;

use App\Services\Central\Marketplace\ReservationService;
use Illuminate\Console\Command;

/** Reserve & collect: close holds past their 24 h and tell the shops to release the stock. */
class ExpireMarketplaceReservations extends Command
{
    protected $signature = 'marketplace:expire-reservations';

    protected $description = 'Expire reserve & collect holds past their time and release the shops\' stock';

    public function handle(ReservationService $reservations): int
    {
        $count = $reservations->expireDue();
        $this->info("Expired reservations: {$count}");

        return self::SUCCESS;
    }
}
