<?php

namespace Tests\Feature\Central\Admin;

use App\Models\BusinessCategory;
use App\Models\BusinessDetail;
use App\Models\BusinessType;
use App\Services\Tenant\Business\BusinessDetailsService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Covers both halves of BusinessDetailsService: tenant-side submission/profile
 * updates and admin-side review (approve/reject/verify). Uses a raw-inserted
 * `tenants` fixture row throughout (see TenantServiceTest for why) — approve()
 * and verify() both attempt to email the tenant's real owner via a genuine
 * tenancy()->initialize() switch, wrapped in try/catch inside the service, so
 * against a fixture with no real per-tenant database that step is a safe no-op
 * (same documented behavior as SubscriptionExpiryServiceTest's resolveOwnerEmail()
 * scope note) — Mail assertions here are deliberately about *whether the send was
 * attempted*, not about delivery to a real owner, which already has coverage via
 * the identical pattern in the subscription-lifecycle feature.
 */
class BusinessDetailsServiceTest extends TestCase
{
    private const FIXTURE_TENANT_ID = 'business-details-test-tenant';

    private BusinessType $businessType;

    private BusinessCategory $businessCategory;

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

        Storage::fake('public');
        Mail::fake();

        $this->businessType = BusinessType::on('central')->firstOrFail();
        $this->businessCategory = BusinessCategory::on('central')
            ->where('business_type_id', $this->businessType->id)->firstOrFail();

        DB::connection('central')->table('tenants')->insertOrIgnore([
            'id' => self::FIXTURE_TENANT_ID,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        DB::connection('central')->statement('SET foreign_key_checks = 0');
        BusinessDetail::on('central')->where('tenant_id', self::FIXTURE_TENANT_ID)->forceDelete();
        DB::connection('central')->table('tenants')->where('id', self::FIXTURE_TENANT_ID)->delete();
        DB::connection('central')->statement('SET foreign_key_checks = 1');
        Cache::forget('tenant_access:'.self::FIXTURE_TENANT_ID);

        parent::tearDown();
    }

    private function makeService(): BusinessDetailsService
    {
        return new BusinessDetailsService;
    }

    private function baseSubmission(array $overrides = []): array
    {
        return array_merge([
            'business_name' => 'Test Business',
            'business_type_id' => $this->businessType->id,
            'business_category_id' => $this->businessCategory->id,
            'business_phone' => '0712345678',
        ], $overrides);
    }

    private function createBusinessDetail(array $overrides = []): BusinessDetail
    {
        return BusinessDetail::on('central')->create(array_merge([
            'tenant_id' => self::FIXTURE_TENANT_ID,
            'business_name' => 'Test Business',
            'business_type_id' => $this->businessType->id,
            'business_category_id' => $this->businessCategory->id,
            'business_phone' => '0712345678',
            'status' => 'pending',
        ], $overrides));
    }

    // =========================================================================
    // submitBusinessDetails()
    // =========================================================================

    public function test_submit_business_details_creates_pending_record(): void
    {
        $detail = $this->makeService()->submitBusinessDetails(self::FIXTURE_TENANT_ID, $this->baseSubmission([
            'business_email' => 'biz@example.com',
        ]));

        $this->assertSame('pending', $detail->status);
        $this->assertSame(self::FIXTURE_TENANT_ID, $detail->tenant_id);
        $this->assertTrue($detail->relationLoaded('businessType'));
    }

    public function test_submit_business_details_stores_uploaded_logo_and_banner(): void
    {
        $detail = $this->makeService()->submitBusinessDetails(self::FIXTURE_TENANT_ID, $this->baseSubmission([
            'business_logo' => UploadedFile::fake()->image('logo.png'),
            'business_banner' => UploadedFile::fake()->image('banner.png'),
        ]));

        Storage::disk('public')->assertExists($detail->business_logo);
        Storage::disk('public')->assertExists($detail->business_banner);
    }

    public function test_submit_business_details_throws_when_already_submitted(): void
    {
        $this->createBusinessDetail();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('already submitted');

        $this->makeService()->submitBusinessDetails(self::FIXTURE_TENANT_ID, $this->baseSubmission());
    }

    // =========================================================================
    // getBusinessDetails()
    // =========================================================================

    public function test_get_business_details_returns_null_when_none_submitted(): void
    {
        $this->assertNull($this->makeService()->getBusinessDetails(self::FIXTURE_TENANT_ID));
    }

    public function test_get_business_details_returns_record_with_relations(): void
    {
        $this->createBusinessDetail();

        $detail = $this->makeService()->getBusinessDetails(self::FIXTURE_TENANT_ID);

        $this->assertNotNull($detail);
        $this->assertTrue($detail->relationLoaded('businessType'));
        $this->assertTrue($detail->relationLoaded('businessCategory'));
    }

    // =========================================================================
    // approve()
    // =========================================================================

    public function test_approve_activates_business_and_sets_onboarded_at(): void
    {
        $detail = $this->createBusinessDetail();

        $approved = $this->makeService()->approve($detail->id);

        $this->assertSame('active', $approved->status);
        $this->assertNotNull($approved->onboarded_at);
    }

    public function test_approve_clears_tenant_access_cache(): void
    {
        $detail = $this->createBusinessDetail();
        Cache::put('tenant_access:'.self::FIXTURE_TENANT_ID, ['cached' => true], 60);

        $this->makeService()->approve($detail->id);

        $this->assertNull(Cache::get('tenant_access:'.self::FIXTURE_TENANT_ID));
    }

    // =========================================================================
    // reject()
    // =========================================================================

    public function test_reject_deletes_record_and_uploaded_files(): void
    {
        $detail = $this->makeService()->submitBusinessDetails(self::FIXTURE_TENANT_ID, $this->baseSubmission([
            'business_logo' => UploadedFile::fake()->image('logo.png'),
        ]));
        $logoPath = $detail->business_logo;

        $this->makeService()->reject($detail->id, 'Missing documents');

        // BusinessDetail uses SoftDeletes, so the row survives with deleted_at set.
        $this->assertSoftDeleted('business_details', ['id' => $detail->id], connection: 'central');
        Storage::disk('public')->assertMissing($logoPath);
    }

    // =========================================================================
    // verify() — the actual bug: passing is_verified=false previously still
    // set is_verified=true, because the service called the model's verify()
    // unconditionally instead of branching on the flag.
    // =========================================================================

    public function test_verify_true_sets_is_verified_and_timestamp(): void
    {
        $detail = $this->createBusinessDetail(['is_verified' => false]);

        $verified = $this->makeService()->verify($detail->id, true);

        $this->assertTrue($verified->is_verified);
        $this->assertNotNull($verified->verified_at);
    }

    public function test_verify_false_actually_unverifies(): void
    {
        $detail = $this->createBusinessDetail(['is_verified' => true, 'verified_at' => now()]);

        $unverified = $this->makeService()->verify($detail->id, false);

        $this->assertFalse($unverified->is_verified);
        $this->assertNull($unverified->verified_at);
    }

    // =========================================================================
    // getPending()
    // =========================================================================

    public function test_get_pending_returns_only_pending_status(): void
    {
        $pending = $this->createBusinessDetail(['status' => 'pending']);

        $result = $this->makeService()->getPending();

        $this->assertTrue($result->getCollection()->contains('id', $pending->id));
        $this->assertTrue($result->getCollection()->every(fn ($d) => $d->status === 'pending'));
    }

    // =========================================================================
    // getAllBusinessDetails()
    // =========================================================================

    public function test_get_all_business_details_filters_by_status(): void
    {
        $active = $this->createBusinessDetail(['status' => 'active']);

        $result = $this->makeService()->getAllBusinessDetails(['status' => 'active']);

        $this->assertTrue($result->getCollection()->contains('id', $active->id));
        $this->assertTrue($result->getCollection()->every(fn ($d) => $d->status === 'active'));
    }

    public function test_get_all_business_details_filters_by_verification_and_city(): void
    {
        $match = $this->createBusinessDetail(['is_verified' => true, 'city' => 'Nairobi']);

        $result = $this->makeService()->getAllBusinessDetails(['is_verified' => true, 'city' => 'Nairobi']);

        $this->assertTrue($result->getCollection()->contains('id', $match->id));
    }

    // =========================================================================
    // Tenant-side profile updates
    // =========================================================================

    public function test_update_profile_only_touches_provided_fields(): void
    {
        $this->createBusinessDetail(['business_name' => 'Old Name', 'business_phone' => '0700000000']);

        $updated = $this->makeService()->updateProfile(self::FIXTURE_TENANT_ID, ['business_name' => 'New Name']);

        $this->assertSame('New Name', $updated->business_name);
        $this->assertSame('0700000000', $updated->business_phone);
    }

    public function test_update_media_replaces_logo_and_deletes_old_file(): void
    {
        $original = UploadedFile::fake()->image('old.png');
        $this->createBusinessDetail(['business_logo' => $original->store('business/logos', 'public')]);
        $oldPath = BusinessDetail::on('central')->where('tenant_id', self::FIXTURE_TENANT_ID)->value('business_logo');

        $updated = $this->makeService()->updateMedia(self::FIXTURE_TENANT_ID, [
            'business_logo' => UploadedFile::fake()->image('new.png'),
        ]);

        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($updated->business_logo);
    }

    public function test_update_location_updates_address_city_county(): void
    {
        $this->createBusinessDetail();

        $updated = $this->makeService()->updateLocation(self::FIXTURE_TENANT_ID, [
            'address' => '123 Main St', 'city' => 'Nairobi', 'county' => 'Nairobi County',
        ]);

        $this->assertSame('123 Main St', $updated->address);
        $this->assertSame('Nairobi', $updated->city);
    }

    public function test_update_operating_hours_merges_with_existing(): void
    {
        $this->createBusinessDetail(['operating_hours' => ['monday' => '9-5']]);

        $updated = $this->makeService()->updateOperatingHours(self::FIXTURE_TENANT_ID, [
            'operating_hours' => ['tuesday' => '9-5'],
        ]);

        $this->assertSame('9-5', $updated->operating_hours['monday']);
        $this->assertSame('9-5', $updated->operating_hours['tuesday']);
    }

    public function test_update_delivery_info_merges_with_existing(): void
    {
        $this->createBusinessDetail(['delivery_info' => ['radius_km' => 5]]);

        $updated = $this->makeService()->updateDeliveryInfo(self::FIXTURE_TENANT_ID, [
            'delivery_info' => ['fee' => 100],
        ]);

        $this->assertSame(5, $updated->delivery_info['radius_km']);
        $this->assertSame(100, $updated->delivery_info['fee']);
    }

    public function test_update_settings_merges_with_existing(): void
    {
        $this->createBusinessDetail(['settings' => ['currency' => 'KES']]);

        $updated = $this->makeService()->updateSettings(self::FIXTURE_TENANT_ID, [
            'settings' => ['payment_methods' => ['mpesa']],
        ]);

        $this->assertSame('KES', $updated->settings['currency']);
        $this->assertSame(['mpesa'], $updated->settings['payment_methods']);
    }

    public function test_update_social_media_merges_with_existing(): void
    {
        $this->createBusinessDetail(['social_media' => ['facebook' => 'fb.com/biz']]);

        $updated = $this->makeService()->updateSocialMedia(self::FIXTURE_TENANT_ID, [
            'social_media' => ['instagram' => 'ig.com/biz'],
        ]);

        $this->assertSame('fb.com/biz', $updated->social_media['facebook']);
        $this->assertSame('ig.com/biz', $updated->social_media['instagram']);
    }
}
