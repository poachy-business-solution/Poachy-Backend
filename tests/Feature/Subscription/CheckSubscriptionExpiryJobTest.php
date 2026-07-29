<?php

namespace Tests\Feature\Subscription;

use App\Jobs\Central\Subscription\CheckSubscriptionExpiryJob;
use App\Jobs\Central\Subscription\SendSubscriptionExpiredNoticeJob;
use App\Jobs\Central\Subscription\SendSubscriptionReminderJob;
use App\Models\BusinessSubscription;
use App\Models\SubscriptionPlan;
use App\Services\Central\Subscription\SubscriptionExpiryService;
use App\Services\Tenant\TenantAccessService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CheckSubscriptionExpiryJobTest extends TestCase
{
    private SubscriptionPlan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('tenancy.database.central_connection', 'central');
        Config::set('database.connections.central.host', env('CENTRAL_DB_HOST', '127.0.0.1'));
        Config::set('database.connections.central.port', env('CENTRAL_DB_PORT', '3306'));
        Config::set('database.connections.central.database', env('CENTRAL_DB_DATABASE', 'poachy'));
        Config::set('database.connections.central.username', env('CENTRAL_DB_USERNAME', 'root'));
        Config::set('database.connections.central.password', env('CENTRAL_DB_PASSWORD', ''));
        DB::purge('central');
        DB::connection('central')->statement('SET foreign_key_checks = 0');

        $this->plan = SubscriptionPlan::on('central')->create([
            'name' => 'Job Test Plan',
            'slug' => 'job-test-plan-'.uniqid(),
            'price' => 1000.00,
            'billing_cycle_days' => 30,
            'is_active' => true,
            'is_featured' => false,
        ]);

        DB::connection('central')->table('tenants')->insertOrIgnore([
            'id' => 'job-test-tenant',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Cache::forget('tenant_access:job-test-tenant');
    }

    protected function tearDown(): void
    {
        DB::connection('central')->statement('SET foreign_key_checks = 0');
        BusinessSubscription::on('central')->where('tenant_id', 'job-test-tenant')->forceDelete();
        DB::connection('central')->table('tenants')->where('id', 'job-test-tenant')->delete();
        SubscriptionPlan::on('central')->where('id', $this->plan->id)->forceDelete();
        DB::connection('central')->statement('SET foreign_key_checks = 1');
        Cache::forget('tenant_access:job-test-tenant');
        parent::tearDown();
    }

    private function createSubscription(array $overrides = []): BusinessSubscription
    {
        return BusinessSubscription::on('central')->create(array_merge([
            'tenant_id' => 'job-test-tenant',
            'subscription_plan_id' => $this->plan->id,
            'start_date' => now()->subDays(30)->toDateString(),
            'end_date' => now()->addDays(10)->toDateString(),
            'amount_paid' => 1000.00,
            'status' => 'active',
        ], $overrides));
    }

    public function test_job_flips_active_row_past_end_date_to_expired(): void
    {
        $subscription = $this->createSubscription(['end_date' => now()->subDay()->toDateString()]);

        Bus::fake();

        (new CheckSubscriptionExpiryJob)->handle(new SubscriptionExpiryService, new TenantAccessService);

        $subscription->refresh();
        $this->assertSame('expired', $subscription->status);
    }

    public function test_job_clears_tenant_access_cache_when_flipping_row(): void
    {
        $this->createSubscription(['end_date' => now()->subDay()->toDateString()]);
        Cache::put('tenant_access:job-test-tenant', ['allowed' => true], now()->addHours(24));

        Bus::fake();

        (new CheckSubscriptionExpiryJob)->handle(new SubscriptionExpiryService, new TenantAccessService);

        $this->assertFalse(Cache::has('tenant_access:job-test-tenant'));
    }

    public function test_job_does_not_re_notify_on_second_run_same_day(): void
    {
        $subscription = $this->createSubscription(['status' => 'expired', 'end_date' => now()->subDay()->toDateString()]);

        Bus::fake();

        $job = new CheckSubscriptionExpiryJob;
        $expiryService = new SubscriptionExpiryService;
        $tenantAccess = new TenantAccessService;

        $job->handle($expiryService, $tenantAccess);

        // Simulate SendSubscriptionExpiredNoticeJob actually running and marking itself notified —
        // Bus::fake() prevents it from executing for real in this test.
        BusinessSubscription::on('central')
            ->where('id', $subscription->id)
            ->update(['expired_notified' => true, 'expired_notified_at' => now()]);

        $job->handle($expiryService, $tenantAccess);

        // Scoped to this test's own subscription id — the shared test database can carry
        // other 'expired' rows from other tests, so a global dispatch count would be unreliable.
        $dispatchesForThisSubscription = Bus::dispatched(
            SendSubscriptionExpiredNoticeJob::class,
            fn (SendSubscriptionExpiredNoticeJob $noticeJob) => $noticeJob->subscriptionId === $subscription->id,
        );

        $this->assertCount(1, $dispatchesForThisSubscription);
    }

    public function test_job_dispatches_reminder_jobs_for_eligible_subscriptions_and_not_for_already_sent_ones(): void
    {
        $due = $this->createSubscription(['end_date' => now()->addDays(6)->toDateString()]);
        $alreadySent = $this->createSubscription([
            'end_date' => now()->addDays(6)->toDateString(),
            'reminder_7day_sent' => true,
        ]);

        Bus::fake();

        (new CheckSubscriptionExpiryJob)->handle(new SubscriptionExpiryService, new TenantAccessService);

        Bus::assertDispatched(SendSubscriptionReminderJob::class, function (SendSubscriptionReminderJob $job) use ($due) {
            return $job->subscriptionId === $due->id && $job->reminderTier === 7;
        });

        Bus::assertNotDispatched(SendSubscriptionReminderJob::class, function (SendSubscriptionReminderJob $job) use ($alreadySent) {
            return $job->subscriptionId === $alreadySent->id;
        });
    }
}
