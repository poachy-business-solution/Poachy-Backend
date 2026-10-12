<?php

namespace App\Console\Commands\Central;

use App\Services\Central\Billing\NightlyBillingService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Charges trading days (see NightlyBillingService). Scheduled at 01:00 Africa/Nairobi; safe to
 * re-run. `--as-of` runs it as if it were that night (local testing / catching up).
 */
class RunNightlyBillingCommand extends Command
{
    protected $signature = 'billing:run {--as-of= : Run as of this Nairobi date-time, e.g. "2026-10-13 01:00"}';

    protected $description = 'Charge every shop for the branch trading days not billed yet';

    public function handle(NightlyBillingService $billing): int
    {
        $now = $this->option('as-of') ? CarbonImmutable::parse($this->option('as-of'), 'Africa/Nairobi') : null;
        $totals = $billing->run($now);

        $this->info(sprintf('Billed %d branch-day(s) across %d shop(s), KSh %s.', $totals['days_billed'], $totals['tenants'], number_format($totals['amount'], 2)));

        return self::SUCCESS;
    }
}
