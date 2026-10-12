<?php

namespace App\Observers\Central;

use App\Models\Tenant;
use App\Services\Central\Billing\BillingAccountService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TenantObserver
{
    /**
     * Assign a unique M-Pesa Paybill account number before the tenant record is inserted.
     *
     * Fires during `Tenant::create()` BEFORE the INSERT, so the account number is
     * written as part of the initial row — no subsequent UPDATE needed, and no
     * sensitivity to Stancl's post-creation connection-switching.
     */
    public function creating(Tenant $tenant): void
    {
        if (! empty($tenant->mpesa_paybill_account)) {
            return;
        }

        try {
            DB::connection('central')
                ->table('central_counters')
                ->where('name', 'mpesa_account')
                ->increment('value');

            $counter = DB::connection('central')
                ->table('central_counters')
                ->where('name', 'mpesa_account')
                ->value('value');

            $tenant->mpesa_paybill_account = config('mpesa.account_prefix', 'POA')
                .str_pad((string) $counter, 5, '0', STR_PAD_LEFT);
        } catch (\Throwable $e) {
            Log::error('Failed to assign Paybill account number to tenant', [
                'tenant_id' => $tenant->id ?? 'unknown',
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Every new shop starts on a free trial of trading-day billing.
     * Never blocks tenant creation: a failure is logged and the account can be opened later
     * with `php artisan poachy:billing-open-accounts`.
     */
    public function created(Tenant $tenant): void
    {
        try {
            app(BillingAccountService::class)->openTrial((string) $tenant->getKey());
        } catch (\Throwable $e) {
            Log::error('Failed to open trial billing account for tenant', [
                'tenant_id' => $tenant->getKey(),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
