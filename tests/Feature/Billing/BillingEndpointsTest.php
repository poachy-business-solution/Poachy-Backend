<?php

namespace Tests\Feature\Billing;

use App\Models\BillingAccount;
use App\Models\BillingLedgerEntry;
use App\Models\BusinessSubscription;
use App\Models\Tenant;
use App\Models\Tenant\Store;
use App\Models\Tenant\User;
use Database\Seeders\BillingPriceSeeder;
use Illuminate\Support\Facades\Cache;
use Tests\Feature\Tenant\Concerns\InteractsWithTenantHttpAuth;
use Tests\TestCase;

/**
 * GET /tenant/billing, GET /tenant/billing/ledger and limited mode, through the real tenant
 * host, Sanctum, roles and middleware. The shop has no plan subscription — only billing.
 */
class BillingEndpointsTest extends TestCase
{
    use InteractsWithTenantHttpAuth;

    public function test_billing_status_ledger_and_limited_mode(): void
    {
        $this->configureTenantHttpDatabases();
        $this->seed(BillingPriceSeeder::class);
        $domain = 'billing-state-'.strtolower(uniqid()).'.poachy.test';
        $tenant = $this->createTenantHttpFixture($domain);
        $url = fn (string $path) => $this->tenantUrl($domain, $path);

        // Trading-day billing replaces the plan: no subscription, just an account (opened by the
        // tenant observer as a trial) that we move to limited.
        BusinessSubscription::on('central')->where('tenant_id', $tenant->id)->delete();
        $account = BillingAccount::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $account->forceFill(['status' => 'limited', 'balance' => -150, 'trial_ends_at' => now()->subDays(3), 'grace_until' => now()->subDay(), 'limited_since' => now()->subHour()])->save();
        BillingLedgerEntry::factory()->create(['billing_account_id' => $account->id, 'tenant_id' => $tenant->id, 'type' => 'charge', 'amount' => -150, 'balance_after' => -150, 'description' => 'Trading day', 'source' => 'system']);
        Cache::forget("tenant_access:{$tenant->id}");

        try {
            $this->getJson($url('/api/v1/tenant/billing'))->assertUnauthorized();

            $tenant->run(function () use ($url, $tenant) {
                $owner = $this->createTenantUserWithRole('owner');
                $cashier = $this->createTenantUserWithRole('cashier');
                Store::create(['name' => 'Main', 'address' => 'x', 'city' => 'Nairobi', 'is_active' => true, 'is_main_store' => true]);

                $this->actingAsTenant($owner);
                $this->getJson($url('/api/v1/tenant/billing'))->assertOk()
                    ->assertJsonPath('data.status', 'limited')
                    ->assertJsonPath('data.balance', -150)
                    ->assertJsonPath('data.account_number', Tenant::query()->find($tenant->id)->mpesa_paybill_account)
                    ->assertJsonPath('data.days_covered', 0)
                    ->assertJsonStructure(['data' => ['month' => ['days_billed', 'amount'], 'prices', 'paybill_number', 'min_topup']]);
                $this->getJson($url('/api/v1/tenant/billing/ledger'))->assertOk()->assertJsonPath('data.data.0.amount', -150)->assertJsonPath('data.pagination.total', 1);

                // Limited mode: reports + admin writes answer 402; selling-side reads still work.
                $this->getJson($url('/api/v1/tenant/reports/owner-summary'))->assertStatus(402)->assertJsonPath('errors.error_code', 'BILLING_LIMITED');
                $this->postJson($url('/api/v1/tenant/stores'), ['name' => 'Second'])->assertStatus(402);
                $this->getJson($url('/api/v1/tenant/stores'))->assertOk();
                $this->getJson($url('/api/v1/tenant/catalog/sync'))->assertOk();

                $this->actingAsTenant($cashier);
                $this->getJson($url('/api/v1/tenant/billing'))->assertOk()->assertJsonPath('data.status', 'limited')->assertJsonMissingPath('data.balance');
                $this->getJson($url('/api/v1/tenant/billing/ledger'))->assertForbidden();
            });

            // Paid up: everything opens again.
            $account->forceFill(['status' => 'active', 'balance' => 500])->save();
            $tenant->run(function () use ($url) {
                $this->actingAsTenant(User::query()->whereHas('roles', fn ($q) => $q->where('name', 'owner'))->firstOrFail());
                // Past the billing gate: the report's own validation answers now, not 402.
                $this->assertNotSame(402, $this->getJson($url('/api/v1/tenant/reports/owner-summary'))->getStatusCode());
            });
        } finally {
            $this->deleteTenantHttpFixture($domain);
        }
    }
}
