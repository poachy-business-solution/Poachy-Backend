<?php

namespace App\Console\Commands\Central;

use App\Models\BillingAccount;
use App\Models\Tenant;
use App\Services\Central\Billing\BillingAccountService;
use Illuminate\Console\Command;

/**
 * One-off: gives every existing shop that has no billing account an active account (no trial).
 * Safe to run again — shops that already have an account are skipped.
 */
class OpenBillingAccountsCommand extends Command
{
    protected $signature = 'poachy:billing-open-accounts {--dry-run : List what would be created}';

    protected $description = 'Create active (no-trial) billing accounts for existing shops that have none';

    public function handle(BillingAccountService $accounts): int
    {
        $existing = BillingAccount::query()->pluck('tenant_id')->all();
        $missing = Tenant::query()->whereNotIn('id', $existing)->pluck('id');

        foreach ($missing as $tenantId) {
            if (! $this->option('dry-run')) {
                $accounts->openActive((string) $tenantId);
            }
            $this->line(($this->option('dry-run') ? 'Would open ' : 'Opened ').$tenantId);
        }

        $this->info(sprintf('%d account(s) %s; %d already had one.', $missing->count(), $this->option('dry-run') ? 'to open' : 'opened', count($existing)));

        return self::SUCCESS;
    }
}
