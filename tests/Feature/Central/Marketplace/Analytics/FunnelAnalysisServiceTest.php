<?php

namespace Tests\Feature\Central\Marketplace\Analytics;

use App\Enums\Central\OrderStatus;
use App\Models\CheckoutSession;
use App\Models\MarketplaceCustomer;
use App\Models\MarketplaceOrder;
use App\Models\MarketplaceOrderPayment;
use App\Models\MarketplaceProduct;
use App\Models\ProductPageView;
use App\Models\ShoppingCart;
use App\Models\User;
use App\Services\Central\Marketplace\Analytics\FunnelAnalysisService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * None of the tables behind FunnelAnalysisService (product_page_views,
 * shopping_carts, checkout_sessions, marketplace_order_payments,
 * marketplace_orders) can be scoped by tenant — shopping_carts in particular
 * had tenant_id removed entirely (see AbandonedCartServiceTest). Every method
 * here is inherently global/platform-wide, so count-based assertions use a
 * before/after delta rather than absolute values to stay robust against other
 * tests in the same suite run creating rows in the same "now" window.
 */
class FunnelAnalysisServiceTest extends TestCase
{
    private string $tenantId;

    private MarketplaceProduct $product;

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

        $this->tenantId = 'funnel-test-'.uniqid();
        DB::connection('central')->table('tenants')->insertOrIgnore([
            'id' => $this->tenantId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->product = MarketplaceProduct::on('central')->create([
            'tenant_id' => $this->tenantId,
            'name' => 'Funnel Product',
            'slug' => 'funnel-product-'.uniqid(),
            'sku' => 'SKU-'.uniqid(),
            'online_price' => 500,
            'base_uom_code' => 'pcs',
            'base_uom_name' => 'Piece',
            'tax_rate' => 0,
            'available_quantity' => 10,
            'stock_status' => 'in_stock',
            'is_active' => true,
        ]);

        $this->customer = $this->createCustomer();
    }

    protected function tearDown(): void
    {
        DB::connection('central')->statement('SET foreign_key_checks = 0');
        ProductPageView::on('central')->where('marketplace_product_id', $this->product->id)->delete();
        $cartIds = ShoppingCart::on('central')->where('session_id', 'like', 'funnel-cart-%')->pluck('id');
        CheckoutSession::on('central')->whereIn('cart_id', $cartIds)->delete();
        ShoppingCart::on('central')->whereIn('id', $cartIds)->delete();
        MarketplaceOrderPayment::on('central')->whereHas('order', fn ($q) => $q->where('tenant_id', $this->tenantId))->forceDelete();
        MarketplaceOrder::on('central')->where('tenant_id', $this->tenantId)->forceDelete();
        MarketplaceCustomer::on('central')->whereIn('id', $this->customerIds)->forceDelete();
        User::on('central')->whereIn('id', $this->userIds)->forceDelete();
        MarketplaceProduct::on('central')->where('id', $this->product->id)->forceDelete();
        DB::connection('central')->table('tenants')->where('id', $this->tenantId)->delete();
        DB::connection('central')->statement('SET foreign_key_checks = 1');

        parent::tearDown();
    }

    private function makeService(): FunnelAnalysisService
    {
        return new FunnelAnalysisService;
    }

    private function createCustomer(): MarketplaceCustomer
    {
        $user = User::on('central')->create([
            'name' => 'Funnel Customer',
            'email' => 'funnel-customer-'.uniqid().'@test.com',
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

    private function createOrder(array $overrides = []): MarketplaceOrder
    {
        return MarketplaceOrder::on('central')->create(array_merge([
            'order_number' => 'ORD-'.uniqid(),
            'customer_id' => $this->customer->id,
            'tenant_id' => $this->tenantId,
            'merchant_name' => 'Test Merchant',
            'subtotal' => 500.0,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'delivery_fee' => 0,
            'total_amount' => 500.0,
            'fulfillment_type' => 'pickup',
            'order_status' => OrderStatus::Confirmed,
        ], $overrides));
    }

    private function funnelRange(): array
    {
        return [now()->subDay(), now()->addDay()];
    }

    // =========================================================================
    // getConversionFunnel()
    // =========================================================================

    public function test_get_conversion_funnel_counts_each_stage(): void
    {
        [$start, $end] = $this->funnelRange();
        $baseline = $this->makeService()->getConversionFunnel($start, $end);

        ProductPageView::on('central')->create([
            'marketplace_product_id' => $this->product->id, 'session_id' => 'funnel-view', 'viewed_at' => now(),
        ]);
        ShoppingCart::on('central')->create(['session_id' => 'funnel-cart-'.uniqid(), 'status' => 'active']);
        $order = $this->createOrder();
        MarketplaceOrderPayment::on('central')->create([
            'order_id' => $order->id, 'payment_method' => 'mpesa', 'amount' => 500,
            'payment_status' => 'completed', 'initiated_at' => now(), 'completed_at' => now(),
        ]);

        $result = $this->makeService()->getConversionFunnel($start, $end);

        $this->assertSame($baseline['product_views'] + 1, $result['product_views']);
        $this->assertSame($baseline['carts_created'] + 1, $result['carts_created']);
        $this->assertSame($baseline['payments_initiated'] + 1, $result['payments_initiated']);
        $this->assertSame($baseline['payments_completed'] + 1, $result['payments_completed']);
        $this->assertSame($baseline['orders_confirmed'] + 1, $result['orders_confirmed']);
    }

    public function test_get_conversion_funnel_zero_denominator_rate_is_zero(): void
    {
        $result = $this->makeService()->getConversionFunnel(now()->subYears(10), now()->subYears(9));

        $this->assertSame(0, $result['product_views']);
        $this->assertSame(0.0, $result['view_to_cart_rate']);
        $this->assertSame(0.0, $result['overall_conversion_rate']);
    }

    public function test_get_conversion_funnel_computes_view_to_cart_rate(): void
    {
        // Isolated far-past window avoids baseline pollution entirely, so an
        // exact rate can be asserted here.
        $start = now()->subYears(5);
        $end = now()->subYears(5)->addDay();
        $viewedAt = now()->subYears(5)->addHours(2);

        ProductPageView::on('central')->create(['marketplace_product_id' => $this->product->id, 'session_id' => 'funnel-rate-1', 'viewed_at' => $viewedAt]);
        ProductPageView::on('central')->create(['marketplace_product_id' => $this->product->id, 'session_id' => 'funnel-rate-2', 'viewed_at' => $viewedAt]);
        DB::connection('central')->table('shopping_carts')->insert([
            'session_id' => 'funnel-cart-rate-'.uniqid(), 'status' => 'active',
            'created_at' => $viewedAt, 'updated_at' => $viewedAt,
        ]);

        $result = $this->makeService()->getConversionFunnel($start, $end);

        $this->assertSame(2, $result['product_views']);
        $this->assertSame(1, $result['carts_created']);
        $this->assertSame(50.0, $result['view_to_cart_rate']);
    }

    // =========================================================================
    // getAbandonmentRates()
    // =========================================================================

    public function test_get_abandonment_rates_counts_by_step(): void
    {
        [$start, $end] = $this->funnelRange();
        $baseline = $this->makeService()->getAbandonmentRates($start, $end);

        $cart = ShoppingCart::on('central')->create(['session_id' => 'funnel-cart-'.uniqid(), 'status' => 'abandoned']);
        CheckoutSession::on('central')->create([
            'cart_id' => $cart->id, 'is_abandoned' => true, 'abandoned_at' => now(), 'abandoned_at_step' => 'payment',
        ]);

        $result = $this->makeService()->getAbandonmentRates($start, $end);

        $this->assertSame($baseline['total_sessions'] + 1, $result['total_sessions']);
        // abandoned_sessions/abandoned_at_payment come from raw SUM() and arrive
        // as numeric strings via PDO, unlike the COUNT()-derived total_sessions.
        $this->assertEquals($baseline['abandoned_sessions'] + 1, $result['abandoned_sessions']);
        $this->assertEquals($baseline['abandoned_at_payment'] + 1, $result['abandoned_at_payment']);
    }

    // =========================================================================
    // getConversionByDevice()
    // =========================================================================

    public function test_get_conversion_by_device_groups_and_computes_rate(): void
    {
        $device = 'funnel-device-'.uniqid();
        ShoppingCart::on('central')->create(['session_id' => 'funnel-cart-'.uniqid(), 'status' => 'converted', 'device_type' => $device]);
        ShoppingCart::on('central')->create(['session_id' => 'funnel-cart-'.uniqid(), 'status' => 'active', 'device_type' => $device]);

        $result = $this->makeService()->getConversionByDevice(...$this->funnelRange());
        $entry = collect($result)->firstWhere('device_type', $device);

        $this->assertNotNull($entry);
        $this->assertSame(2, $entry['total_carts']);
        // converted_carts is a raw SUM() — arrives as a numeric string via PDO.
        $this->assertEquals(1, $entry['converted_carts']);
        $this->assertSame(50.0, $entry['conversion_rate']);
    }

    public function test_get_conversion_by_device_defaults_null_device_to_unknown(): void
    {
        // Isolated far-past window — the 'unknown' bucket is otherwise unscoped
        // and would collide with any other null-device cart in the shared table.
        $start = now()->subYears(6);
        $end = now()->subYears(6)->addDay();
        DB::connection('central')->table('shopping_carts')->insert([
            'session_id' => 'funnel-cart-unknown-'.uniqid(), 'status' => 'active', 'device_type' => null,
            'created_at' => $start->copy()->addHour(), 'updated_at' => $start->copy()->addHour(),
        ]);

        $result = $this->makeService()->getConversionByDevice($start, $end);

        $this->assertTrue(collect($result)->contains('device_type', 'unknown'));
    }

    // =========================================================================
    // getAverageTimeToPurchase()
    // =========================================================================

    public function test_get_average_time_to_purchase_computes_from_converted_carts(): void
    {
        [$start, $end] = $this->funnelRange();
        $order = $this->createOrder();
        $createdAt = now()->subHours(2);
        $convertedAt = $createdAt->copy()->addSeconds(10000);
        DB::connection('central')->table('shopping_carts')->insert([
            'session_id' => 'funnel-cart-'.uniqid(), 'status' => 'converted',
            'converted_order_id' => $order->id,
            'created_at' => $createdAt, 'updated_at' => $convertedAt, 'converted_at' => $convertedAt,
        ]);

        $result = $this->makeService()->getAverageTimeToPurchase($start, $end);

        // A huge, deliberately-outlying duration guarantees it dominates the max
        // regardless of any other converted carts from other tests in this run.
        $this->assertGreaterThanOrEqual(10000, $result['max_seconds']);
        $this->assertGreaterThan(0, $result['average_seconds']);
    }

    public function test_get_average_time_to_purchase_ignores_non_converted_carts(): void
    {
        $result = $this->makeService()->getAverageTimeToPurchase(now()->subYears(10), now()->subYears(9));

        // average_seconds is always run through round(), which returns float even
        // for a zero/null input — unlike min/max, which pass through the raw ?? 0.
        $this->assertSame(0.0, $result['average_seconds']);
        $this->assertSame(0, $result['min_seconds']);
        $this->assertSame(0, $result['max_seconds']);
    }
}
