<?php

namespace App\Console\Commands\Central;

use App\Enums\Central\BillingStatus;
use App\Models\BillingAccount;
use App\Services\Central\Billing\BillingNotices;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

class BillingTrialRemindersCommand extends Command
{
    protected $signature = 'billing:trial-reminders {--as-of= : Pretend it is this moment (for checks)}';

    protected $description = 'Email shops whose free trial ends in 7, 3 or 1 days (each reminder once)';

    public function handle(BillingNotices $notices): int
    {
        $now = $this->option('as-of') ? CarbonImmutable::parse((string) $this->option('as-of'), 'Africa/Nairobi')->utc() : CarbonImmutable::now();
        $sent = 0;

        BillingAccount::query()
            ->where('status', BillingStatus::Trial)
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '>', $now)
            ->where('trial_ends_at', '<=', $now->addDays(max(BillingNotices::TRIAL_REMINDER_DAYS) + 1))
            ->orderBy('id')
            ->each(function (BillingAccount $account) use ($notices, $now, &$sent) {
                try {
                    if ($notices->trialEnding($account, $now) !== null) {
                        $sent++;
                    }
                } catch (Throwable $e) {
                    report($e);
                }
            });

        $this->info("Trial reminders sent: {$sent}");

        return self::SUCCESS;
    }
}
