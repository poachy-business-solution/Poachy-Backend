<?php

namespace App\Services\Central\Billing;

use App\Enums\Central\BillingStatus;
use App\Models\BillingAccount;
use App\Models\BillingPrice;
use Illuminate\Support\Carbon;

/**
 * Opens billing accounts: a free trial for new shops, an active (no-trial) account for shops
 * that existed before trading-day billing.
 */
class BillingAccountService
{
    /** Open a trial account for a new shop (no-op if it already has one). */
    public function openTrial(string $tenantId, ?Carbon $now = null): BillingAccount
    {
        $now ??= now();
        $trialDays = BillingPrice::forDate($now->copy()->setTimezone('Africa/Nairobi'))?->trial_days ?? 30;

        return BillingAccount::query()->firstOrCreate(
            ['tenant_id' => $tenantId],
            ['balance' => 0, 'status' => BillingStatus::Trial, 'trial_ends_at' => $now->copy()->addDays($trialDays)],
        );
    }

    /** Open an active account with no trial (shops that were on Poachy before billing started). */
    public function openActive(string $tenantId): BillingAccount
    {
        return BillingAccount::query()->firstOrCreate(
            ['tenant_id' => $tenantId],
            ['balance' => 0, 'status' => BillingStatus::Active, 'trial_ends_at' => null],
        );
    }
}
