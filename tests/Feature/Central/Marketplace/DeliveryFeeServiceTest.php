<?php

namespace Tests\Feature\Central\Marketplace;

use App\Enums\Central\DeliveryMethod;
use App\Models\BusinessCategory;
use App\Models\BusinessDetail;
use App\Models\BusinessType;
use App\Models\CustomerAddress;
use App\Models\MarketplaceCustomer;
use App\Models\TenantDeliveryZone;
use App\Models\User;
use App\Services\Central\Marketplace\DeliveryFeeService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DeliveryFeeServiceTest extends TestCase
{
    private string $tenantId;

    private MarketplaceCustomer $customer;

    private array $userIds = [];

    private array $customerIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.connections.central.host', env('CENTRAL_DB_HOST', '127.0.0.1'));
        Config::set('database.connections.central.port', env('CENTRAL_DB_PORT', '3306'));
        Config::set('database.connections.central.database', env('CENTRAL_DB_DATABASE', 'poachy'));
        Config::set('database.connections.central.username', env('CENTRAL_DB_USERNAME', 'root'));
        Config::set('database.connections.central.password', env('CENTRAL_DB_PASSWORD', ''));
        DB::purge('central');
        DB::setDefaultConnection('central');
        DB::connection('central')->statement('SET foreign_key_checks = 0');

        $this->tenantId = 'delivery-fee-test-'.uniqid();
        DB::connection('central')->table('tenants')->insertOrIgnore([
            'id' => $this->tenantId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->customer = $this->createCustomer();
    }

    protected function tearDown(): void
    {
        DB::connection('central')->statement('SET foreign_key_checks = 0');
        TenantDeliveryZone::on('central')->where('tenant_id', $this->tenantId)->delete();
        BusinessDetail::on('central')->where('tenant_id', $this->tenantId)->forceDelete();
        CustomerAddress::on('central')->whereIn('customer_id', $this->customerIds)->delete();
        MarketplaceCustomer::on('central')->whereIn('id', $this->customerIds)->forceDelete();
        User::on('central')->whereIn('id', $this->userIds)->forceDelete();
        DB::connection('central')->table('tenants')->where('id', $this->tenantId)->delete();
        DB::connection('central')->statement('SET foreign_key_checks = 1');
        Cache::forget("tenant:{$this->tenantId}:delivery_zones");

        parent::tearDown();
    }

    private function makeService(): DeliveryFeeService
    {
        return new DeliveryFeeService;
    }

    private function createCustomer(): MarketplaceCustomer
    {
        $user = User::on('central')->create([
            'name' => 'Delivery Fee Customer',
            'email' => 'delivery-fee-customer-'.uniqid().'@test.com',
            'password' => bcrypt('password'),
            'user_type' => 'customer',
        ]);
        $this->userIds[] = $user->id;

        $customer = MarketplaceCustomer::on('central')->create([
            'user_id' => $user->id,
            'customer_number' => 'MKT-'.uniqid(),
            'phone' => '0712'.rand(100000, 999999),
            'phone_verified' => true,
        ]);
        $this->customerIds[] = $customer->id;

        return $customer;
    }

    private function createAddress(array $overrides = []): CustomerAddress
    {
        return CustomerAddress::on('central')->create(array_merge([
            'customer_id' => $this->customer->id,
            'address_type' => 'home',
            'recipient_name' => 'Test Recipient',
            'recipient_phone' => '0712345678',
            'address_line' => '123 Test Street',
            'city' => 'Nairobi',
            'county' => 'Nairobi',
            'is_default' => true,
            'is_active' => true,
        ], $overrides));
    }

    private function enableZones(): void
    {
        BusinessDetail::on('central')->updateOrCreate(
            ['tenant_id' => $this->tenantId],
            [
                'business_name' => 'Delivery Fee Test Business',
                'business_type_id' => BusinessType::on('central')->firstOrFail()->id,
                'business_category_id' => BusinessCategory::on('central')->firstOrFail()->id,
                'business_phone' => '0712345678',
                'status' => 'active',
                'delivery_info' => ['zones_enabled' => true],
            ]
        );
    }

    private function createZone(array $overrides = []): TenantDeliveryZone
    {
        return TenantDeliveryZone::on('central')->create(array_merge([
            'tenant_id' => $this->tenantId,
            'zone_name' => 'Zone '.uniqid(),
            'zone_type' => 'city',
            'cities' => ['nairobi'],
            'standard_fee' => 200,
            'supported_methods' => ['standard'],
            'priority' => 100,
            'is_active' => true,
        ], $overrides));
    }

    // =========================================================================
    // calculateDeliveryFee() — zones disabled / no match / unsupported method
    // =========================================================================

    public function test_calculate_fee_returns_zero_when_zones_not_enabled(): void
    {
        // No BusinessDetail / delivery_info set at all — zones_enabled defaults to false.
        $address = $this->createAddress();

        $result = $this->makeService()->calculateDeliveryFee($this->tenantId, $address, DeliveryMethod::Standard, 1000);

        $this->assertSame(0.0, $result['fee']);
        $this->assertNull($result['zone_id']);
    }

    public function test_calculate_fee_throws_when_no_zone_matches_address(): void
    {
        $this->enableZones();
        $this->createZone(['cities' => ['mombasa']]);
        $address = $this->createAddress(['city' => 'Nairobi']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('not available to your address');

        $this->makeService()->calculateDeliveryFee($this->tenantId, $address, DeliveryMethod::Standard, 1000);
    }

    public function test_calculate_fee_throws_when_method_not_supported_by_matched_zone(): void
    {
        $this->enableZones();
        $this->createZone(['supported_methods' => ['standard']]);
        $address = $this->createAddress();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('not available for your area');

        $this->makeService()->calculateDeliveryFee($this->tenantId, $address, DeliveryMethod::Express, 1000);
    }

    // =========================================================================
    // calculateDeliveryFee() — fee + free-delivery threshold
    // =========================================================================

    public function test_calculate_fee_returns_zone_fee_when_below_threshold(): void
    {
        $this->enableZones();
        $this->createZone(['standard_fee' => 300, 'free_delivery_threshold' => 2000]);
        $address = $this->createAddress();

        $result = $this->makeService()->calculateDeliveryFee($this->tenantId, $address, DeliveryMethod::Standard, 1000);

        $this->assertEquals(300, $result['fee']);
        $this->assertFalse($result['free_delivery_applied']);
    }

    public function test_calculate_fee_waives_fee_at_or_above_threshold(): void
    {
        $this->enableZones();
        $this->createZone(['standard_fee' => 300, 'free_delivery_threshold' => 2000]);
        $address = $this->createAddress();

        $result = $this->makeService()->calculateDeliveryFee($this->tenantId, $address, DeliveryMethod::Standard, 2000);

        $this->assertSame(0.0, $result['fee']);
        $this->assertTrue($result['free_delivery_applied']);
    }

    public function test_calculate_fee_charges_full_fee_when_no_threshold_set(): void
    {
        $this->enableZones();
        $this->createZone(['standard_fee' => 300, 'free_delivery_threshold' => null]);
        $address = $this->createAddress();

        $result = $this->makeService()->calculateDeliveryFee($this->tenantId, $address, DeliveryMethod::Standard, 999999);

        $this->assertEquals(300, $result['fee']);
    }

    // =========================================================================
    // Zone matching — city / county / postal_code
    // =========================================================================

    public function test_matches_city_case_insensitively(): void
    {
        $this->enableZones();
        $this->createZone(['cities' => ['Nairobi']]);
        $address = $this->createAddress(['city' => 'NAIROBI']);

        $result = $this->makeService()->calculateDeliveryFee($this->tenantId, $address, DeliveryMethod::Standard, 100);

        $this->assertNotNull($result['zone_id']);
    }

    public function test_matches_county(): void
    {
        $this->enableZones();
        $this->createZone(['zone_type' => 'county', 'cities' => null, 'counties' => ['kiambu']]);
        $address = $this->createAddress(['city' => 'Ruiru', 'county' => 'Kiambu']);

        $result = $this->makeService()->calculateDeliveryFee($this->tenantId, $address, DeliveryMethod::Standard, 100);

        $this->assertNotNull($result['zone_id']);
    }

    public function test_matches_postal_code(): void
    {
        $this->enableZones();
        $this->createZone(['zone_type' => 'postal_code', 'cities' => null, 'postal_codes' => ['00100']]);
        $address = $this->createAddress(['postal_code' => '00100']);

        $result = $this->makeService()->calculateDeliveryFee($this->tenantId, $address, DeliveryMethod::Standard, 100);

        $this->assertNotNull($result['zone_id']);
    }

    public function test_does_not_match_different_postal_code(): void
    {
        $this->enableZones();
        $this->createZone(['zone_type' => 'postal_code', 'cities' => null, 'postal_codes' => ['00100']]);
        $address = $this->createAddress(['postal_code' => '00200']);

        $this->expectException(\RuntimeException::class);

        $this->makeService()->calculateDeliveryFee($this->tenantId, $address, DeliveryMethod::Standard, 100);
    }

    // =========================================================================
    // Zone matching — radius
    // =========================================================================

    public function test_matches_radius_within_distance(): void
    {
        $this->enableZones();
        // Nairobi CBD coordinates, 10km radius.
        $this->createZone([
            'zone_type' => 'radius', 'cities' => null,
            'latitude' => -1.286389, 'longitude' => 36.817223, 'radius_km' => 10,
        ]);
        // ~2km away.
        $address = $this->createAddress(['latitude' => -1.300000, 'longitude' => 36.820000]);

        $result = $this->makeService()->calculateDeliveryFee($this->tenantId, $address, DeliveryMethod::Standard, 100);

        $this->assertNotNull($result['zone_id']);
    }

    public function test_does_not_match_radius_beyond_distance(): void
    {
        $this->enableZones();
        $this->createZone([
            'zone_type' => 'radius', 'cities' => null,
            'latitude' => -1.286389, 'longitude' => 36.817223, 'radius_km' => 5,
        ]);
        // Mombasa — roughly 480km from Nairobi, well beyond a 5km radius.
        $address = $this->createAddress(['latitude' => -4.043477, 'longitude' => 39.658871]);

        $this->expectException(\RuntimeException::class);

        $this->makeService()->calculateDeliveryFee($this->tenantId, $address, DeliveryMethod::Standard, 100);
    }

    public function test_radius_zone_falls_back_to_city_match_when_address_has_no_coordinates(): void
    {
        $this->enableZones();
        $this->createZone([
            'zone_type' => 'radius', 'cities' => ['nairobi'],
            'latitude' => -1.286389, 'longitude' => 36.817223, 'radius_km' => 5,
        ]);
        $address = $this->createAddress(['city' => 'Nairobi', 'latitude' => null, 'longitude' => null]);

        $result = $this->makeService()->calculateDeliveryFee($this->tenantId, $address, DeliveryMethod::Standard, 100);

        $this->assertNotNull($result['zone_id']);
    }

    // =========================================================================
    // Zone priority
    // =========================================================================

    public function test_lower_priority_number_wins_when_multiple_zones_match(): void
    {
        $this->enableZones();
        $this->createZone(['zone_name' => 'General Nairobi', 'cities' => ['nairobi'], 'standard_fee' => 300, 'priority' => 100]);
        $specific = $this->createZone(['zone_name' => 'Priority Nairobi', 'cities' => ['nairobi'], 'standard_fee' => 150, 'priority' => 1]);
        $address = $this->createAddress();

        $result = $this->makeService()->calculateDeliveryFee($this->tenantId, $address, DeliveryMethod::Standard, 100);

        $this->assertSame($specific->id, $result['zone_id']);
        $this->assertEquals(150, $result['fee']);
    }

    // =========================================================================
    // getAvailableMethodsForAddress()
    // =========================================================================

    public function test_get_available_methods_only_includes_supported_methods(): void
    {
        $this->enableZones();
        $this->createZone(['supported_methods' => ['standard', 'express']]);
        $address = $this->createAddress();

        $methods = $this->makeService()->getAvailableMethodsForAddress($this->tenantId, $address);

        $this->assertCount(2, $methods);
        $this->assertEqualsCanonicalizing(['standard', 'express'], array_column($methods, 'method'));
    }

    public function test_get_available_methods_returns_empty_when_no_zone_matches(): void
    {
        $this->enableZones();
        $this->createZone(['cities' => ['mombasa']]);
        $address = $this->createAddress(['city' => 'Nairobi']);

        $methods = $this->makeService()->getAvailableMethodsForAddress($this->tenantId, $address);

        $this->assertEmpty($methods);
    }

    public function test_get_available_methods_returns_all_methods_free_when_zones_disabled(): void
    {
        $address = $this->createAddress();

        $methods = $this->makeService()->getAvailableMethodsForAddress($this->tenantId, $address);

        $this->assertCount(3, $methods);
        $this->assertTrue(collect($methods)->every(fn ($m) => $m['fee'] === 0.0));
    }

    // =========================================================================
    // flushZoneCache()
    // =========================================================================

    public function test_zones_are_cached_until_flushed(): void
    {
        $this->enableZones();
        $zone = $this->createZone(['standard_fee' => 200]);
        $address = $this->createAddress();

        $first = $this->makeService()->calculateDeliveryFee($this->tenantId, $address, DeliveryMethod::Standard, 100);
        $this->assertEquals(200, $first['fee']);

        // Bypass the model/observer to avoid re-triggering the cache write, simulating
        // an out-of-band change while the old zone list is still cached.
        DB::connection('central')->table('tenant_delivery_zones')->where('id', $zone->id)->update(['standard_fee' => 999]);

        $stillCached = $this->makeService()->calculateDeliveryFee($this->tenantId, $address, DeliveryMethod::Standard, 100);
        $this->assertEquals(200, $stillCached['fee'], 'stale cached zone data should still be served');

        $this->makeService()->flushZoneCache($this->tenantId);

        $fresh = $this->makeService()->calculateDeliveryFee($this->tenantId, $address, DeliveryMethod::Standard, 100);
        $this->assertEquals(999, $fresh['fee']);
    }
}
