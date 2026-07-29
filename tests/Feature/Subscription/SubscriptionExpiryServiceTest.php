<?php

namespace Tests\Feature\Subscription;

use App\Models\BusinessSubscription;
use App\Models\SubscriptionPlan;
use App\Services\Central\Subscription\SubscriptionExpiryService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SubscriptionExpiryServiceTest extends TestCase
{
    private SubscriptionPlan $plan;

    private SubscriptionExpiryService $service;

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

        $this->service = new SubscriptionExpiryService;

        $this->plan = SubscriptionPlan::on('central')->create([
            'name' => 'Expiry Test Plan',
            'slug' => 'expiry-test-plan-'.uniqid(),
            'price' => 1000.00,
            'billing_cycle_days' => 30,
            'is_active' => true,
            'is_featured' => false,
        ]);

        DB::connection('central')->table('tenants')->insertOrIgnore([
            'id' => 'expiry-test-tenant',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        DB::connection('central')->statement('SET foreign_key_checks = 0');
        BusinessSubscription::on('central')->where('tenant_id', 'expiry-test-tenant')->forceDelete();
        DB::connection('central')->table('tenants')->where('id', 'expiry-test-tenant')->delete();
        SubscriptionPlan::on('central')->where('id', $this->plan->id)->forceDelete();
        DB::connection('central')->statement('SET foreign_key_checks = 1');
        parent::tearDown();
    }

    private function createSubscription(array $overrides = []): BusinessSubscription
    {
        return BusinessSubscription::on('central')->create(array_merge([
            'tenant_id' => 'expiry-test-tenant',
            'subscription_plan_id' => $this->plan->id,
            'start_date' => now()->subDays(30)->toDateString(),
            'end_date' => now()->addDays(10)->toDateString(),
            'amount_paid' => 1000.00,
            'status' => 'active',
        ], $overrides));
    }

    public function test_get_active_expired_subscription_ids_includes_only_active_rows_past_end_date(): void
    {
        $expired = $this->createSubscription(['end_date' => now()->subDay()->toDateString()]);
        $stillActive = $this->createSubscription(['end_date' => now()->addDays(5)->toDateString()]);
        $alreadyExpiredStatus = $this->createSubscription(['end_date' => now()->subDays(3)->toDateString(), 'status' => 'expired']);

        $ids = $this->service->getActiveExpiredSubscriptionIds();

        $this->assertTrue($ids->contains($expired->id));
        $this->assertFalse($ids->contains($stillActive->id));
        $this->assertFalse($ids->contains($alreadyExpiredStatus->id));
    }

    public function test_lifetime_subscription_with_null_end_date_is_never_returned_as_expired(): void
    {
        $lifetime = $this->createSubscription(['end_date' => null]);

        $ids = $this->service->getActiveExpiredSubscriptionIds();

        $this->assertFalse($ids->contains($lifetime->id));
    }

    public function test_get_subscription_ids_due_for_7day_reminder_matches_window_and_excludes_already_sent(): void
    {
        $due = $this->createSubscription(['end_date' => now()->addDays(6)->toDateString()]);
        $tooFar = $this->createSubscription(['end_date' => now()->addDays(10)->toDateString()]);
        $alreadySent = $this->createSubscription(['end_date' => now()->addDays(6)->toDateString(), 'reminder_7day_sent' => true]);

        $ids = $this->service->getSubscriptionIdsDueFor7DayReminder();

        $this->assertTrue($ids->contains($due->id));
        $this->assertFalse($ids->contains($tooFar->id));
        $this->assertFalse($ids->contains($alreadySent->id));
    }

    public function test_get_subscription_ids_due_for_1day_reminder_matches_window_and_excludes_already_sent(): void
    {
        $due = $this->createSubscription(['end_date' => now()->addDay()->toDateString()]);
        $tooFar = $this->createSubscription(['end_date' => now()->addDays(3)->toDateString()]);
        $alreadySent = $this->createSubscription(['end_date' => now()->addDay()->toDateString(), 'reminder_1day_sent' => true]);

        $ids = $this->service->getSubscriptionIdsDueFor1DayReminder();

        $this->assertTrue($ids->contains($due->id));
        $this->assertFalse($ids->contains($tooFar->id));
        $this->assertFalse($ids->contains($alreadySent->id));
    }

    public function test_get_expired_subscription_ids_needing_notification_excludes_already_notified(): void
    {
        $needsNotice = $this->createSubscription(['status' => 'expired', 'end_date' => now()->subDay()->toDateString()]);
        $alreadyNotified = $this->createSubscription([
            'status' => 'expired',
            'end_date' => now()->subDay()->toDateString(),
            'expired_notified' => true,
        ]);
        $stillActive = $this->createSubscription(['end_date' => now()->addDays(5)->toDateString()]);

        $ids = $this->service->getExpiredSubscriptionIdsNeedingNotification();

        $this->assertTrue($ids->contains($needsNotice->id));
        $this->assertFalse($ids->contains($alreadyNotified->id));
        $this->assertFalse($ids->contains($stillActive->id));
    }

    public function test_resolve_owner_email_returns_null_when_tenant_not_found(): void
    {
        $this->assertNull($this->service->resolveOwnerEmail('nonexistent-tenant'));
    }

    public function test_resolve_owner_email_returns_null_gracefully_when_tenancy_switch_fails(): void
    {
        // 'expiry-test-tenant' has a central `tenants` row but no real per-tenant database —
        // the tenancy switch fails, and resolveOwnerEmail() must catch that rather than throw.
        $this->assertNull($this->service->resolveOwnerEmail('expiry-test-tenant'));
    }
}
