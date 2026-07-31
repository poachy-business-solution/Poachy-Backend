<?php

namespace Tests\Feature\Central\Admin;

use App\Models\BusinessDetail;
use App\Models\BusinessSubscription;
use App\Models\Domain;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Services\Central\Admin\Tenant\TenantService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TenantServiceTest extends TestCase
{
    private const FIXTURE_TENANT_ID = 'tenant-service-test-tenant';

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
        DB::setDefaultConnection('central');
    }

    protected function tearDown(): void
    {
        DB::connection('central')->statement('SET foreign_key_checks = 0');
        BusinessSubscription::on('central')->where('tenant_id', self::FIXTURE_TENANT_ID)->forceDelete();
        BusinessDetail::on('central')->where('tenant_id', self::FIXTURE_TENANT_ID)->forceDelete();
        DB::connection('central')->table('domains')->where('tenant_id', self::FIXTURE_TENANT_ID)->delete();
        DB::connection('central')->table('tenants')->where('id', self::FIXTURE_TENANT_ID)->delete();
        DB::connection('central')->statement('SET foreign_key_checks = 1');

        parent::tearDown();
    }

    private function makeService(): TenantService
    {
        return new TenantService;
    }

    /**
     * Inserts a raw `tenants` row, bypassing Eloquent `Tenant::create()` so the
     * real Stancl tenancy pipeline (database creation + migration + seeding) never
     * fires — see `feedback-testing-db.md`-adjacent convention already used by
     * SubscriptionExpiryServiceTest/CategoryMappingServiceTest for this exact reason.
     */
    private function insertRawTenant(string $id = self::FIXTURE_TENANT_ID, ?array $data = null): void
    {
        DB::connection('central')->table('tenants')->insertOrIgnore([
            'id' => $id,
            'data' => $data ? json_encode($data) : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertRawDomain(string $domain, string $tenantId = self::FIXTURE_TENANT_ID): void
    {
        DB::connection('central')->table('domains')->insert([
            'domain' => $domain,
            'tenant_id' => $tenantId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // =========================================================================
    // createTenant() / deleteTenant() — real Stancl provisioning pipeline.
    //
    // Unlike every other method here, createTenant() IS the tenant-provisioning
    // pipeline (TenantObserver::creating() + TenancyServiceProvider's
    // TenantCreated -> CreateDatabase -> MigrateDatabase -> SeedTenantDatabase
    // JobPipeline, all synchronous per shouldBeQueued(false)) — faking it with a
    // raw insert would test nothing. Confirmed via manual tinker timing this costs
    // ~19s per create+delete cycle, so real-provisioning tests are deliberately
    // kept to the two below rather than one per behavior.
    // =========================================================================

    public function test_create_tenant_provisions_real_database_with_domains_and_metadata(): void
    {
        $domain = 'create-test-'.uniqid().'.poachy.test';
        $additionalDomain = 'create-test-alt-'.uniqid().'.poachy.test';

        $tenant = $this->makeService()->createTenant([
            'domain' => $domain,
            'additional_domains' => [$additionalDomain],
            'tenant_name' => 'Real Provisioning Co',
            'notes' => 'created by TenantServiceTest',
        ]);

        try {
            $this->assertSame('Real Provisioning Co', $tenant->tenant_name);
            $this->assertSame('created by TenantServiceTest', $tenant->notes);
            $this->assertNotEmpty($tenant->mpesa_paybill_account);
            $this->assertTrue($tenant->relationLoaded('domains'));
            $this->assertCount(2, $tenant->domains);
            $this->assertEqualsCanonicalizing(
                [$domain, $additionalDomain],
                $tenant->domains->pluck('domain')->all()
            );

            // Prove the per-tenant database actually exists and was migrated —
            // the whole point of this (expensive) test over a raw-fixture one.
            $tenant->run(function () {
                $this->assertTrue(Schema::connection('tenant')->hasTable('users'));
            });

            $fetched = $this->makeService()->getTenant($tenant->id);
            $this->assertSame($tenant->id, $fetched->id);
            $this->assertTrue($fetched->relationLoaded('businessDetail'));
            $this->assertTrue($fetched->relationLoaded('activeSubscription'));

            $updated = $this->makeService()->updateTenantMetadata($tenant->id, ['notes' => 'updated note']);
            $this->assertSame('updated note', $updated->notes);
            $this->assertSame('Real Provisioning Co', $updated->tenant_name, 'metadata update should merge, not replace');

            $trial = $this->makeService()->startTrialPeriod($tenant->id, now()->addDays(14)->toDateString());
            $this->assertSame('trial', $trial->status);
            $this->assertTrue($trial->is_trial);

            $subscriptions = $this->makeService()->getTenantSubscriptions($tenant->id);
            $this->assertCount(1, $subscriptions);
            $this->assertTrue($subscriptions->first()->relationLoaded('plan'));
        } finally {
            $this->makeService()->deleteTenant($tenant->id);
        }

        $this->assertDatabaseMissing('tenants', ['id' => $tenant->id], 'central');
        $this->assertDatabaseMissing('domains', ['tenant_id' => $tenant->id], 'central');
    }

    public function test_create_tenant_cleans_up_on_domain_creation_failure(): void
    {
        // Pre-seed a *raw* conflicting tenant/domain (no real DB) purely to trip
        // the unique constraint — createTenant()'s own Tenant::create() call still
        // runs for real and provisions a database before the Domain::create() call
        // fails, which is exactly the scenario the try/catch cleanup exists for.
        $this->insertRawTenant('conflicting-tenant');
        $this->insertRawDomain('conflict-'.uniqid().'.poachy.test', 'conflicting-tenant');
        $conflictingDomain = DB::connection('central')->table('domains')
            ->where('tenant_id', 'conflicting-tenant')->value('domain');
        $marker = 'orphan-marker-'.uniqid();

        // The service's catch block deletes the half-created tenant (and its just-
        // provisioned database) *before* re-throwing, so by the time this test
        // regains control the row is already gone — assert via the marker rather
        // than trying to capture the tenant's id mid-flight.
        try {
            $this->makeService()->createTenant(['domain' => $conflictingDomain, 'tenant_name' => $marker]);
            $this->fail('Expected domain uniqueness violation was not thrown.');
        } catch (\Exception $e) {
            // expected
        }

        $orphan = DB::connection('central')->table('tenants')
            ->whereRaw("JSON_EXTRACT(data, '$.tenant_name') = ?", [json_encode($marker)])
            ->exists();
        $this->assertFalse($orphan, 'createTenant() should clean up the tenant row it minted before the domain step failed');

        DB::connection('central')->table('domains')->where('tenant_id', 'conflicting-tenant')->delete();
        DB::connection('central')->table('tenants')->where('id', 'conflicting-tenant')->delete();
    }

    // =========================================================================
    // getTenant()
    // =========================================================================

    public function test_get_tenant_throws_for_unknown_id(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->makeService()->getTenant('does-not-exist');
    }

    // =========================================================================
    // getAllTenants()
    // =========================================================================

    public function test_get_all_tenants_paginates_with_relations(): void
    {
        $this->insertRawTenant();
        $this->insertRawDomain('list-test-'.uniqid().'.poachy.test');

        $result = $this->makeService()->getAllTenants(15);

        $tenant = $result->getCollection()->firstWhere('id', self::FIXTURE_TENANT_ID);
        $this->assertNotNull($tenant);
        $this->assertTrue($tenant->relationLoaded('domains'));
        $this->assertTrue($tenant->relationLoaded('businessDetail'));
    }

    // =========================================================================
    // searchTenants()
    // =========================================================================

    public function test_search_tenants_matches_by_tenant_name_domain_or_business_fields(): void
    {
        $this->insertRawTenant(self::FIXTURE_TENANT_ID, ['tenant_name' => 'Unique Bakery Ltd']);
        $this->insertRawDomain('unique-bakery-'.uniqid().'.poachy.test');

        $byName = $this->makeService()->searchTenants('Unique Bakery');
        $this->assertTrue($byName->getCollection()->contains('id', self::FIXTURE_TENANT_ID));

        $byUnrelatedTerm = $this->makeService()->searchTenants('zzz-no-such-tenant-zzz');
        $this->assertFalse($byUnrelatedTerm->getCollection()->contains('id', self::FIXTURE_TENANT_ID));
    }

    public function test_search_tenants_matches_by_business_email(): void
    {
        $this->insertRawTenant();
        $this->insertRawDomain('biz-email-search-'.uniqid().'.poachy.test');
        BusinessDetail::on('central')->create([
            'tenant_id' => self::FIXTURE_TENANT_ID,
            'business_name' => 'Some Business',
            'business_type_id' => 1,
            'business_category_id' => 1,
            'business_phone' => '0712345678',
            'business_email' => 'findme-'.uniqid().'@example.com',
            'status' => 'pending',
        ]);
        $email = BusinessDetail::on('central')->where('tenant_id', self::FIXTURE_TENANT_ID)->value('business_email');

        $result = $this->makeService()->searchTenants($email);

        $this->assertTrue($result->getCollection()->contains('id', self::FIXTURE_TENANT_ID));
    }

    // =========================================================================
    // addDomain() / updateDomain() / deleteDomain()
    // =========================================================================

    public function test_add_domain_creates_domain_for_tenant(): void
    {
        $this->insertRawTenant();

        $domain = $this->makeService()->addDomain(self::FIXTURE_TENANT_ID, 'added-'.uniqid().'.poachy.test');

        $this->assertDatabaseHas('domains', ['id' => $domain->id, 'tenant_id' => self::FIXTURE_TENANT_ID], 'central');
    }

    public function test_update_domain_changes_domain_name(): void
    {
        $this->insertRawTenant();
        $this->insertRawDomain('before-'.uniqid().'.poachy.test');
        $domainId = Domain::on('central')->where('tenant_id', self::FIXTURE_TENANT_ID)->value('id');
        $newName = 'after-'.uniqid().'.poachy.test';

        $updated = $this->makeService()->updateDomain($domainId, $newName);

        $this->assertSame($newName, $updated->domain);
    }

    public function test_delete_domain_throws_when_it_is_the_last_one(): void
    {
        $this->insertRawTenant();
        $this->insertRawDomain('only-'.uniqid().'.poachy.test');
        $domainId = Domain::on('central')->where('tenant_id', self::FIXTURE_TENANT_ID)->value('id');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Cannot delete the last domain');

        $this->makeService()->deleteDomain($domainId);
    }

    public function test_delete_domain_succeeds_when_another_domain_remains(): void
    {
        $this->insertRawTenant();
        $this->insertRawDomain('first-'.uniqid().'.poachy.test');
        $this->insertRawDomain('second-'.uniqid().'.poachy.test');
        $domainToDelete = Domain::on('central')->where('tenant_id', self::FIXTURE_TENANT_ID)->first();

        $this->makeService()->deleteDomain($domainToDelete->id);

        $this->assertDatabaseMissing('domains', ['id' => $domainToDelete->id], 'central');
    }

    // =========================================================================
    // updateTenantMetadata()
    // =========================================================================

    public function test_update_tenant_metadata_merges_into_existing_data(): void
    {
        $this->insertRawTenant(self::FIXTURE_TENANT_ID, ['tenant_name' => 'Original Name', 'notes' => 'keep me']);

        $updated = $this->makeService()->updateTenantMetadata(self::FIXTURE_TENANT_ID, ['tenant_name' => 'New Name']);

        $this->assertSame('New Name', $updated->tenant_name);
        $this->assertSame('keep me', $updated->notes, 'updating one metadata field should not clobber the other');
    }

    // =========================================================================
    // startTrialPeriod()
    // =========================================================================

    public function test_start_trial_period_throws_for_unknown_tenant(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Tenant not found');

        $this->makeService()->startTrialPeriod('does-not-exist', now()->addDays(14)->toDateString());
    }

    public function test_start_trial_period_throws_when_active_trial_already_exists(): void
    {
        $this->insertRawTenant();
        $freePlan = SubscriptionPlan::on('central')->where('slug', 'free')->where('is_active', true)->firstOrFail();
        BusinessSubscription::on('central')->create([
            'tenant_id' => self::FIXTURE_TENANT_ID,
            'subscription_plan_id' => $freePlan->id,
            'start_date' => now()->toDateString(),
            'amount_paid' => 0,
            'status' => 'trial',
            'is_trial' => true,
            'trial_ends_at' => now()->addDays(5)->toDateString(),
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('already has an active trial');

        $this->makeService()->startTrialPeriod(self::FIXTURE_TENANT_ID, now()->addDays(14)->toDateString());
    }

    // =========================================================================
    // getTenantSubscriptions()
    // =========================================================================

    public function test_get_tenant_subscriptions_throws_for_unknown_tenant(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Tenant not found');

        $this->makeService()->getTenantSubscriptions('does-not-exist');
    }

    public function test_get_tenant_subscriptions_orders_newest_first_with_plan_loaded(): void
    {
        $this->insertRawTenant();
        $plan = SubscriptionPlan::on('central')->where('slug', 'free')->where('is_active', true)->firstOrFail();
        $older = BusinessSubscription::on('central')->create([
            'tenant_id' => self::FIXTURE_TENANT_ID,
            'subscription_plan_id' => $plan->id,
            'start_date' => now()->subDays(60)->toDateString(),
            'end_date' => now()->subDays(30)->toDateString(),
            'amount_paid' => 0,
            'status' => 'expired',
        ]);
        $newer = BusinessSubscription::on('central')->create([
            'tenant_id' => self::FIXTURE_TENANT_ID,
            'subscription_plan_id' => $plan->id,
            'start_date' => now()->toDateString(),
            'amount_paid' => 0,
            'status' => 'active',
        ]);
        // created_at isn't mass-assignable (standard Eloquent behaviour), so force
        // distinct timestamps directly to make the ordering assertion meaningful.
        DB::connection('central')->table('business_subscriptions')
            ->where('id', $older->id)->update(['created_at' => now()->subDays(60)]);
        DB::connection('central')->table('business_subscriptions')
            ->where('id', $newer->id)->update(['created_at' => now()]);

        $subscriptions = $this->makeService()->getTenantSubscriptions(self::FIXTURE_TENANT_ID);

        $this->assertSame($newer->id, $subscriptions->first()->id);
        $this->assertSame($older->id, $subscriptions->last()->id);
        $this->assertTrue($subscriptions->first()->relationLoaded('plan'));
    }
}
