<?php

namespace App\Console\Commands\Central;

use App\Services\Central\Payouts\PayoutService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * The nightly marketplace payout batch (02:00 Nairobi): one B2C transfer per shop for what was
 * handed over at least 24 h ago, plus re-sends of failed payouts that are due.
 */
class RunMarketplacePayouts extends Command
{
    protected $signature = 'marketplace:payouts {--as-of= : Run as if it were this moment (ISO date/time), for checks}
        {--retries-only : Only re-send failed payouts that are due}';

    protected $description = 'Pay shops their marketplace earnings by M-Pesa B2C (nightly batch + due retries)';

    public function handle(PayoutService $payouts): int
    {
        $asOf = $this->option('as-of') ? Carbon::parse((string) $this->option('as-of')) : now();
        $summary = $payouts->runBatch($asOf, (bool) $this->option('retries-only'));

        $this->info("Payouts created: {$summary['created']}, sent: {$summary['sent']}, retried: {$summary['retried']}");
        foreach ($summary['skipped'] as $tenant => $why) {
            $this->line("  skipped {$tenant}: {$why}");
        }

        return self::SUCCESS;
    }
}
