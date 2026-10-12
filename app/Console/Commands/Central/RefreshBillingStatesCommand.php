<?php

namespace App\Console\Commands\Central;

use App\Services\Central\Billing\BillingStateService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Moves shops between trial / active / grace / limited as time passes and sends notices.
 * Hourly, and right after the nightly billing run.
 */
class RefreshBillingStatesCommand extends Command
{
    protected $signature = 'billing:refresh-states {--as-of= : Evaluate as of this Nairobi date-time (local testing)}';

    protected $description = 'Re-evaluate every shop\'s billing state (grace, limited, low balance)';

    public function handle(BillingStateService $states): int
    {
        $now = $this->option('as-of') ? CarbonImmutable::parse($this->option('as-of'), 'Africa/Nairobi') : null;

        $this->info('Evaluated '.$states->evaluateAll($now).' billing account(s).');

        return self::SUCCESS;
    }
}
