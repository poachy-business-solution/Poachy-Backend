<?php

namespace Tests\Feature\Central\Marketplace\Analytics;

use App\Models\CustomerJourneyEvent;
use App\Models\MarketplaceProduct;
use App\Models\ProductPageView;
use App\Models\SearchQuery;
use App\Services\Central\Marketplace\Analytics\AnalyticsTrackingService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AnalyticsTrackingServiceTest extends TestCase
{
    private string $tenantId;

    private MarketplaceProduct $product;

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

        $this->tenantId = 'analytics-track-test-'.uniqid();
        DB::connection('central')->table('tenants')->insertOrIgnore([
            'id' => $this->tenantId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->product = MarketplaceProduct::on('central')->create([
            'tenant_id' => $this->tenantId,
            'name' => 'Tracked Product',
            'slug' => 'tracked-product-'.uniqid(),
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
        CustomerJourneyEvent::on('central')->where('session_id', 'like', 'track-test-%')->delete();
        ProductPageView::on('central')->where('session_id', 'like', 'track-test-%')->delete();
        SearchQuery::on('central')->where('session_id', 'like', 'track-test-%')->delete();
        MarketplaceProduct::on('central')->where('id', $this->product->id)->forceDelete();
        DB::connection('central')->table('tenants')->where('id', $this->tenantId)->delete();
        DB::connection('central')->statement('SET foreign_key_checks = 1');

        parent::tearDown();
    }

    private function makeService(): AnalyticsTrackingService
    {
        return new AnalyticsTrackingService;
    }

    // =========================================================================
    // trackEvent()
    // =========================================================================

    public function test_track_event_creates_record(): void
    {
        $sessionId = 'track-test-'.uniqid();

        $event = $this->makeService()->trackEvent([
            'session_id' => $sessionId,
            'event_type' => 'page_view',
        ]);

        $this->assertNotNull($event);
        $this->assertSame('page_view', $event->event_type);
    }

    public function test_track_event_defaults_session_uuid_from_session_id(): void
    {
        $sessionId = 'track-test-'.uniqid();

        $event = $this->makeService()->trackEvent([
            'session_id' => $sessionId,
            'event_type' => 'page_view',
        ]);

        $this->assertSame($sessionId, $event->session_uuid);
    }

    public function test_track_event_auto_increments_sequence_in_session(): void
    {
        $sessionId = 'track-test-'.uniqid();

        $first = $this->makeService()->trackEvent(['session_id' => $sessionId, 'event_type' => 'page_view']);
        $second = $this->makeService()->trackEvent(['session_id' => $sessionId, 'event_type' => 'product_view']);
        $third = $this->makeService()->trackEvent(['session_id' => $sessionId, 'event_type' => 'add_to_cart']);

        $this->assertSame(1, $first->sequence_in_session);
        $this->assertSame(2, $second->sequence_in_session);
        $this->assertSame(3, $third->sequence_in_session);
    }

    public function test_track_event_sequence_is_independent_per_session(): void
    {
        $sessionA = 'track-test-'.uniqid();
        $sessionB = 'track-test-'.uniqid();

        $this->makeService()->trackEvent(['session_id' => $sessionA, 'event_type' => 'page_view']);
        $eventB = $this->makeService()->trackEvent(['session_id' => $sessionB, 'event_type' => 'page_view']);

        $this->assertSame(1, $eventB->sequence_in_session);
    }

    public function test_track_event_defaults_event_timestamp(): void
    {
        $event = $this->makeService()->trackEvent([
            'session_id' => 'track-test-'.uniqid(), 'event_type' => 'page_view',
        ]);

        $this->assertNotNull($event->event_timestamp);
    }

    public function test_track_event_returns_null_and_logs_on_failure(): void
    {
        // Missing required 'session_id' triggers a DB-level failure, caught and swallowed.
        $result = $this->makeService()->trackEvent(['event_type' => 'page_view']);

        $this->assertNull($result);
    }

    // =========================================================================
    // trackProductView()
    // =========================================================================

    public function test_track_product_view_creates_record(): void
    {
        $view = $this->makeService()->trackProductView([
            'marketplace_product_id' => $this->product->id,
            'session_id' => 'track-test-'.uniqid(),
        ]);

        $this->assertNotNull($view);
    }

    public function test_track_product_view_defaults_viewed_at(): void
    {
        $view = $this->makeService()->trackProductView([
            'marketplace_product_id' => $this->product->id,
            'session_id' => 'track-test-'.uniqid(),
        ]);

        $this->assertNotNull($view->viewed_at);
    }

    public function test_track_product_view_respects_explicit_viewed_at(): void
    {
        $explicit = now()->subDay();

        $view = $this->makeService()->trackProductView([
            'marketplace_product_id' => $this->product->id,
            'session_id' => 'track-test-'.uniqid(), 'viewed_at' => $explicit,
        ]);

        $this->assertSame($explicit->timestamp, $view->viewed_at->timestamp);
    }

    public function test_track_product_view_returns_null_and_logs_on_failure(): void
    {
        // Missing required 'session_id'.
        $result = $this->makeService()->trackProductView([]);

        $this->assertNull($result);
    }

    // =========================================================================
    // trackSearch()
    // =========================================================================

    public function test_track_search_creates_record(): void
    {
        $search = $this->makeService()->trackSearch([
            'session_id' => 'track-test-'.uniqid(), 'search_query' => 'running shoes',
        ]);

        $this->assertNotNull($search);
        $this->assertSame('running shoes', $search->search_query);
    }

    public function test_track_search_defaults_searched_at(): void
    {
        $search = $this->makeService()->trackSearch([
            'session_id' => 'track-test-'.uniqid(), 'search_query' => 'shoes',
        ]);

        $this->assertNotNull($search->searched_at);
    }

    public function test_track_search_derives_has_results_true_from_positive_count(): void
    {
        $search = $this->makeService()->trackSearch([
            'session_id' => 'track-test-'.uniqid(), 'search_query' => 'shoes', 'results_count' => 5,
        ]);

        $this->assertTrue($search->has_results);
    }

    public function test_track_search_derives_has_results_false_from_zero_count(): void
    {
        $search = $this->makeService()->trackSearch([
            'session_id' => 'track-test-'.uniqid(), 'search_query' => 'nonexistent item xyz', 'results_count' => 0,
        ]);

        $this->assertFalse($search->has_results);
    }

    public function test_track_search_respects_explicit_has_results(): void
    {
        $search = $this->makeService()->trackSearch([
            'session_id' => 'track-test-'.uniqid(), 'search_query' => 'shoes',
            'results_count' => 5, 'has_results' => false,
        ]);

        $this->assertFalse($search->has_results);
    }

    public function test_track_search_returns_null_and_logs_on_failure(): void
    {
        // Missing required 'search_query'.
        $result = $this->makeService()->trackSearch(['session_id' => 'track-test-'.uniqid()]);

        $this->assertNull($result);
    }
}
