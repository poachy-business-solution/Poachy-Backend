<?php

namespace Tests\Feature\Subscription;

use App\Jobs\Central\Subscription\SendSubscriptionExpiredNoticeJob;
use App\Jobs\Central\Subscription\SendSubscriptionReminderJob;
use App\Mail\Central\Subscription\SubscriptionExpiredMail;
use App\Mail\Central\Subscription\SubscriptionReminderMail;
use App\Models\BusinessSubscription;
use App\Models\SubscriptionPlan;
use App\Models\SystemNotification;
use App\Services\Central\Notification\SystemNotificationService;
use App\Services\Central\Subscription\SubscriptionExpiryService;
use App\Services\Central\Subscription\SubscriptionPaymentService;
use App\Services\Shared\Mpesa\MpesaService;
use App\Services\Tenant\TenantAccessService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Tests\TestCase;

class SubscriptionNotificationJobsTest extends TestCase
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
            'name' => 'Notify Test Plan',
            'slug' => 'notify-test-plan-'.uniqid(),
            'price' => 2000.00,
            'billing_cycle_days' => 30,
            'is_active' => true,
            'is_featured' => false,
        ]);

        DB::connection('central')->table('tenants')->insertOrIgnore([
            'id' => 'notify-test-tenant',
            'mpesa_paybill_account' => 'POA99001',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('central')->table('business_details')->updateOrInsert(
            ['tenant_id' => 'notify-test-tenant'],
            [
                'business_name' => 'Notify Test Biz',
                'business_phone' => '0712345000',
                'business_type_id' => 1,
                'business_category_id' => 1,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    protected function tearDown(): void
    {
        $businessDetailId = DB::connection('central')->table('business_details')
            ->where('tenant_id', 'notify-test-tenant')
            ->value('id');

        DB::connection('central')->statement('SET foreign_key_checks = 0');
        BusinessSubscription::on('central')->where('tenant_id', 'notify-test-tenant')->forceDelete();
        if ($businessDetailId) {
            SystemNotification::on('central')->forRecipient('tenant', $businessDetailId)->forceDelete();
        }
        DB::connection('central')->table('business_details')->where('tenant_id', 'notify-test-tenant')->delete();
        DB::connection('central')->table('tenants')->where('id', 'notify-test-tenant')->delete();
        SubscriptionPlan::on('central')->where('id', $this->plan->id)->forceDelete();
        DB::connection('central')->statement('SET foreign_key_checks = 1');
        Mockery::close();
        parent::tearDown();
    }

    private function createSubscription(array $overrides = []): BusinessSubscription
    {
        return BusinessSubscription::on('central')->create(array_merge([
            'tenant_id' => 'notify-test-tenant',
            'subscription_plan_id' => $this->plan->id,
            'start_date' => now()->subDays(30)->toDateString(),
            'end_date' => now()->addDays(7)->toDateString(),
            'amount_paid' => 2000.00,
            'status' => 'active',
        ], $overrides));
    }

    private function makePaymentService(): SubscriptionPaymentService
    {
        $mpesa = Mockery::mock(MpesaService::class);
        $mpesa->shouldReceive('getActiveCredentials')->andReturn(['shortcode' => '174379']);

        return new SubscriptionPaymentService(
            $mpesa,
            new TenantAccessService,
            new SystemNotificationService,
            new SubscriptionExpiryService,
        );
    }

    private function makeExpiryServiceWithOwner(): SubscriptionExpiryService
    {
        $expiryService = Mockery::mock(SubscriptionExpiryService::class)->makePartial();
        $expiryService->shouldReceive('resolveOwnerEmail')
            ->andReturn(['name' => 'Test Owner', 'email' => 'owner@notify-test.com']);

        return $expiryService;
    }

    private function businessDetailId(): int
    {
        return DB::connection('central')->table('business_details')
            ->where('tenant_id', 'notify-test-tenant')
            ->value('id');
    }

    // =========================================================================
    // SendSubscriptionReminderJob
    // =========================================================================

    public function test_reminder_job_sends_mail_creates_in_app_notification_and_marks_column_sent(): void
    {
        Mail::fake();

        $subscription = $this->createSubscription(['end_date' => now()->addDays(6)->toDateString()]);

        $job = new SendSubscriptionReminderJob($subscription->id, 7);
        $job->handle($this->makeExpiryServiceWithOwner(), $this->makePaymentService(), new SystemNotificationService);

        Mail::assertQueued(SubscriptionReminderMail::class, fn (SubscriptionReminderMail $mail) => $mail->hasTo('owner@notify-test.com'));

        $subscription->refresh();
        $this->assertTrue($subscription->reminder_7day_sent);
        $this->assertNotNull($subscription->reminder_7day_sent_at);

        $this->assertNotNull(
            SystemNotification::on('central')
                ->forRecipient('tenant', $this->businessDetailId())
                ->where('type', 'subscription_expiring')
                ->first()
        );
    }

    public function test_reminder_job_1day_tier_marks_correct_column(): void
    {
        Mail::fake();

        $subscription = $this->createSubscription(['end_date' => now()->addDay()->toDateString()]);

        $job = new SendSubscriptionReminderJob($subscription->id, 1);
        $job->handle($this->makeExpiryServiceWithOwner(), $this->makePaymentService(), new SystemNotificationService);

        $subscription->refresh();
        $this->assertTrue($subscription->reminder_1day_sent);
        $this->assertFalse($subscription->reminder_7day_sent);
    }

    public function test_reminder_job_is_idempotent_when_column_already_set(): void
    {
        Mail::fake();

        $subscription = $this->createSubscription([
            'end_date' => now()->addDays(6)->toDateString(),
            'reminder_7day_sent' => true,
            'reminder_7day_sent_at' => now(),
        ]);

        $job = new SendSubscriptionReminderJob($subscription->id, 7);
        $job->handle($this->makeExpiryServiceWithOwner(), $this->makePaymentService(), new SystemNotificationService);

        Mail::assertNotQueued(SubscriptionReminderMail::class);
    }

    public function test_reminder_job_skips_email_but_still_creates_in_app_notification_when_no_owner_found(): void
    {
        Mail::fake();

        $subscription = $this->createSubscription(['end_date' => now()->addDays(6)->toDateString()]);

        // Real (unmocked) SubscriptionExpiryService — no per-tenant database exists for this
        // fixture, so resolveOwnerEmail() falls back to null via its own try/catch.
        $job = new SendSubscriptionReminderJob($subscription->id, 7);
        $job->handle(new SubscriptionExpiryService, $this->makePaymentService(), new SystemNotificationService);

        Mail::assertNotQueued(SubscriptionReminderMail::class);

        $this->assertNotNull(
            SystemNotification::on('central')
                ->forRecipient('tenant', $this->businessDetailId())
                ->where('type', 'subscription_expiring')
                ->first()
        );

        $subscription->refresh();
        $this->assertTrue($subscription->reminder_7day_sent);
    }

    // =========================================================================
    // SendSubscriptionExpiredNoticeJob
    // =========================================================================

    public function test_expired_notice_job_sends_mail_creates_notification_and_marks_expired_notified_at(): void
    {
        Mail::fake();

        $subscription = $this->createSubscription(['status' => 'expired', 'end_date' => now()->subDay()->toDateString()]);

        $job = new SendSubscriptionExpiredNoticeJob($subscription->id);
        $job->handle($this->makeExpiryServiceWithOwner(), $this->makePaymentService(), new SystemNotificationService);

        Mail::assertQueued(SubscriptionExpiredMail::class, fn (SubscriptionExpiredMail $mail) => $mail->hasTo('owner@notify-test.com'));

        $subscription->refresh();
        $this->assertTrue($subscription->expired_notified);
        $this->assertNotNull($subscription->expired_notified_at);

        $this->assertNotNull(
            SystemNotification::on('central')
                ->forRecipient('tenant', $this->businessDetailId())
                ->where('type', 'subscription_expired')
                ->first()
        );
    }

    public function test_expired_notice_job_is_idempotent_when_already_notified(): void
    {
        Mail::fake();

        $subscription = $this->createSubscription([
            'status' => 'expired',
            'end_date' => now()->subDay()->toDateString(),
            'expired_notified' => true,
            'expired_notified_at' => now(),
        ]);

        $job = new SendSubscriptionExpiredNoticeJob($subscription->id);
        $job->handle($this->makeExpiryServiceWithOwner(), $this->makePaymentService(), new SystemNotificationService);

        Mail::assertNotQueued(SubscriptionExpiredMail::class);
    }
}
