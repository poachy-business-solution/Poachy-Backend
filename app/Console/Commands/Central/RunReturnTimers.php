<?php

namespace App\Console\Commands\Central;

use App\Services\Central\Refunds\ReturnCaseService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Return clocks (every 15 min): uninspected 48 h → Poachy; dispute window over → the shop's
 * decision stands; never brought back in 7 days → expired.
 */
class RunReturnTimers extends Command
{
    protected $signature = 'marketplace:return-timers {--as-of= : Run as if it were this moment}';

    protected $description = 'Escalate, settle and expire marketplace returns whose clocks ran out';

    public function handle(ReturnCaseService $returns): int
    {
        $done = $returns->runTimers($this->option('as-of') ? Carbon::parse((string) $this->option('as-of')) : null);
        $this->info("Escalated {$done['escalated']}, settled {$done['settled']}, expired {$done['expired']}");

        return self::SUCCESS;
    }
}
