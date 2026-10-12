<?php

namespace Tests\Feature\Internal;

use App\Enums\Central\BillingStatus;
use App\Jobs\Central\ProvisionSignupJob;
use App\Mail\Central\Billing\BillingNoticeMail;
use App\Mail\Shared\PlainMessageMail;
use App\Models\AdminAuditLog;
use App\Models\BillingAccount;
use App\Models\BillingLedgerEntry;
use App\Models\BillingPrice;
use App\Models\BillingTopup;
use App\Models\BusinessDetail;
use App\Models\PaymentLink;
use App\Models\SignupRequest;
use Carbon\CarbonImmutable;
use Database\Seeders\BillingPriceSeeder;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Private admin API (/api/internal/admin): who may call it, and each shop / billing / price action
 * with its audit log entry. Runs in a rolled-back central transaction.
 */
class InternalAdminApiTest extends TestCase
{
    private const TOKEN = 'test-internal-admin-token-0123456789abcdef';

    private string $tenant;

    private string $poa;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('database.default', 'central');
        Config::set('tenancy.database.central_connection', 'central');
        Config::set('messaging.site_url', 'https://poachy.test');
        Config::set('internal_admin.token', self::TOKEN);
        Config::set('internal_admin.allowed_ips', ['127.0.0.1', '10.0.0.0/8']);
        DB::connection('central')->beginTransaction();
        $this->seed(BillingPriceSeeder::class);
        Mail::fake();
        RateLimiter::clear('internal-admin');

        $this->tenant = 'iadmin-'.Str::lower(Str::random(8));
        $this->poa = 'POA6'.random_int(1000, 9999);
        DB::connection('central')->table('tenants')->insert(['id' => $this->tenant, 'mpesa_paybill_account' => $this->poa, 'created_at' => now(), 'updated_at' => now()]);
        BusinessDetail::query()->create([
            'tenant_id' => $this->tenant, 'business_name' => 'Admin Test Duka', 'business_type_id' => DB::connection('central')->table('business_types')->value('id'),
            'business_category_id' => DB::connection('central')->table('business_categories')->value('id'),
            'business_phone' => '0712345678', 'business_email' => 'owner@iadmin-test.example', 'status' => 'active', 'onboarded_at' => now(),
        ]);
        BillingAccount::factory()->create(['tenant_id' => $this->tenant, 'balance' => -50, 'status' => 'limited', 'limited_since' => now()->subHour()]);
    }

    protected function tearDown(): void
    {
        DB::connection('central')->rollBack();
        parent::tearDown();
    }

    /** @param array<string, string> $headers */
    private function admin(string $method, string $path, array $data = [], string $role = 'super', array $headers = []): TestResponse
    {
        return $this->json($method, "/api/internal/admin{$path}", $data, $headers + [
            'Authorization' => 'Bearer '.self::TOKEN, 'X-Admin-Id' => '42', 'X-Admin-Role' => $role, 'X-Admin-Name' => 'Wanjiku Ops',
        ]);
    }

    private function audit(string $action): AdminAuditLog
    {
        return AdminAuditLog::query()->where('action', $action)->where('tenant_id', $this->tenant)->latest('id')->firstOrFail();
    }

    public function test_only_the_admin_app_can_call_it(): void
    {
        $path = '/api/internal/admin/billing/prices';
        $admin = ['X-Admin-Id' => '42', 'X-Admin-Role' => 'super'];

        $this->getJson($path, $admin)->assertUnauthorized();
        $this->getJson($path, $admin + ['Authorization' => 'Bearer wrong-token-0123456789abcdef0123456789'])->assertUnauthorized();
        $this->getJson($path, ['Authorization' => 'Bearer '.self::TOKEN])->assertUnprocessable();
        $this->getJson($path, ['Authorization' => 'Bearer '.self::TOKEN, 'X-Admin-Id' => '42', 'X-Admin-Role' => 'owner'])->assertUnprocessable();

        // Wrong network: as if the route did not exist, even with the right token; a forwarded
        // header cannot fake the address.
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
            ->getJson($path, $admin + ['Authorization' => 'Bearer '.self::TOKEN, 'X-Forwarded-For' => '127.0.0.1'])->assertNotFound();
        $this->withServerVariables(['REMOTE_ADDR' => '10.1.2.3'])->getJson($path, $admin + ['Authorization' => 'Bearer '.self::TOKEN])->assertOk();

        // Switched off when no token is configured.
        Config::set('internal_admin.token', '');
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])->getJson($path, $admin + ['Authorization' => 'Bearer '.self::TOKEN])->assertNotFound();
    }

    public function test_roles_limit_what_an_admin_can_do(): void
    {
        $this->admin('POST', "/shops/{$this->poa}/billing/credit", ['amount' => 100, 'reason' => 'Goodwill'], 'support', ['Idempotency-Key' => 'r1'])->assertForbidden();
        $this->admin('POST', "/shops/{$this->poa}/billing/credit", ['amount' => 100, 'reason' => 'Goodwill'], 'operations', ['Idempotency-Key' => 'r1'])->assertForbidden();
        $this->admin('POST', "/shops/{$this->poa}/suspend", ['reason' => 'Fraud report'], 'finance')->assertForbidden();
        $this->admin('POST', '/billing/prices', [], 'support')->assertForbidden();
        $this->admin('GET', '/billing/prices', [], 'support')->assertOk();

        $this->assertSame(0, BillingLedgerEntry::query()->where('tenant_id', $this->tenant)->count());
        $this->assertSame('active', BusinessDetail::query()->where('tenant_id', $this->tenant)->value('status'));
    }

    public function test_a_manual_credit_is_idempotent_audited_and_restores_access(): void
    {
        $this->admin('POST', "/shops/{$this->poa}/billing/credit", ['amount' => 500, 'reason' => 'Goodwill'], 'finance')
            ->assertUnprocessable()->assertJsonValidationErrors('idempotency_key');
        $this->admin('POST', "/shops/{$this->poa}/billing/credit", ['amount' => 999999, 'reason' => 'x'], 'finance', ['Idempotency-Key' => 'c1'])
            ->assertUnprocessable()->assertJsonValidationErrors(['amount', 'reason']);

        $first = $this->admin('POST', "/shops/{$this->poa}/billing/credit", ['amount' => 500, 'reason' => 'Goodwill after outage'], 'finance', ['Idempotency-Key' => 'c1'])
            ->assertCreated()->assertJsonPath('data.amount', 500)->assertJsonPath('data.balance_after', 450);
        $this->admin('POST', "/shops/{$this->poa}/billing/credit", ['amount' => 500, 'reason' => 'Goodwill after outage'], 'finance', ['Idempotency-Key' => 'c1'])
            ->assertOk()->assertJsonPath('data.id', $first->json('data.id'));

        $account = BillingAccount::query()->where('tenant_id', $this->tenant)->firstOrFail();
        $this->assertSame(450.0, (float) $account->balance);
        $this->assertSame(BillingStatus::Active, $account->status);
        $entry = BillingLedgerEntry::query()->findOrFail($first->json('data.id'));
        $this->assertSame('admin:42', $entry->created_by);
        $this->assertSame('adjustment', $entry->type->value);

        $log = $this->audit('billing.credit');
        $this->assertSame(['42', 'Wanjiku Ops', 'finance'], [$log->admin_id, $log->admin_name, $log->admin_role]);
        $this->assertSame(['balance' => -50], $log->before);
        $this->assertSame(450, (int) $log->after['balance']);
        $this->assertSame('Goodwill after outage', $log->reason);
        $this->assertSame(1, AdminAuditLog::query()->where('action', 'billing.credit')->where('tenant_id', $this->tenant)->count());
    }

    public function test_send_stk_link_and_check_a_topup(): void
    {
        $prompt = $this->admin('POST', "/shops/{$this->tenant}/billing/stk", ['amount' => 700], 'operations', ['Idempotency-Key' => 's1'])
            ->assertCreated()->assertJsonPath('data.status', 'processing')->assertJsonPath('data.phone', '2547****5678');
        $this->admin('POST', "/shops/{$this->tenant}/billing/stk", ['amount' => 700], 'operations', ['Idempotency-Key' => 's1'])
            ->assertOk()->assertJsonPath('data.id', $prompt->json('data.id'));
        $this->assertSame('admin:42', BillingTopup::query()->findOrFail($prompt->json('data.id'))->initiated_by);
        $this->admin('GET', "/shops/{$this->tenant}/billing/topups/{$prompt->json('data.id')}", [], 'support')->assertOk();
        $this->admin('GET', '/shops/no-such-shop/billing/topups/1')->assertNotFound();
        $this->audit('billing.stk');

        $link = $this->admin('POST', "/shops/{$this->poa}/billing/link", ['amount' => 700, 'email' => true], 'support')->assertCreated();
        $this->assertStringStartsWith('https://poachy.test/pay/', $link->json('data.url'));
        $this->assertSame('owner@iadmin-test.example', $link->json('data.emailed_to'));
        $this->assertSame('admin:42', PaymentLink::query()->where('tenant_id', $this->tenant)->latest('id')->value('created_by'));
        Mail::assertQueued(BillingNoticeMail::class, fn (BillingNoticeMail $m) => $m->kind === 'payment_link' && $m->payUrl === $link->json('data.url'));
        $this->assertTrue($this->audit('billing.link')->after['emailed']);
    }

    public function test_suspend_and_restore(): void
    {
        $this->admin('POST', "/shops/{$this->poa}/restore", ['reason' => 'Paid'])->assertUnprocessable();
        $this->admin('POST', "/shops/{$this->poa}/suspend", [])->assertUnprocessable()->assertJsonValidationErrors('reason');

        $this->admin('POST', "/shops/{$this->poa}/suspend", ['reason' => 'Fraud report under review'], 'operations')->assertOk()->assertJsonPath('data.status', 'suspended');
        $this->assertSame('suspended', BusinessDetail::query()->where('tenant_id', $this->tenant)->value('status'));
        $this->assertSame(['active', 'suspended'], [$this->audit('shop.suspend')->before['status'], $this->audit('shop.suspend')->after['status']]);

        $this->admin('POST', "/shops/{$this->poa}/restore", ['reason' => 'Cleared'], 'operations')->assertOk();
        $this->assertSame('active', BusinessDetail::query()->where('tenant_id', $this->tenant)->value('status'));
        $this->assertSame('Cleared', $this->audit('shop.restore')->reason);
        $this->admin('POST', '/shops/no-such-shop/suspend', ['reason' => 'Fraud'])->assertNotFound();
    }

    public function test_extend_a_running_trial_or_reopen_an_ended_one(): void
    {
        $account = BillingAccount::query()->where('tenant_id', $this->tenant)->firstOrFail();
        $ends = CarbonImmutable::now()->addDays(5)->startOfSecond();
        $account->forceFill(['status' => BillingStatus::Trial, 'trial_ends_at' => $ends, 'balance' => 0, 'limited_since' => null])->save();

        $this->admin('POST', "/shops/{$this->poa}/trial", ['days' => 10, 'reason' => 'Late onboarding'], 'operations')->assertOk()->assertJsonPath('data.status', 'trial');
        $this->assertTrue($account->fresh()->trial_ends_at->equalTo($ends->addDays(10)));

        // Ended trial, now limited: a new trial starts from now and access comes back.
        $account->forceFill(['status' => BillingStatus::Limited, 'trial_ends_at' => now()->subDays(3), 'balance' => -20, 'limited_since' => now()->subDay()])->save();
        $this->admin('POST', "/shops/{$this->poa}/trial", ['days' => 7, 'reason' => 'Promise to pay'], 'finance')->assertOk()->assertJsonPath('data.status', 'trial');
        $this->assertEqualsWithDelta(7 * 86400, now()->diffInSeconds($account->fresh()->trial_ends_at), 10);
        $this->assertSame('limited', $this->audit('shop.trial.extend')->before['status']);

        $this->admin('POST', "/shops/{$this->poa}/trial", ['days' => 365, 'reason' => 'x'])->assertUnprocessable()->assertJsonValidationErrors(['days', 'reason']);
    }

    public function test_verification_decisions(): void
    {
        $this->admin('POST', "/shops/{$this->poa}/verification", ['decision' => 'reject'])->assertUnprocessable()->assertJsonValidationErrors('reason');

        $this->admin('POST', "/shops/{$this->poa}/verification", ['decision' => 'approve'], 'operations')->assertOk()->assertJsonPath('data.is_verified', true);
        $this->assertTrue((bool) BusinessDetail::query()->where('tenant_id', $this->tenant)->value('is_verified'));

        $this->admin('POST', "/shops/{$this->poa}/verification", ['decision' => 'revoke', 'reason' => 'Permit expired'])->assertOk()->assertJsonPath('data.is_verified', false);
        $this->admin('POST', "/shops/{$this->poa}/verification", ['decision' => 'reject', 'reason' => 'The shop photo is unclear.'])->assertOk();
        Mail::assertQueued(PlainMessageMail::class, fn (PlainMessageMail $m) => $m->hasTo('owner@iadmin-test.example') && in_array('The shop photo is unclear.', $m->paragraphs, true));

        $this->assertSame(['shop.verification.approve', 'shop.verification.revoke', 'shop.verification.reject'],
            AdminAuditLog::query()->where('tenant_id', $this->tenant)->orderBy('id')->pluck('action')->all());
    }

    public function test_prices_change_only_from_a_future_date(): void
    {
        $row = ['first_branch_day' => 120, 'extra_branch_day' => 60, 'first_branch_cap' => 3000, 'extra_branch_cap' => 1500,
            'trial_days' => 30, 'min_topup' => 10, 'low_balance_days' => 3, 'grace_days' => 1, 'notes' => '2027 prices'];

        $this->admin('POST', '/billing/prices', $row + ['effective_from' => now('Africa/Nairobi')->toDateString()], 'finance')
            ->assertUnprocessable()->assertJsonValidationErrors('effective_from');

        $date = now('Africa/Nairobi')->addMonth()->startOfMonth()->toDateString();
        $this->admin('POST', '/billing/prices', $row + ['effective_from' => $date], 'finance')->assertCreated()->assertJsonPath('data.first_branch_day', 120);
        $this->admin('POST', '/billing/prices', $row + ['effective_from' => $date], 'finance')->assertStatus(409);

        $this->assertSame('admin:42', BillingPrice::query()->whereDate('effective_from', $date)->value('created_by'));
        $this->assertSame(120, (int) AdminAuditLog::query()->where('action', 'billing.price.create')->latest('id')->firstOrFail()->after['first_branch_day']);
        $this->admin('GET', '/billing/prices', [], 'support')->assertOk()->assertJsonPath('data.0.effective_from', $date);
    }

    public function test_onboarding_queues_the_same_provisioning_as_sign_up(): void
    {
        Queue::fake();
        $workspace = 'iadmin-'.Str::lower(Str::random(6));
        $body = ['shop_name' => 'Njeri Mini Mart', 'owner_name' => 'Njeri Wambui', 'phone' => '07'.random_int(10000000, 99999999),
            'email' => "{$workspace}@example.com", 'town' => 'Nyeri', 'workspace' => $workspace];

        $this->admin('POST', '/shops', $body, 'finance')->assertForbidden();
        $this->admin('POST', '/shops', ['phone' => '123'] + $body)->assertUnprocessable()->assertJsonValidationErrors('phone');
        $this->admin('POST', '/shops', ['phone' => '0712345678'] + $body)->assertUnprocessable(); // already a shop's phone

        $made = $this->admin('POST', '/shops', $body, 'operations')->assertStatus(202)->assertJsonPath('data.status', 'provisioning')->assertJsonPath('data.workspace', $workspace);
        $signup = SignupRequest::query()->findOrFail($made->json('data.id'));
        $this->assertSame([SignupRequest::SOURCE_ADMIN, '42'], [$signup->source, $signup->created_by_admin]);
        $this->assertNull($signup->password_hash);
        Queue::assertPushed(ProvisionSignupJob::class, fn (ProvisionSignupJob $job) => $job->signupId === $signup->id);

        $this->admin('POST', '/shops', ['email' => 'other@example.com', 'phone' => '0799'.random_int(100000, 999999)] + $body)
            ->assertUnprocessable()->assertJsonValidationErrors('workspace');
        $this->admin('GET', "/shops/onboarding/{$signup->id}", [], 'support')->assertOk()->assertJsonPath('data.status', 'provisioning');
        $this->assertSame($workspace, AdminAuditLog::query()->where('action', 'shop.onboard')->latest('id')->firstOrFail()->after['workspace']);
    }
}
