<?php

namespace Tests\Feature\Billing;

use App\Enums\Central\BillingStatus;
use App\Http\Middleware\EnforceBillingLimits;
use App\Mail\Shared\PlainMessageMail;
use App\Models\BillingAccount;
use App\Models\BillingDay;
use App\Models\BusinessDetail;
use App\Models\MessageLog;
use App\Models\TenantSaleDay;
use App\Services\Central\Billing\BillingStateService;
use App\Services\Central\Billing\BillingTopupService;
use Carbon\CarbonImmutable;
use Database\Seeders\BillingPriceSeeder;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Billing states (trial → active → grace → limited → active), notices, low balance, and the
 * limited-mode route rules.
 */
class BillingStateTest extends TestCase
{
    private string $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('database.default', 'central');
        Config::set('tenancy.database.central_connection', 'central');
        DB::connection('central')->beginTransaction();
        $this->seed(BillingPriceSeeder::class);
        Mail::fake();

        $this->tenant = 'state-'.Str::lower(Str::random(8));
        DB::connection('central')->table('tenants')->insert(['id' => $this->tenant, 'mpesa_paybill_account' => 'POA7'.random_int(1000, 9999), 'created_at' => now(), 'updated_at' => now()]);
        BusinessDetail::query()->create([
            'tenant_id' => $this->tenant, 'business_name' => 'State Shop', 'business_type_id' => DB::connection('central')->table('business_types')->value('id'),
            'business_category_id' => DB::connection('central')->table('business_categories')->value('id'),
            'business_phone' => '0712345678', 'business_email' => 'owner@state-test.example',
        ]);
    }

    protected function tearDown(): void
    {
        DB::connection('central')->rollBack();
        parent::tearDown();
    }

    private function account(array $attributes = []): BillingAccount
    {
        return BillingAccount::factory()->create($attributes + ['tenant_id' => $this->tenant]);
    }

    private function at(string $nairobi): CarbonImmutable
    {
        return CarbonImmutable::parse($nairobi, 'Africa/Nairobi');
    }

    private function evaluate(BillingAccount $account, string $nairobi): BillingAccount
    {
        return app(BillingStateService::class)->evaluate($account->fresh(), $this->at($nairobi))->fresh();
    }

    private function notices(string $template): int
    {
        return MessageLog::query()->where('tenant_id', $this->tenant)->where('template', $template)->count();
    }

    public function test_a_shop_in_trial_stays_in_trial_whatever_the_balance(): void
    {
        $account = $this->account(['status' => 'trial', 'trial_ends_at' => $this->at('2026-11-01 10:00')->utc(), 'balance' => 0]);

        $this->assertSame(BillingStatus::Trial, $this->evaluate($account, '2026-10-12 10:00')->status);
    }

    public function test_an_empty_balance_gets_one_grace_day_then_limited_mode(): void
    {
        $account = $this->account(['status' => 'trial', 'trial_ends_at' => $this->at('2026-10-12 09:00')->utc(), 'balance' => 0]);

        $account = $this->evaluate($account, '2026-10-12 10:00');
        $this->assertSame(BillingStatus::Grace, $account->status);
        $this->assertSame('2026-10-13 10:00', $account->grace_until->setTimezone('Africa/Nairobi')->format('Y-m-d H:i'));
        $this->assertSame(2, $this->notices('billing_grace')); // email + SMS
        Mail::assertQueued(PlainMessageMail::class, fn ($m) => $m->heading === 'Your Poachy balance has run out');

        $this->assertSame(BillingStatus::Grace, $this->evaluate($account, '2026-10-13 09:59')->status);

        $account = $this->evaluate($account, '2026-10-13 10:01');
        $this->assertSame(BillingStatus::Limited, $account->status);
        $this->assertNotNull($account->limited_since);
        $this->assertSame(2, $this->notices('billing_limited'));

        // Staying limited does not re-send the notice.
        $this->evaluate($account, '2026-10-13 12:00');
        $this->assertSame(2, $this->notices('billing_limited'));
        $this->assertSame(2, $this->notices('billing_grace')); // email + SMS
    }

    public function test_a_top_up_restores_full_access_at_once(): void
    {
        $account = $this->account(['status' => 'limited', 'balance' => -300, 'grace_until' => now()->subDay(), 'limited_since' => now()->subHours(2)]);

        app(BillingTopupService::class)->creditFromMpesa($this->tenant, 500, 'FKRESTORE1', 'mpesa_c2b');

        $account->refresh();
        $this->assertSame(BillingStatus::Active, $account->status);
        $this->assertSame('200.00', $account->balance); // the debt is settled first
        $this->assertNull($account->grace_until);
        $this->assertNull($account->limited_since);
        Mail::assertQueued(PlainMessageMail::class, fn ($m) => $m->heading === 'Poachy is fully back on');
    }

    public function test_a_top_up_that_does_not_clear_the_debt_keeps_the_shop_limited(): void
    {
        $account = $this->account(['status' => 'limited', 'balance' => -300, 'grace_until' => now()->subDay(), 'limited_since' => now()->subHours(2)]);

        app(BillingTopupService::class)->creditFromMpesa($this->tenant, 100, 'FKPARTIAL1', 'mpesa_c2b');

        $this->assertSame(BillingStatus::Limited, $account->fresh()->status);
    }

    public function test_low_balance_notice_once_a_day_in_daytime(): void
    {
        $account = $this->account(['status' => 'active', 'balance' => 250]); // 2 days at KSh 100

        $this->evaluate($account, '2026-10-12 03:00'); // night: no SMS
        $this->assertSame(0, $this->notices('billing_low_balance'));

        $this->evaluate($account, '2026-10-12 09:00');
        $this->evaluate($account, '2026-10-12 15:00');
        $this->assertSame(2, $this->notices('billing_low_balance'));
        Mail::assertQueued(PlainMessageMail::class, fn ($m) => $m->heading === 'Top up to keep Poachy running' && str_contains($m->paragraphs[0], '2 more trading days'));

        $this->evaluate($account, '2026-10-13 09:00');
        $this->assertSame(4, $this->notices('billing_low_balance'));
    }

    public function test_enough_balance_is_not_low(): void
    {
        $account = $this->account(['status' => 'active', 'balance' => 300]);

        $this->evaluate($account, '2026-10-12 09:00');

        $this->assertSame(0, $this->notices('billing_low_balance'));
    }

    public function test_daily_cost_counts_the_branches_that_sold_this_week(): void
    {
        foreach ([1, 2, 3] as $store) {
            TenantSaleDay::query()->create(['tenant_id' => $this->tenant, 'store_id' => $store, 'date' => now('Africa/Nairobi')->subDay()->toDateString(), 'sales_count' => 1, 'is_main_branch' => $store === 1, 'reported_at' => now()]);
        }
        $account = $this->account(['status' => 'active', 'balance' => 450]);
        $states = app(BillingStateService::class);

        $this->assertSame(200.0, $states->dailyCost($this->tenant));
        $this->assertSame(2, $states->daysCovered($account));
        $this->assertTrue($states->isLow($account));
    }

    public function test_month_to_date_totals(): void
    {
        $this->travelTo($this->at('2026-10-20 12:00'));
        BillingDay::factory()->create(['tenant_id' => $this->tenant, 'store_id' => 1, 'date' => '2026-10-02', 'amount' => 100]);
        BillingDay::factory()->create(['tenant_id' => $this->tenant, 'store_id' => 2, 'date' => '2026-10-02', 'amount' => 50, 'is_main_branch' => false]);
        BillingDay::factory()->create(['tenant_id' => $this->tenant, 'store_id' => 1, 'date' => '2026-10-03', 'amount' => 0]);
        BillingDay::factory()->create(['tenant_id' => $this->tenant, 'store_id' => 1, 'date' => '2026-09-30', 'amount' => 100]);

        $this->assertSame(['days' => 1, 'amount' => 150.0], app(BillingStateService::class)->monthToDate($this->tenant));
    }

    public function test_the_nightly_run_moves_an_emptied_shop_into_grace(): void
    {
        $account = $this->account(['status' => 'active', 'balance' => 50]);
        TenantSaleDay::query()->create(['tenant_id' => $this->tenant, 'store_id' => 1, 'date' => '2026-10-11', 'sales_count' => 2, 'is_main_branch' => true, 'reported_at' => now()]);
        $this->travelTo($this->at('2026-10-12 01:00'));

        $this->artisan('billing:run', ['--as-of' => '2026-10-12 01:00'])->assertSuccessful();

        $this->assertSame(BillingStatus::Grace, $account->fresh()->status);
        $this->assertSame('-50.00', $account->fresh()->balance);
    }

    public function test_limited_mode_blocks_reports_receiving_transfers_and_admin_only(): void
    {
        $blocked = fn (string $m, string $p) => EnforceBillingLimits::isBlocked($m, "api/v1/tenant/{$p}");

        foreach ([['GET', 'reports/owner-summary'], ['GET', 'reports/daily-sales/summary'], ['GET', 'shift-analytics/overview'],
            ['POST', 'purchase-orders'], ['POST', 'batches/receive'], ['POST', 'products/abc-123/receive-stock'],
            ['POST', 'transfers'], ['PATCH', 'transfers/5/receive'], ['POST', 'users'], ['PUT', 'users/3/stores'],
            ['POST', 'stores'], ['PUT', 'tills/settings'], ['PATCH', 'business-details/settings']] as [$m, $p]) {
            $this->assertTrue($blocked($m, $p), "{$m} {$p} should be blocked");
        }

        foreach ([['POST', 'sales'], ['POST', 'sales/calculate'], ['POST', 'sales/12/refunds'], ['GET', 'catalog/sync'],
            ['GET', 'stores'], ['GET', 'transfers'], ['GET', 'users'], ['GET', 'products'], ['POST', 'products'],
            ['POST', 'expenses'], ['POST', 'drawer/movements'], ['GET', 'tills/current'], ['POST', 'inventory-movements/adjustment']] as [$m, $p]) {
            $this->assertFalse($blocked($m, $p), "{$m} {$p} should stay open");
        }
    }
}
