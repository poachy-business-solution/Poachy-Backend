<?php

namespace App\Console\Commands\Central;

use App\Services\Central\Marketplace\AdvertService;
use Illuminate\Console\Command;

class EndFinishedAdverts extends Command
{
    protected $signature = 'adverts:end-finished';

    protected $description = 'Mark adverts whose paid days are over as ended';

    public function handle(AdvertService $adverts): int
    {
        $this->info('Adverts ended: '.$adverts->endFinished());

        return self::SUCCESS;
    }
}
