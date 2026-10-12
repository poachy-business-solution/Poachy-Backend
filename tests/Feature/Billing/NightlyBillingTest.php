<?php

namespace Tests\Feature\Billing;

use App\Enums\Central\BillingLedgerType;
use App\Models\BillingAccount;
use App\Models\BillingDay;
use App\Models\BillingLedgerEntry;
use App\Models\BillingPrice;
use App\Models\TenantSaleDay;
use App\Services\Central\Billing\NightlyBillingService;
use Carbon\CarbonImmutable;
use Database\Seeders\BillingPriceSeeder;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Nightly trading-day billing: rates, extra branches, monthly caps, trial, late offline sales,
 * month boundaries and re-run safety. Each test runs in a rolled-back central transaction.
 */
class NightlyBillingTest extends TestCase
{
    private string $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('tenancy.database.central_connection', 'central');
        DB::connection('central')->beginTransaction();
        $this->seed(BillingPriceSeeder::class);

        $this->tenant = 'nightly-'.Str::lower(Str::random(8));
        DB::connection('central')->table('tenants')->insert(['id' => $this->tenant, 'created_at' => now(), 'updated_at' => now()]);
    }

    protected function tearDown(): void
    {
        DB::connection('central')->rollBack();
        parent::tearDown();
    }

    private function account(array $attributes = []): BillingAccount
    {
        return BillingAccount::factory()->create($attributes + ['tenant_id' => $this->tenant, 'balance' => 1000]);
    }

    private function sold(int $store, string $date, bool $main = true): void
    {
        TenantSaleDay::query()->create([
            'tenant_id' => $this->tenant, 'store_id' => $store, 'date' => $date,
            'sales_count' => 3, 'is_main_branch' => $main, 'reported_at' => now(),
        ]);
    }

    /** Run as at 01:00 Nairobi on the given date. */
    private function runOn(string $date): array
    {
        return app(NightlyBillingService::class)->run(CarbonImmutable::parse("{$date} 01:00", 'Africa/Nairobi'));
    }

    private function billed(int $store, string $date): ?string
    {
        return BillingDay::query()->where('tenant_id', $this->tenant)->where('store_id', $store)->whereDate('date', $date)->value('amount');
    }

    public function test_the_main_branch_is_charged_the_day_rate_with_a_ledger_line(): void
    {
        $account = $this->account();
        $this->sold(1, '2026-10-11');

        $totals = $this->runOn('2026-10-12');

        $this->assertSame(1, $totals['days_billed']);
        $this->assertSame('100.00', $this->billed(1, '2026-10-11'));
        $this->assertSame('900.00', $account->fresh()->balance);

        $line = BillingLedgerEntry::query()->where('tenant_id', $this->tenant)->sole();
        $this->assertSame(BillingLedgerType::Charge, $line->type);
        $this->assertSame('-100.00', $line->amount);
        $this->assertSame('900.00', $line->balance_after);
        $this->assertSame('2026-10-11', $line->reference);
        $this->assertSame($line->id, BillingDay::query()->where('tenant_id', $this->tenant)->value('billing_ledger_id'));
    }

    public function test_three_branches_pay_the_first_and_extra_rates(): void
    {
        $account = $this->account();
        $this->sold(1, '2026-10-11');
        $this->sold(2, '2026-10-11', main: false);
        $this->sold(3, '2026-10-11', main: false);

        $this->runOn('2026-10-12');

        $this->assertSame('100.00', $this->billed(1, '2026-10-11'));
        $this->assertSame('50.00', $this->billed(2, '2026-10-11'));
        $this->assertSame('50.00', $this->billed(3, '2026-10-11'));
        $this->assertSame('800.00', $account->fresh()->balance);
        $this->assertSame(3, BillingLedgerEntry::query()->where('tenant_id', $this->tenant)->count());
    }

    public function test_a_day_with_no_sales_is_not_charged_and_today_waits(): void
    {
        $account = $this->account();
        $this->sold(1, '2026-10-12'); // "today" at 01:00 on the 12th — not complete yet

        $this->runOn('2026-10-12');
        $this->assertSame('1000.00', $account->fresh()->balance);

        $this->runOn('2026-10-13');
        $this->assertSame('100.00', $this->billed(1, '2026-10-12'));
    }

    public function test_a_late_offline_sale_is_billed_on_a_later_night_without_rebilling(): void
    {
        $account = $this->account();
        $this->sold(1, '2026-10-11');
        $this->runOn('2026-10-12');

        // Two nights later a phone comes back online with sales from the 9th.
        $this->sold(1, '2026-10-09');
        $this->sold(1, '2026-10-13');
        $this->runOn('2026-10-14');

        $this->assertSame('100.00', $this->billed(1, '2026-10-09'));
        $this->assertSame('100.00', $this->billed(1, '2026-10-13'));
        $this->assertSame(3, BillingDay::query()->where('tenant_id', $this->tenant)->count());
        $this->assertSame('700.00', $account->fresh()->balance);
    }

    public function test_sales_older_than_the_look_back_are_never_billed(): void
    {
        $account = $this->account();
        $this->sold(1, '2026-10-04'); // 8 days before the 12th

        $this->runOn('2026-10-12');

        $this->assertNull($this->billed(1, '2026-10-04'));
        $this->assertSame('1000.00', $account->fresh()->balance);
    }

    public function test_the_monthly_cap_is_reached_mid_month(): void
    {
        $account = $this->account(['balance' => 0]);
        // Already billed: main 2,450 this month (24 × 100 + one 50); extra branch 1,200.
        foreach (range(1, 24) as $d) {
            BillingDay::factory()->create(['tenant_id' => $this->tenant, 'store_id' => 1, 'date' => sprintf('2026-10-%02d', $d), 'amount' => 100, 'is_main_branch' => true]);
        }
        BillingDay::factory()->create(['tenant_id' => $this->tenant, 'store_id' => 1, 'date' => '2026-09-30', 'amount' => 100, 'is_main_branch' => true]); // other month: ignored
        BillingDay::factory()->create(['tenant_id' => $this->tenant, 'store_id' => 2, 'date' => '2026-10-01', 'amount' => 1200, 'is_main_branch' => false]);

        $this->sold(1, '2026-10-25');
        $this->sold(1, '2026-10-26');
        $this->sold(2, '2026-10-25', main: false);
        $this->sold(2, '2026-10-26', main: false);

        $this->runOn('2026-10-27');

        $this->assertSame('100.00', $this->billed(1, '2026-10-25')); // 2,400 → 2,500
        $this->assertSame('0.00', $this->billed(1, '2026-10-26'));   // capped
        $this->assertSame('50.00', $this->billed(2, '2026-10-25'));  // 1,200 → 1,250
        $this->assertSame('0.00', $this->billed(2, '2026-10-26'));
        $this->assertSame('-150.00', $account->fresh()->balance);
        // Capped days are recorded but write no ledger line.
        $this->assertSame(2, BillingLedgerEntry::query()->where('tenant_id', $this->tenant)->count());
    }

    public function test_a_partial_day_rate_brings_the_branch_exactly_to_the_cap(): void
    {
        $account = $this->account(['balance' => 0]);
        BillingDay::factory()->create(['tenant_id' => $this->tenant, 'store_id' => 1, 'date' => '2026-10-01', 'amount' => 2470, 'is_main_branch' => true]);
        $this->sold(1, '2026-10-11');

        $this->runOn('2026-10-12');

        $this->assertSame('30.00', $this->billed(1, '2026-10-11'));
        $this->assertTrue(BillingLedgerEntry::query()->where('tenant_id', $this->tenant)->sole()->meta['capped']);
    }

    public function test_the_cap_resets_at_the_month_boundary(): void
    {
        $this->account(['balance' => 0]);
        BillingDay::factory()->create(['tenant_id' => $this->tenant, 'store_id' => 1, 'date' => '2026-10-01', 'amount' => 2500, 'is_main_branch' => true]);
        $this->sold(1, '2026-10-31');
        $this->sold(1, '2026-11-01');

        $this->runOn('2026-11-02');

        $this->assertSame('0.00', $this->billed(1, '2026-10-31'));
        $this->assertSame('100.00', $this->billed(1, '2026-11-01'));
    }

    public function test_trial_days_cost_nothing_and_are_never_charged_later(): void
    {
        $account = $this->account(['status' => 'trial', 'trial_ends_at' => CarbonImmutable::parse('2026-10-10 12:00', 'Africa/Nairobi')]);
        $this->sold(1, '2026-10-08');
        $this->sold(1, '2026-10-09');
        $this->sold(2, '2026-10-09', main: false);

        $this->runOn('2026-10-10');
        $this->assertSame('0.00', $this->billed(1, '2026-10-08'));
        $this->assertSame('0.00', $this->billed(1, '2026-10-09'));
        $this->assertSame('0.00', $this->billed(2, '2026-10-09'));

        // Trial over: the 10th (trial ended mid-day) and the 11th are charged; earlier days stay free.
        $this->sold(1, '2026-10-10');
        $this->sold(1, '2026-10-11');
        $this->runOn('2026-10-12');

        $this->assertSame('100.00', $this->billed(1, '2026-10-10'));
        $this->assertSame('100.00', $this->billed(1, '2026-10-11'));
        $this->assertSame('0.00', $this->billed(1, '2026-10-08'));
        $this->assertSame('800.00', $account->fresh()->balance);
    }

    public function test_re_running_the_same_night_bills_nothing_twice(): void
    {
        $account = $this->account();
        $this->sold(1, '2026-10-10');
        $this->sold(1, '2026-10-11');
        $this->sold(2, '2026-10-11', main: false);

        $first = $this->runOn('2026-10-12');
        $second = $this->runOn('2026-10-12');
        $third = app(NightlyBillingService::class)->run(CarbonImmutable::parse('2026-10-12 23:00', 'Africa/Nairobi'));

        $this->assertSame(3, $first['days_billed']);
        $this->assertSame(0, $second['days_billed']);
        $this->assertSame(0, $third['days_billed']);
        $this->assertSame('750.00', $account->fresh()->balance);
        $this->assertSame(3, BillingLedgerEntry::query()->where('tenant_id', $this->tenant)->count());
    }

    public function test_the_balance_may_go_negative(): void
    {
        $account = $this->account(['balance' => 40]);
        $this->sold(1, '2026-10-11');

        $this->runOn('2026-10-12');

        $this->assertSame('-60.00', $account->fresh()->balance);
    }

    public function test_without_a_flagged_main_branch_the_lowest_store_pays_the_first_rate(): void
    {
        $this->account();
        $this->sold(7, '2026-10-11', main: false);
        $this->sold(4, '2026-10-11', main: false);

        $this->runOn('2026-10-12');

        $this->assertSame('100.00', $this->billed(4, '2026-10-11'));
        $this->assertSame('50.00', $this->billed(7, '2026-10-11'));
    }

    public function test_a_price_change_applies_from_its_effective_date(): void
    {
        $this->account();
        BillingPrice::factory()->create(['effective_from' => '2026-10-11', 'first_branch_day' => 120]);
        $this->sold(1, '2026-10-10');
        $this->sold(1, '2026-10-11');

        $this->runOn('2026-10-12');

        $this->assertSame('100.00', $this->billed(1, '2026-10-10'));
        $this->assertSame('120.00', $this->billed(1, '2026-10-11'));
    }

    public function test_a_shop_without_an_account_is_skipped(): void
    {
        $this->sold(1, '2026-10-11');

        $this->assertSame(0, $this->runOn('2026-10-12')['days_billed']);
        $this->assertNull($this->billed(1, '2026-10-11'));
    }

    public function test_the_command_runs_as_of_a_night(): void
    {
        $account = $this->account();
        $this->sold(1, '2026-10-11');

        $this->artisan('billing:run', ['--as-of' => '2026-10-12 01:00'])->expectsOutputToContain('Billed 1 branch-day(s)')->assertSuccessful();

        $this->assertSame('900.00', $account->fresh()->balance);
    }
}
