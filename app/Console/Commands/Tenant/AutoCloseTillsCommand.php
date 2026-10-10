<?php

namespace App\Console\Commands\Tenant;

use App\Models\Tenant;
use App\Services\Tenant\Shift\TillService;
use Illuminate\Console\Command;

class AutoCloseTillsCommand extends Command
{
    protected $signature = 'tills:auto-close';

    protected $description = 'Close till sessions left open from an earlier shop-local day, as of local midnight';

    public function handle(): int
    {
        $closed = 0;

        foreach (Tenant::all() as $tenant) {
            $tenant->run(function () use (&$closed): void {
                $closed += app(TillService::class)->autoCloseStaleSessions();
            });
        }

        $this->info("Closed {$closed} till sessions left open overnight");

        return Command::SUCCESS;
    }
}
