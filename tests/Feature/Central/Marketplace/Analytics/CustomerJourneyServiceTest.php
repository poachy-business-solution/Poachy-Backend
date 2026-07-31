<?php

namespace Tests\Feature\Central\Marketplace\Analytics;

use App\Models\BusinessCategory;
use App\Models\BusinessDetail;
use App\Models\BusinessType;
use App\Models\CustomerJourneyEvent;
use App\Models\MarketplaceProduct;
use App\Services\Central\Marketplace\Analytics\CustomerJourneyService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CustomerJourneyServiceTest extends TestCase
{
    private string $tenantId;

    private MarketplaceProduct $product;

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
        DB::connection('central')->statement('SET foreign_key_checks = 0');

        $this->tenantId = 'journey-test-'.uniqid();
        DB::connection('central')->table('tenants')->insertOrIgnore([
            'id' => $this->tenantId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->product = MarketplaceProduct::on('central')->create([
            'tenant_id' => $this->tenantId,
            'name' => 'Journey Product',
            'slug' => 'journey-product-'.uniqid(),
            'sku' => 'SKU-'.uniqid(),
            'online_price' => 500,
            'base_uom_code' => 'pcs',
            'base_uom_name' => 'Piece',
            'tax_rate' => 0,
            'available_quantity' => 10,
            'stock_status' => 'in_stock',
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        DB::connection('central')->statement('SET foreign_key_checks = 0');
        // Every event created by createEvent() shares this literal session_id
        // (only session_uuid varies per test) — clean up by it directly rather
        // than by tenant_id/product_id, which most events here never set.
        CustomerJourneyEvent::on('central')->where('session_id', 'journey-sess')->delete();
        BusinessDetail::on('central')->where('tenant_id', $this->tenantId)->forceDelete();
        MarketplaceProduct::on('central')->where('id', $this->product->id)->forceDelete();
        DB::connection('central')->table('tenants')->where('id', $this->tenantId)->delete();
        DB::connection('central')->statement('SET foreign_key_checks = 1');

        parent::tearDown();
    }

    private function makeService(): CustomerJourneyService
    {
        return new CustomerJourneyService;
    }

    private function createEvent(array $overrides = []): CustomerJourneyEvent
    {
        return CustomerJourneyEvent::on('central')->create(array_merge([
            'session_id' => 'journey-sess',
            'session_uuid' => 'journey-sess',
            'event_type' => 'page_view',
            'sequence_in_session' => 1,
            'event_timestamp' => now(),
        ], $overrides));
    }

    // =========================================================================
    // getSessionJourney()
    // =========================================================================

    public function test_get_session_journey_orders_by_sequence(): void
    {
        $sessionUuid = 'journey-'.uniqid();
        $this->createEvent(['session_uuid' => $sessionUuid, 'sequence_in_session' => 2, 'event_type' => 'add_to_cart']);
        $this->createEvent(['session_uuid' => $sessionUuid, 'sequence_in_session' => 1, 'event_type' => 'product_view']);

        $journey = $this->makeService()->getSessionJourney($sessionUuid);

        $this->assertSame('product_view', $journey[0]['event_type']);
        $this->assertSame('add_to_cart', $journey[1]['event_type']);
    }

    public function test_get_session_journey_includes_product_details(): void
    {
        $sessionUuid = 'journey-'.uniqid();
        $this->createEvent([
            'session_uuid' => $sessionUuid, 'event_type' => 'product_view',
            'marketplace_product_id' => $this->product->id,
        ]);

        $journey = $this->makeService()->getSessionJourney($sessionUuid);

        $this->assertSame($this->product->id, $journey[0]['product']['id']);
        $this->assertSame('Journey Product', $journey[0]['product']['name']);
    }

    public function test_get_session_journey_null_product_when_not_set(): void
    {
        $sessionUuid = 'journey-'.uniqid();
        $this->createEvent(['session_uuid' => $sessionUuid]);

        $journey = $this->makeService()->getSessionJourney($sessionUuid);

        $this->assertNull($journey[0]['product']);
    }

    /**
     * Regression test for a real bug (fixed 2026-07-31): the eager load used
     * to be `tenant:id,business_name`, but `business_name` doesn't exist on
     * the `tenants` table — it lives on `business_details`. Any session event
     * with a tenant_id set crashed this method with a raw SQL error
     * ("Unknown column 'business_name'"). Fixed to go through
     * tenant.businessDetail instead.
     */
    public function test_get_session_journey_includes_tenant_business_name(): void
    {
        BusinessDetail::on('central')->create([
            'tenant_id' => $this->tenantId,
            'business_name' => 'Journey Test Business',
            'business_type_id' => BusinessType::on('central')->firstOrFail()->id,
            'business_category_id' => BusinessCategory::on('central')->firstOrFail()->id,
            'business_phone' => '0712345678',
            'status' => 'active',
        ]);
        $sessionUuid = 'journey-'.uniqid();
        $this->createEvent(['session_uuid' => $sessionUuid, 'tenant_id' => $this->tenantId]);

        $journey = $this->makeService()->getSessionJourney($sessionUuid);

        $this->assertSame($this->tenantId, $journey[0]['tenant']['id']);
        $this->assertSame('Journey Test Business', $journey[0]['tenant']['name']);
    }

    public function test_get_session_journey_tenant_name_null_when_business_detail_missing(): void
    {
        // Tenant exists but never submitted business details.
        $sessionUuid = 'journey-'.uniqid();
        $this->createEvent(['session_uuid' => $sessionUuid, 'tenant_id' => $this->tenantId]);

        $journey = $this->makeService()->getSessionJourney($sessionUuid);

        $this->assertSame($this->tenantId, $journey[0]['tenant']['id']);
        $this->assertNull($journey[0]['tenant']['name']);
    }

    public function test_get_session_journey_includes_event_properties_and_page_url(): void
    {
        $sessionUuid = 'journey-'.uniqid();
        $this->createEvent([
            'session_uuid' => $sessionUuid,
            'event_properties' => ['price' => 500],
            'page_url' => '/products/journey-product',
        ]);

        $journey = $this->makeService()->getSessionJourney($sessionUuid);

        $this->assertSame(['price' => 500], $journey[0]['event_properties']);
        $this->assertSame('/products/journey-product', $journey[0]['page_url']);
    }

    public function test_get_session_journey_scoped_to_given_session(): void
    {
        $sessionUuid = 'journey-'.uniqid();
        $this->createEvent(['session_uuid' => $sessionUuid]);
        $this->createEvent(['session_uuid' => 'journey-'.uniqid()]);

        $journey = $this->makeService()->getSessionJourney($sessionUuid);

        $this->assertCount(1, $journey);
    }

    // =========================================================================
    // getCommonConversionPaths()
    // =========================================================================

    public function test_get_common_conversion_paths_builds_arrow_joined_path(): void
    {
        $sessionUuid = 'journey-'.uniqid();
        $this->createEvent(['session_uuid' => $sessionUuid, 'sequence_in_session' => 1, 'event_type' => 'product_view']);
        $this->createEvent(['session_uuid' => $sessionUuid, 'sequence_in_session' => 2, 'event_type' => 'add_to_cart']);
        $this->createEvent(['session_uuid' => $sessionUuid, 'sequence_in_session' => 3, 'event_type' => 'purchase']);

        $paths = $this->makeService()->getCommonConversionPaths(now()->subDay(), now()->addDay());

        $this->assertTrue(collect($paths)->contains('path', 'product_view → add_to_cart → purchase'));
    }

    public function test_get_common_conversion_paths_ignores_irrelevant_event_types(): void
    {
        $sessionUuid = 'journey-'.uniqid();
        $this->createEvent(['session_uuid' => $sessionUuid, 'sequence_in_session' => 1, 'event_type' => 'page_view']);
        $this->createEvent(['session_uuid' => $sessionUuid, 'sequence_in_session' => 2, 'event_type' => 'product_view']);

        $paths = $this->makeService()->getCommonConversionPaths(now()->subDay(), now()->addDay());

        $this->assertTrue(collect($paths)->contains('path', 'product_view'));
        $this->assertFalse(collect($paths)->contains(fn ($p) => str_contains($p['path'], 'page_view')));
    }

    public function test_get_common_conversion_paths_counts_matching_sessions(): void
    {
        foreach (range(1, 3) as $i) {
            $sessionUuid = 'journey-count-'.uniqid();
            $this->createEvent(['session_uuid' => $sessionUuid, 'sequence_in_session' => 1, 'event_type' => 'product_view']);
        }

        $paths = $this->makeService()->getCommonConversionPaths(now()->subDay(), now()->addDay());

        $productViewOnly = collect($paths)->firstWhere('path', 'product_view');
        $this->assertGreaterThanOrEqual(3, $productViewOnly['count']);
    }

    public function test_get_common_conversion_paths_respects_date_range(): void
    {
        $sessionUuid = 'journey-'.uniqid();
        $this->createEvent([
            'session_uuid' => $sessionUuid, 'event_type' => 'purchase',
            'event_timestamp' => now()->subYears(2),
        ]);

        $paths = $this->makeService()->getCommonConversionPaths(now()->subDay(), now()->addDay());

        $this->assertFalse(collect($paths)->contains('path', 'purchase'));
    }

    public function test_get_common_conversion_paths_respects_limit(): void
    {
        foreach (['product_view', 'add_to_cart', 'checkout_started', 'purchase'] as $i => $type) {
            $this->createEvent([
                'session_uuid' => 'journey-limit-'.$i, 'sequence_in_session' => 1, 'event_type' => $type,
            ]);
        }

        $paths = $this->makeService()->getCommonConversionPaths(now()->subDay(), now()->addDay(), 2);

        $this->assertLessThanOrEqual(2, count($paths));
    }
}
