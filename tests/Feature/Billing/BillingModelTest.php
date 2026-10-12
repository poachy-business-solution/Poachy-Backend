<?php

namespace Tests\Feature\Billing;

use App\Enums\Central\BillingLedgerType;
use App\Enums\Central\BillingStatus;
use App\Models\BillingAccount;
use App\Models\BillingDay;
use App\Models\BillingLedgerEntry;
use App\Models\BillingPrice;
use App\Models\PaymentLink;
use App\Models\Tenant;
use App\Observers\Central\TenantObserver;
use Database\Seeders\BillingPriceSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Billing tables, seeded prices, the trial hook for new shops and the one-off command that
 * opens accounts for existing shops. Each test runs inside a central-DB transaction.
 */
class BillingModelTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Config::set('tenancy.database.central_connection', 'central');
        DB::connection('central')->beginTransaction();
        $this->seed(BillingPriceSeeder::class);
    }

    protected function tearDown(): void
    {
        DB::connection('central')->rollBack();
        parent::tearDown();
    }

    private function tenant(): string
    {
        $id = 'billing-'.Str::lower(Str::random(10));
        DB::connection('central')->table('tenants')->insert(['id' => $id, 'created_at' => now(), 'updated_at' => now()]);

        return $id;
    }

    public function test_launch_prices_are_seeded(): void
    {
        $price = BillingPrice::forDate('2026-10-12');

        $this->assertSame(100.0, $price->dayRate(true));
        $this->assertSame(50.0, $price->dayRate(false));
        $this->assertSame(2500.0, $price->monthlyCap(true));
        $this->assertSame(1250.0, $price->monthlyCap(false));
        $this->assertSame(30, $price->trial_days);
        $this->assertSame('10.00', $price->min_topup);
        $this->assertSame(3, $price->low_balance_days);
        $this->assertSame(1, $price->grace_days);
    }

    public function test_a_later_price_applies_only_from_its_effective_date(): void
    {
        BillingPrice::factory()->create(['effective_from' => '2027-02-01', 'first_branch_day' => 120]);

        $this->assertSame(100.0, BillingPrice::forDate('2027-01-31')->dayRate(true));
        $this->assertSame(120.0, BillingPrice::forDate('2027-02-01')->dayRate(true));
        $this->assertNull(BillingPrice::forDate('2025-12-31'));
    }

    public function test_a_new_shop_gets_a_30_day_trial_account(): void
    {
        $this->travelTo('2026-10-12 09:00:00');
        $id = $this->tenant();

        (new TenantObserver)->created(Tenant::query()->findOrFail($id));

        $account = BillingAccount::query()->where('tenant_id', $id)->firstOrFail();
        $this->assertSame(BillingStatus::Trial, $account->status);
        $this->assertSame('0.00', $account->balance);
        $this->assertSame('2026-11-11 09:00:00', $account->trial_ends_at->toDateTimeString());
        $this->assertTrue($account->isInTrial());
        $this->assertFalse($account->isInTrial(now()->addDays(31)));

        // Running the hook again does not reset the trial.
        $this->travelTo('2026-10-20 09:00:00');
        (new TenantObserver)->created(Tenant::query()->findOrFail($id));
        $this->assertSame(1, BillingAccount::query()->where('tenant_id', $id)->count());
        $this->assertSame('2026-11-11', $account->fresh()->trial_ends_at->toDateString());
    }

    public function test_the_command_opens_active_accounts_for_existing_shops_only_once(): void
    {
        $withoutAccount = $this->tenant();
        $withTrial = $this->tenant();
        BillingAccount::factory()->trial()->create(['tenant_id' => $withTrial]);

        $this->artisan('poachy:billing-open-accounts')->assertSuccessful();

        $opened = BillingAccount::query()->where('tenant_id', $withoutAccount)->firstOrFail();
        $this->assertSame(BillingStatus::Active, $opened->status);
        $this->assertNull($opened->trial_ends_at);
        $this->assertSame(BillingStatus::Trial, BillingAccount::query()->where('tenant_id', $withTrial)->value('status'));

        $before = BillingAccount::query()->count();
        $this->artisan('poachy:billing-open-accounts')->assertSuccessful();
        $this->assertSame($before, BillingAccount::query()->count());
    }

    public function test_dry_run_creates_nothing(): void
    {
        $id = $this->tenant();

        $this->artisan('poachy:billing-open-accounts', ['--dry-run' => true])->expectsOutputToContain("Would open {$id}")->assertSuccessful();

        $this->assertFalse(BillingAccount::query()->where('tenant_id', $id)->exists());
    }

    public function test_a_branch_day_cannot_be_billed_twice(): void
    {
        $id = $this->tenant();
        BillingDay::factory()->create(['tenant_id' => $id, 'store_id' => 3, 'date' => '2026-10-11']);
        BillingDay::factory()->create(['tenant_id' => $id, 'store_id' => 4, 'date' => '2026-10-11']); // other branch: fine

        $this->expectException(QueryException::class);
        BillingDay::factory()->create(['tenant_id' => $id, 'store_id' => 3, 'date' => '2026-10-11']);
    }

    public function test_ledger_idempotency_keys_are_unique(): void
    {
        $account = BillingAccount::factory()->create(['tenant_id' => $this->tenant()]);
        BillingLedgerEntry::factory()->create(['billing_account_id' => $account->id, 'idempotency_key' => 'c2b:FK1']);

        $entry = BillingLedgerEntry::query()->where('idempotency_key', 'c2b:FK1')->firstOrFail();
        $this->assertSame(BillingLedgerType::Topup, $entry->type);
        $this->assertSame($account->tenant_id, $entry->tenant_id);

        $this->expectException(QueryException::class);
        BillingLedgerEntry::factory()->create(['billing_account_id' => $account->id, 'idempotency_key' => 'c2b:FK1']);
    }

    public function test_payment_links_are_found_by_token_but_store_only_a_hash(): void
    {
        $token = Str::random(40);
        $link = PaymentLink::factory()->create(['tenant_id' => $this->tenant(), 'token_hash' => PaymentLink::hashToken($token)]);

        $this->assertTrue(PaymentLink::findByToken($token)->is($link));
        $this->assertNull(PaymentLink::findByToken('wrong'));
        $this->assertNotSame($token, $link->token_hash);
        $this->assertArrayNotHasKey('token_hash', $link->toArray());
        $this->assertFalse($link->isExpired());
    }

    public function test_deleting_a_shop_removes_its_billing_rows(): void
    {
        $id = $this->tenant();
        BillingAccount::factory()->create(['tenant_id' => $id]);
        BillingDay::factory()->create(['tenant_id' => $id]);

        DB::connection('central')->table('tenants')->where('id', $id)->delete();

        $this->assertFalse(BillingAccount::query()->where('tenant_id', $id)->exists());
        $this->assertFalse(BillingDay::query()->where('tenant_id', $id)->exists());
    }
}
