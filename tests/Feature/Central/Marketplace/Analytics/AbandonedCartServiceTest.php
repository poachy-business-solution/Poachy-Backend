<?php

namespace Tests\Feature\Central\Marketplace\Analytics;

use App\Models\MarketplaceCustomer;
use App\Models\MarketplaceProduct;
use App\Models\ShoppingCart;
use App\Models\ShoppingCartItem;
use App\Models\User;
use App\Services\Central\Marketplace\Analytics\AbandonedCartService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AbandonedCartServiceTest extends TestCase
{
    private string $tenantId;

    private MarketplaceProduct $product;

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

        $this->tenantId = 'abandoned-cart-test-'.uniqid();
        DB::connection('central')->table('tenants')->insertOrIgnore([
            'id' => $this->tenantId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->product = MarketplaceProduct::on('central')->create([
            'tenant_id' => $this->tenantId,
            'name' => 'Cart Product',
            'slug' => 'cart-product-'.uniqid(),
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
        // shopping_carts has no tenant_id (removed by
        // 2026_02_16_151856_remove_tenant_id_from_shopping_carts_table — a cart can
        // span multiple tenants), so scope cleanup by the session_id prefix instead.
        $cartIds = ShoppingCart::on('central')->where('session_id', 'like', 'cart-sess-%')->pluck('id');
        ShoppingCartItem::on('central')->whereIn('cart_id', $cartIds)->delete();
        ShoppingCart::on('central')->whereIn('id', $cartIds)->delete();
        MarketplaceCustomer::on('central')->whereIn('id', $this->customerIds)->forceDelete();
        User::on('central')->whereIn('id', $this->userIds)->forceDelete();
        MarketplaceProduct::on('central')->where('id', $this->product->id)->forceDelete();
        DB::connection('central')->table('tenants')->where('id', $this->tenantId)->delete();
        DB::connection('central')->statement('SET foreign_key_checks = 1');

        parent::tearDown();
    }

    private function makeService(): AbandonedCartService
    {
        return new AbandonedCartService;
    }

    private function createCustomer(array $overrides = []): MarketplaceCustomer
    {
        $user = User::on('central')->create([
            'name' => 'Cart Customer',
            'email' => 'cart-customer-'.uniqid().'@test.com',
            'password' => bcrypt('password'),
            'user_type' => 'customer',
        ]);
        $this->userIds[] = $user->id;

        $customer = MarketplaceCustomer::on('central')->create(array_merge([
            'user_id' => $user->id,
            'customer_number' => 'MKT-'.uniqid(),
            'phone' => '0712'.rand(100000, 999999),
            'is_active' => true,
            'phone_verified' => true,
            'accepts_marketing' => true,
            'accepts_sms' => true,
        ], $overrides));
        $this->customerIds[] = $customer->id;

        return $customer;
    }

    private function createCart(array $overrides = []): ShoppingCart
    {
        return ShoppingCart::on('central')->create(array_merge([
            'session_id' => 'cart-sess-'.uniqid(),
            'status' => 'abandoned',
            'abandoned_at' => now(),
            'recovery_email_sent' => false,
            'recovery_sms_sent' => false,
        ], $overrides));
    }

    private function addItem(ShoppingCart $cart, array $overrides = []): ShoppingCartItem
    {
        return ShoppingCartItem::on('central')->create(array_merge([
            'cart_id' => $cart->id,
            'marketplace_product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'product_sku' => $this->product->sku,
            'quantity' => 2,
            'uom_code' => 'pcs',
            'unit_price' => 250,
            'added_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    // =========================================================================
    // getEmailEligibleCarts()
    // =========================================================================

    public function test_get_email_eligible_carts_includes_matching_cart_with_email_and_totals(): void
    {
        $customer = $this->createCustomer();
        $cart = $this->createCart(['customer_id' => $customer->id]);
        $this->addItem($cart);
        $this->addItem($cart, ['unit_price' => 100, 'quantity' => 1]);

        $result = $this->makeService()->getEmailEligibleCarts();
        $entry = collect($result)->firstWhere('cart_id', $cart->id);

        $this->assertNotNull($entry);
        $this->assertSame($customer->user->email, $entry['email']);
        $this->assertSame(2, $entry['item_count']);
        $this->assertEquals(600.0, $entry['subtotal']);
    }

    public function test_get_email_eligible_carts_excludes_when_already_sent(): void
    {
        $customer = $this->createCustomer();
        $cart = $this->createCart(['customer_id' => $customer->id, 'recovery_email_sent' => true]);

        $result = $this->makeService()->getEmailEligibleCarts();

        $this->assertFalse(collect($result)->contains('cart_id', $cart->id));
    }

    public function test_get_email_eligible_carts_excludes_guest_carts(): void
    {
        $cart = $this->createCart(['customer_id' => null]);

        $result = $this->makeService()->getEmailEligibleCarts();

        $this->assertFalse(collect($result)->contains('cart_id', $cart->id));
    }

    public function test_get_email_eligible_carts_excludes_customer_who_opted_out_of_marketing(): void
    {
        $customer = $this->createCustomer(['accepts_marketing' => false]);
        $cart = $this->createCart(['customer_id' => $customer->id]);

        $result = $this->makeService()->getEmailEligibleCarts();

        $this->assertFalse(collect($result)->contains('cart_id', $cart->id));
    }

    public function test_get_email_eligible_carts_excludes_inactive_customer(): void
    {
        $customer = $this->createCustomer(['is_active' => false]);
        $cart = $this->createCart(['customer_id' => $customer->id]);

        $result = $this->makeService()->getEmailEligibleCarts();

        $this->assertFalse(collect($result)->contains('cart_id', $cart->id));
    }

    public function test_get_email_eligible_carts_excludes_non_abandoned_status(): void
    {
        $customer = $this->createCustomer();
        $cart = $this->createCart(['customer_id' => $customer->id, 'status' => 'active']);

        $result = $this->makeService()->getEmailEligibleCarts();

        $this->assertFalse(collect($result)->contains('cart_id', $cart->id));
    }

    public function test_get_email_eligible_carts_respects_since_filter(): void
    {
        $customer = $this->createCustomer();
        $cart = $this->createCart(['customer_id' => $customer->id, 'abandoned_at' => now()->subDays(10)]);

        $result = $this->makeService()->getEmailEligibleCarts(now()->subDay());

        $this->assertFalse(collect($result)->contains('cart_id', $cart->id));
    }

    // =========================================================================
    // getSMSEligibleCarts()
    // =========================================================================

    public function test_get_sms_eligible_carts_includes_matching_cart_with_phone(): void
    {
        $customer = $this->createCustomer();
        $cart = $this->createCart(['customer_id' => $customer->id]);
        $this->addItem($cart);

        $result = $this->makeService()->getSMSEligibleCarts();
        $entry = collect($result)->firstWhere('cart_id', $cart->id);

        $this->assertNotNull($entry);
        $this->assertSame($customer->phone, $entry['phone']);
    }

    public function test_get_sms_eligible_carts_excludes_when_already_sent(): void
    {
        $customer = $this->createCustomer();
        $cart = $this->createCart(['customer_id' => $customer->id, 'recovery_sms_sent' => true]);

        $result = $this->makeService()->getSMSEligibleCarts();

        $this->assertFalse(collect($result)->contains('cart_id', $cart->id));
    }

    public function test_get_sms_eligible_carts_excludes_customer_who_opted_out_of_sms(): void
    {
        $customer = $this->createCustomer(['accepts_sms' => false]);
        $cart = $this->createCart(['customer_id' => $customer->id]);

        $result = $this->makeService()->getSMSEligibleCarts();

        $this->assertFalse(collect($result)->contains('cart_id', $cart->id));
    }

    public function test_get_sms_eligible_carts_excludes_unverified_phone(): void
    {
        $customer = $this->createCustomer(['phone_verified' => false]);
        $cart = $this->createCart(['customer_id' => $customer->id]);

        $result = $this->makeService()->getSMSEligibleCarts();

        $this->assertFalse(collect($result)->contains('cart_id', $cart->id));
    }

    public function test_get_sms_eligible_carts_excludes_inactive_customer(): void
    {
        $customer = $this->createCustomer(['is_active' => false]);
        $cart = $this->createCart(['customer_id' => $customer->id]);

        $result = $this->makeService()->getSMSEligibleCarts();

        $this->assertFalse(collect($result)->contains('cart_id', $cart->id));
    }

    // =========================================================================
    // getAbandonmentStats()
    // =========================================================================

    /**
     * shopping_carts has no tenant_id (removed by
     * 2026_02_16_151856_remove_tenant_id_from_shopping_carts_table), so
     * getAbandonmentStats() is inherently global/unscoped — matching that,
     * assert deltas off a baseline rather than absolute counts, since other
     * tests in the same suite run may create carts within the same date range.
     */
    public function test_get_abandonment_stats_computes_counts_and_rate(): void
    {
        $baseline = $this->makeService()->getAbandonmentStats(now()->subDay(), now()->addDay());

        $customer = $this->createCustomer();
        $this->createCart(['customer_id' => $customer->id, 'status' => 'abandoned']);
        $this->createCart(['customer_id' => $customer->id, 'status' => 'abandoned']);
        $this->createCart(['customer_id' => $customer->id, 'status' => 'converted']);
        $this->createCart(['customer_id' => $customer->id, 'status' => 'active']);

        $result = $this->makeService()->getAbandonmentStats(now()->subDay(), now()->addDay());

        $this->assertEquals($baseline['total_carts'] + 4, $result['total_carts']);
        $this->assertEquals($baseline['abandoned_carts'] + 2, $result['abandoned_carts']);
        $this->assertEquals($baseline['converted_carts'] + 1, $result['converted_carts']);
        $expectedRate = round((($baseline['abandoned_carts'] + 2) / ($baseline['total_carts'] + 4)) * 100, 2);
        $this->assertSame($expectedRate, $result['abandonment_rate']);
    }

    public function test_get_abandonment_stats_counts_recovery_sends(): void
    {
        $baseline = $this->makeService()->getAbandonmentStats(now()->subDay(), now()->addDay());

        $customer = $this->createCustomer();
        $this->createCart(['customer_id' => $customer->id, 'recovery_email_sent' => true]);
        $this->createCart(['customer_id' => $customer->id, 'recovery_sms_sent' => true]);

        $result = $this->makeService()->getAbandonmentStats(now()->subDay(), now()->addDay());

        $this->assertEquals($baseline['recovery_emails_sent'] + 1, $result['recovery_emails_sent']);
        $this->assertEquals($baseline['recovery_sms_sent'] + 1, $result['recovery_sms_sent']);
    }

    public function test_get_abandonment_stats_returns_zeros_with_no_carts(): void
    {
        $result = $this->makeService()->getAbandonmentStats(now()->subYears(10), now()->subYears(9));

        $this->assertSame(0, $result['total_carts']);
        $this->assertSame(0.0, $result['abandonment_rate']);
    }

    public function test_get_abandonment_stats_respects_date_range(): void
    {
        $baseline = $this->makeService()->getAbandonmentStats(now()->subDay(), now()->addDay());
        $customer = $this->createCustomer();
        $cart = $this->createCart(['customer_id' => $customer->id]);
        DB::connection('central')->table('shopping_carts')->where('id', $cart->id)->update(['created_at' => now()->subYears(2)]);

        $result = $this->makeService()->getAbandonmentStats(now()->subDay(), now()->addDay());

        $this->assertEquals($baseline['total_carts'], $result['total_carts']);
    }
}
