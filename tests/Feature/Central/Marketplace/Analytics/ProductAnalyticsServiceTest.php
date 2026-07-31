<?php

namespace Tests\Feature\Central\Marketplace\Analytics;

use App\Models\MarketplaceProduct;
use App\Models\ProductPageView;
use App\Services\Central\Marketplace\Analytics\ProductAnalyticsService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductAnalyticsServiceTest extends TestCase
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

        $this->tenantId = 'product-analytics-test-'.uniqid();
        DB::connection('central')->table('tenants')->insertOrIgnore([
            'id' => $this->tenantId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->product = $this->createProduct();
    }

    protected function tearDown(): void
    {
        DB::connection('central')->statement('SET foreign_key_checks = 0');
        ProductPageView::on('central')->where('marketplace_product_id', $this->product->id)->delete();
        MarketplaceProduct::on('central')->where('tenant_id', $this->tenantId)->forceDelete();
        DB::connection('central')->table('tenants')->where('id', $this->tenantId)->delete();
        DB::connection('central')->statement('SET foreign_key_checks = 1');

        parent::tearDown();
    }

    private function makeService(): ProductAnalyticsService
    {
        return new ProductAnalyticsService;
    }

    private function createProduct(array $overrides = []): MarketplaceProduct
    {
        return MarketplaceProduct::on('central')->create(array_merge([
            'tenant_id' => $this->tenantId,
            'name' => 'Analytics Product',
            'slug' => 'analytics-product-'.uniqid(),
            'sku' => 'SKU-'.uniqid(),
            'online_price' => 500,
            'base_uom_code' => 'pcs',
            'base_uom_name' => 'Piece',
            'tax_rate' => 0,
            'available_quantity' => 10,
            'stock_status' => 'in_stock',
            'is_active' => true,
        ], $overrides));
    }

    private function createView(array $overrides = []): ProductPageView
    {
        return ProductPageView::on('central')->create(array_merge([
            'marketplace_product_id' => $this->product->id,
            'session_id' => 'pa-sess-'.uniqid(),
            'viewed_at' => now(),
        ], $overrides));
    }

    // =========================================================================
    // getProductPerformance()
    // =========================================================================

    public function test_get_product_performance_counts_total_views(): void
    {
        $this->createView();
        $this->createView();
        $this->createView();

        $result = $this->makeService()->getProductPerformance($this->product->id, now()->subDay(), now()->addDay());

        $this->assertSame(3, $result['total_views']);
    }

    public function test_get_product_performance_returns_zeros_with_no_views(): void
    {
        $result = $this->makeService()->getProductPerformance($this->product->id, now()->subDay(), now()->addDay());

        $this->assertSame(0, $result['total_views']);
        $this->assertSame(0.0, $result['view_to_cart_rate']);
        $this->assertSame(0.0, $result['engagement_rate']);
    }

    public function test_get_product_performance_computes_cart_and_wishlist_rates(): void
    {
        $this->createView(['added_to_cart' => true]);
        $this->createView(['added_to_cart' => true]);
        $this->createView(['added_to_cart' => false]);
        $this->createView(['added_to_wishlist' => true]);

        $result = $this->makeService()->getProductPerformance($this->product->id, now()->subDay(), now()->addDay());

        $this->assertSame(4, $result['total_views']);
        // Raw SUM() aggregates come back as numeric strings via PDO, unlike
        // COUNT() — assertEquals rather than assertSame for those.
        $this->assertEquals(2, $result['added_to_cart']);
        $this->assertEquals(1, $result['added_to_wishlist']);
        $this->assertSame(50.0, $result['view_to_cart_rate']);
        $this->assertSame(25.0, $result['view_to_wishlist_rate']);
    }

    public function test_get_product_performance_computes_avg_time_spent(): void
    {
        $this->createView(['time_spent_seconds' => 10]);
        $this->createView(['time_spent_seconds' => 20]);

        $result = $this->makeService()->getProductPerformance($this->product->id, now()->subDay(), now()->addDay());

        $this->assertSame(15.0, $result['avg_time_spent_seconds']);
    }

    public function test_get_product_performance_computes_engagement_rate(): void
    {
        // 2 of 3 possible engagement signals across 1 view = 2/(1*3) = 66.67%.
        $this->createView([
            'scrolled_to_description' => true,
            'scrolled_to_reviews' => true,
            'clicked_images' => false,
        ]);

        $result = $this->makeService()->getProductPerformance($this->product->id, now()->subDay(), now()->addDay());

        $this->assertSame(66.67, $result['engagement_rate']);
    }

    public function test_get_product_performance_excludes_views_outside_date_range(): void
    {
        $this->createView(['viewed_at' => now()->subYears(2)]);

        $result = $this->makeService()->getProductPerformance($this->product->id, now()->subDay(), now()->addDay());

        $this->assertSame(0, $result['total_views']);
    }

    public function test_get_product_performance_scoped_to_given_product(): void
    {
        $otherProduct = $this->createProduct();
        $this->createView(['marketplace_product_id' => $otherProduct->id]);

        $result = $this->makeService()->getProductPerformance($this->product->id, now()->subDay(), now()->addDay());

        $this->assertSame(0, $result['total_views']);
    }

    // =========================================================================
    // getTopProducts()
    // =========================================================================

    public function test_get_top_products_orders_by_conversion_rate_desc(): void
    {
        $lowConversion = $this->createProduct(['slug' => 'low-conv-'.uniqid()]);
        $highConversion = $this->createProduct(['slug' => 'high-conv-'.uniqid()]);

        // 1/4 = 25% conversion.
        ProductPageView::on('central')->create(['marketplace_product_id' => $lowConversion->id, 'session_id' => 's1', 'viewed_at' => now(), 'added_to_cart' => true]);
        ProductPageView::on('central')->create(['marketplace_product_id' => $lowConversion->id, 'session_id' => 's2', 'viewed_at' => now(), 'added_to_cart' => false]);
        ProductPageView::on('central')->create(['marketplace_product_id' => $lowConversion->id, 'session_id' => 's3', 'viewed_at' => now(), 'added_to_cart' => false]);
        ProductPageView::on('central')->create(['marketplace_product_id' => $lowConversion->id, 'session_id' => 's4', 'viewed_at' => now(), 'added_to_cart' => false]);

        // 1/1 = 100% conversion.
        ProductPageView::on('central')->create(['marketplace_product_id' => $highConversion->id, 'session_id' => 's5', 'viewed_at' => now(), 'added_to_cart' => true]);

        $result = $this->makeService()->getTopProducts(now()->subDay(), now()->addDay(), 50);
        $ids = array_column($result, 'product_id');
        $highIndex = array_search($highConversion->id, $ids);
        $lowIndex = array_search($lowConversion->id, $ids);

        $this->assertLessThan($lowIndex, $highIndex);

        ProductPageView::on('central')->whereIn('marketplace_product_id', [$lowConversion->id, $highConversion->id])->delete();
        MarketplaceProduct::on('central')->whereIn('id', [$lowConversion->id, $highConversion->id])->forceDelete();
    }

    public function test_get_top_products_respects_limit(): void
    {
        $this->createView();

        $result = $this->makeService()->getTopProducts(now()->subDay(), now()->addDay(), 1);

        $this->assertLessThanOrEqual(1, count($result));
    }

    // =========================================================================
    // getReferrerSourceBreakdown()
    // =========================================================================

    public function test_get_referrer_source_breakdown_groups_by_source(): void
    {
        $this->createView(['referrer_source' => 'search', 'added_to_cart' => true]);
        $this->createView(['referrer_source' => 'search', 'added_to_cart' => false]);
        $this->createView(['referrer_source' => 'category']);

        $result = $this->makeService()->getReferrerSourceBreakdown($this->product->id, now()->subDay(), now()->addDay());
        $bySource = collect($result)->keyBy('referrer_source');

        $this->assertSame(2, $bySource['search']['views']);
        // conversions is a raw SUM() (not COUNT()) — MySQL/PDO returns it as a
        // numeric string rather than an int, unlike the plain COUNT() above.
        $this->assertEquals(1, $bySource['search']['conversions']);
        $this->assertSame(50.0, $bySource['search']['conversion_rate']);
        $this->assertSame(1, $bySource['category']['views']);
    }

    public function test_get_referrer_source_breakdown_defaults_null_source_to_direct(): void
    {
        $this->createView(['referrer_source' => null]);

        $result = $this->makeService()->getReferrerSourceBreakdown($this->product->id, now()->subDay(), now()->addDay());

        $this->assertTrue(collect($result)->contains('referrer_source', 'direct'));
    }
}
