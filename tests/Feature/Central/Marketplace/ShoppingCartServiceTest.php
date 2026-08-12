<?php

namespace Tests\Feature\Central\Marketplace;

use App\Events\Central\Marketplace\CartItemAdded;
use App\Events\Central\Marketplace\CartItemRemoved;
use App\Models\MarketplaceCustomer;
use App\Models\MarketplaceProduct;
use App\Models\ShoppingCart;
use App\Models\ShoppingCartItem;
use App\Models\User;
use App\Services\Central\Marketplace\ShoppingCartService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ShoppingCartServiceTest extends TestCase
{
    private string $tenantId;

    private User $user;

    private MarketplaceCustomer $customer;

    private ShoppingCartService $service;

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

        Event::fake([CartItemAdded::class, CartItemRemoved::class]);

        $this->tenantId = 'cart-test-'.uniqid();
        DB::connection('central')->table('tenants')->insertOrIgnore([
            'id' => $this->tenantId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->user = User::on('central')->create([
            'name' => 'Cart Test Customer',
            'email' => 'cart-customer-'.uniqid().'@test.com',
            'password' => bcrypt('password'),
            'user_type' => 'customer',
        ]);

        $this->customer = MarketplaceCustomer::on('central')->create([
            'user_id' => $this->user->id,
            'customer_number' => 'MKT-'.uniqid(),
            'phone' => '0712'.rand(100000, 999999),
            'phone_verified' => true,
        ]);

        $this->service = new ShoppingCartService;
    }

    protected function tearDown(): void
    {
        DB::connection('central')->statement('SET foreign_key_checks = 0');

        $cartIds = ShoppingCart::on('central')->where('customer_id', $this->customer->id)
            ->orWhere('session_id', 'like', 'cart-sess-%')
            ->pluck('id');
        ShoppingCartItem::on('central')->whereIn('cart_id', $cartIds)->delete();
        ShoppingCart::on('central')->whereIn('id', $cartIds)->delete();
        MarketplaceProduct::on('central')->where('tenant_id', $this->tenantId)->forceDelete();
        MarketplaceCustomer::on('central')->where('id', $this->customer->id)->forceDelete();
        User::on('central')->where('id', $this->user->id)->forceDelete();
        DB::connection('central')->table('tenants')->where('id', $this->tenantId)->delete();

        DB::connection('central')->statement('SET foreign_key_checks = 1');
        parent::tearDown();
    }

    // =========================================================================
    // getOrCreateCart()
    // =========================================================================

    public function test_creates_new_guest_cart_when_session_id_provided_and_none_exists(): void
    {
        $sessionId = 'cart-sess-'.uniqid();
        $request = $this->requestWithSession($sessionId);

        $cart = $this->service->getOrCreateCart($request, null);

        $this->assertNull($cart->customer_id);
        $this->assertSame($sessionId, $cart->session_id);
        $this->assertTrue($cart->isActive());
    }

    public function test_creates_new_guest_cart_with_generated_session_id_when_no_header_sent(): void
    {
        $request = Request::create('/', 'GET');

        $cart = $this->service->getOrCreateCart($request, null);

        $this->assertNull($cart->customer_id);
        $this->assertNotEmpty($cart->session_id);

        ShoppingCart::on('central')->where('id', $cart->id)->delete();
    }

    public function test_returns_existing_guest_cart_for_same_session_id(): void
    {
        $sessionId = 'cart-sess-'.uniqid();
        $existing = $this->createCart(['session_id' => $sessionId]);

        $cart = $this->service->getOrCreateCart($this->requestWithSession($sessionId), null);

        $this->assertSame($existing->id, $cart->id);
    }

    public function test_returns_existing_customer_cart_when_authenticated(): void
    {
        $existing = $this->createCart(['customer_id' => $this->customer->id]);

        $cart = $this->service->getOrCreateCart(Request::create('/', 'GET'), $this->customer->id);

        $this->assertSame($existing->id, $cart->id);
    }

    public function test_customer_cart_takes_precedence_over_session_cart_when_both_exist(): void
    {
        $sessionId = 'cart-sess-'.uniqid();
        $customerCart = $this->createCart(['customer_id' => $this->customer->id]);
        $this->createCart(['session_id' => $sessionId]);

        $cart = $this->service->getOrCreateCart($this->requestWithSession($sessionId), $this->customer->id);

        $this->assertSame($customerCart->id, $cart->id);
    }

    public function test_falls_back_to_session_cart_when_authenticated_customer_has_no_cart_yet(): void
    {
        $sessionId = 'cart-sess-'.uniqid();
        $guestCart = $this->createCart(['session_id' => $sessionId]);

        $cart = $this->service->getOrCreateCart($this->requestWithSession($sessionId), $this->customer->id);

        $this->assertSame($guestCart->id, $cart->id);
        $this->assertNull($cart->customer_id);
    }

    // =========================================================================
    // addItem()
    // =========================================================================

    public function test_add_item_creates_new_cart_item(): void
    {
        $cart = $this->createCart();
        $product = $this->createProduct(['online_price' => 1000, 'available_quantity' => 10]);

        $item = $this->service->addItem($cart, [
            'marketplace_product_id' => $product->id,
            'quantity' => 2,
        ]);

        $this->assertSame($product->id, $item->marketplace_product_id);
        $this->assertEquals(2, (float) $item->quantity);
        $this->assertEquals(1000, (float) $item->unit_price);
        Event::assertDispatched(CartItemAdded::class, fn ($e) => $e->item->id === $item->id);
    }

    public function test_add_item_increments_quantity_when_product_already_in_cart(): void
    {
        $cart = $this->createCart();
        $product = $this->createProduct(['available_quantity' => 10]);
        $this->service->addItem($cart, ['marketplace_product_id' => $product->id, 'quantity' => 2]);

        $item = $this->service->addItem($cart, ['marketplace_product_id' => $product->id, 'quantity' => 3]);

        $this->assertEquals(5, (float) $item->quantity);
        $this->assertSame(1, $cart->items()->count());
    }

    public function test_add_item_throws_when_product_out_of_stock(): void
    {
        $cart = $this->createCart();
        $product = $this->createProduct(['stock_status' => 'out_of_stock', 'available_quantity' => 0]);

        $this->expectException(\RuntimeException::class);

        $this->service->addItem($cart, ['marketplace_product_id' => $product->id, 'quantity' => 1]);
    }

    public function test_add_item_succeeds_for_bundle_product_with_null_tenant_product_id(): void
    {
        // Bundles are synced into marketplace_products with tenant_product_id = null
        // (see MarketplaceBundleSyncService) — regression test for the NOT NULL
        // shopping_cart_items.tenant_product_id column that used to crash this.
        $cart = $this->createCart();
        $bundle = $this->createProduct([
            'tenant_product_type' => 'bundle',
            'tenant_product_id' => null,
            'tenant_bundle_id' => 42,
        ]);

        $item = $this->service->addItem($cart, ['marketplace_product_id' => $bundle->id, 'quantity' => 1]);

        $this->assertNull($item->tenant_product_id);
    }

    public function test_add_item_throws_for_inactive_product(): void
    {
        $cart = $this->createCart();
        $product = $this->createProduct(['is_active' => false]);

        $this->expectException(ModelNotFoundException::class);

        $this->service->addItem($cart, ['marketplace_product_id' => $product->id, 'quantity' => 1]);
    }

    // =========================================================================
    // updateItemQuantity()
    // =========================================================================

    public function test_update_item_quantity_updates_within_available_stock(): void
    {
        $cart = $this->createCart();
        $product = $this->createProduct(['available_quantity' => 10]);
        $item = $this->service->addItem($cart, ['marketplace_product_id' => $product->id, 'quantity' => 1]);

        $updated = $this->service->updateItemQuantity($cart, $item->id, 5);

        $this->assertEquals(5, (float) $updated->quantity);
    }

    public function test_update_item_quantity_throws_when_exceeding_available_stock(): void
    {
        $cart = $this->createCart();
        $product = $this->createProduct(['available_quantity' => 3]);
        $item = $this->service->addItem($cart, ['marketplace_product_id' => $product->id, 'quantity' => 1]);

        $this->expectException(\RuntimeException::class);

        $this->service->updateItemQuantity($cart, $item->id, 4);
    }

    public function test_update_item_quantity_throws_for_item_belonging_to_another_cart(): void
    {
        $cart = $this->createCart();
        $otherCart = $this->createCart();
        $product = $this->createProduct(['available_quantity' => 10]);
        $item = $this->service->addItem($otherCart, ['marketplace_product_id' => $product->id, 'quantity' => 1]);

        $this->expectException(ModelNotFoundException::class);

        $this->service->updateItemQuantity($cart, $item->id, 2);
    }

    // =========================================================================
    // removeItem() / clearCart()
    // =========================================================================

    public function test_remove_item_deletes_it_and_fires_event(): void
    {
        $cart = $this->createCart();
        $product = $this->createProduct();
        $item = $this->service->addItem($cart, ['marketplace_product_id' => $product->id, 'quantity' => 1]);

        $this->service->removeItem($cart, $item->id);

        $this->assertSame(0, $cart->items()->count());
        Event::assertDispatched(CartItemRemoved::class, fn ($e) => $e->removedProductId === $product->id);
    }

    public function test_remove_item_is_noop_for_nonexistent_item(): void
    {
        $cart = $this->createCart();

        $this->service->removeItem($cart, 999999);

        Event::assertNotDispatched(CartItemRemoved::class);
    }

    public function test_clear_cart_removes_all_items(): void
    {
        $cart = $this->createCart();
        $productA = $this->createProduct();
        $productB = $this->createProduct();
        $this->service->addItem($cart, ['marketplace_product_id' => $productA->id, 'quantity' => 1]);
        $this->service->addItem($cart, ['marketplace_product_id' => $productB->id, 'quantity' => 1]);

        $this->service->clearCart($cart);

        $this->assertSame(0, $cart->items()->count());
    }

    // =========================================================================
    // refreshPrices()
    // =========================================================================

    public function test_refresh_prices_flags_increased_price(): void
    {
        $cart = $this->createCart();
        $product = $this->createProduct(['online_price' => 100]);
        $item = $this->service->addItem($cart, ['marketplace_product_id' => $product->id, 'quantity' => 1]);
        $product->update(['online_price' => 150]);

        $result = $this->service->refreshPrices($cart);

        $this->assertCount(1, $result['changed']);
        $this->assertSame($item->id, $result['changed'][0]['item_id']);
        $this->assertEquals(50, $result['changed'][0]['difference']);
        $this->assertSame(0, $result['unchanged']);
    }

    public function test_refresh_prices_flags_decreased_price_too(): void
    {
        $cart = $this->createCart();
        $product = $this->createProduct(['online_price' => 100]);
        $this->service->addItem($cart, ['marketplace_product_id' => $product->id, 'quantity' => 1]);
        $product->update(['online_price' => 80]);

        $result = $this->service->refreshPrices($cart);

        $this->assertCount(1, $result['changed']);
        $this->assertEquals(-20, $result['changed'][0]['difference']);
    }

    public function test_refresh_prices_reports_unchanged_when_price_stable(): void
    {
        $cart = $this->createCart();
        $product = $this->createProduct(['online_price' => 100]);
        $this->service->addItem($cart, ['marketplace_product_id' => $product->id, 'quantity' => 1]);

        $result = $this->service->refreshPrices($cart);

        $this->assertSame([], $result['changed']);
        $this->assertSame(1, $result['unchanged']);
    }

    public function test_refresh_prices_skips_items_whose_product_was_deleted(): void
    {
        $cart = $this->createCart();
        $product = $this->createProduct();
        $this->service->addItem($cart, ['marketplace_product_id' => $product->id, 'quantity' => 1]);
        $product->forceDelete();

        $result = $this->service->refreshPrices($cart);

        $this->assertSame([], $result['changed']);
    }

    // =========================================================================
    // mergeGuestCartToCustomer()
    // =========================================================================

    public function test_merge_creates_new_customer_cart_when_neither_exists(): void
    {
        $sessionId = 'cart-sess-'.uniqid();

        $cart = $this->service->mergeGuestCartToCustomer($sessionId, $this->customer->id);

        $this->assertSame($this->customer->id, $cart->customer_id);
    }

    public function test_merge_returns_existing_customer_cart_when_no_guest_cart(): void
    {
        $customerCart = $this->createCart(['customer_id' => $this->customer->id]);

        $cart = $this->service->mergeGuestCartToCustomer('cart-sess-'.uniqid(), $this->customer->id);

        $this->assertSame($customerCart->id, $cart->id);
    }

    public function test_merge_assigns_guest_cart_to_customer_when_customer_has_no_cart(): void
    {
        $sessionId = 'cart-sess-'.uniqid();
        $guestCart = $this->createCart(['session_id' => $sessionId]);

        $cart = $this->service->mergeGuestCartToCustomer($sessionId, $this->customer->id);

        $this->assertSame($guestCart->id, $cart->id);
        $this->assertSame($this->customer->id, $cart->customer_id);
    }

    public function test_merge_moves_distinct_guest_items_into_customer_cart(): void
    {
        $sessionId = 'cart-sess-'.uniqid();
        $guestCart = $this->createCart(['session_id' => $sessionId]);
        $customerCart = $this->createCart(['customer_id' => $this->customer->id]);
        $product = $this->createProduct(['available_quantity' => 10]);
        $this->service->addItem($guestCart, ['marketplace_product_id' => $product->id, 'quantity' => 2]);

        $merged = $this->service->mergeGuestCartToCustomer($sessionId, $this->customer->id);

        $this->assertSame($customerCart->id, $merged->id);
        $this->assertSame(1, $merged->items()->count());
        $this->assertSame('expired', $guestCart->fresh()->status->value);
    }

    public function test_merge_keeps_the_higher_quantity_when_product_in_both_carts(): void
    {
        $sessionId = 'cart-sess-'.uniqid();
        $guestCart = $this->createCart(['session_id' => $sessionId]);
        $customerCart = $this->createCart(['customer_id' => $this->customer->id]);
        $product = $this->createProduct(['available_quantity' => 10]);
        $this->service->addItem($guestCart, ['marketplace_product_id' => $product->id, 'quantity' => 5]);
        $this->service->addItem($customerCart, ['marketplace_product_id' => $product->id, 'quantity' => 2]);

        $merged = $this->service->mergeGuestCartToCustomer($sessionId, $this->customer->id);

        $this->assertSame(1, $merged->items()->count());
        $this->assertEquals(5, (float) $merged->items()->first()->quantity);
    }

    // =========================================================================
    // getCartSummary()
    // =========================================================================

    public function test_get_cart_summary_groups_items_by_tenant(): void
    {
        $cart = $this->createCart();
        $productA = $this->createProduct(['online_price' => 100]);
        $otherTenantId = 'cart-test-other-'.uniqid();
        DB::connection('central')->table('tenants')->insertOrIgnore([
            'id' => $otherTenantId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $productB = $this->createProduct(['online_price' => 200, 'tenant_id' => $otherTenantId]);
        $this->service->addItem($cart, ['marketplace_product_id' => $productA->id, 'quantity' => 1]);
        $this->service->addItem($cart, ['marketplace_product_id' => $productB->id, 'quantity' => 1]);

        $summary = $this->service->getCartSummary($cart);

        $this->assertSame(2, $summary['item_count']);
        $this->assertEquals(300, $summary['subtotal']);
        $this->assertCount(2, $summary['tenant_groups']);

        MarketplaceProduct::on('central')->where('id', $productB->id)->forceDelete();
        DB::connection('central')->table('tenants')->where('id', $otherTenantId)->delete();
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function requestWithSession(string $sessionId): Request
    {
        return Request::create('/', 'GET', [], [], [], [
            'HTTP_X_CART_SESSION_ID' => $sessionId,
        ]);
    }

    private function createCart(array $overrides = []): ShoppingCart
    {
        return ShoppingCart::on('central')->create(array_merge([
            'session_id' => 'cart-sess-'.uniqid(),
            'status' => 'active',
        ], $overrides));
    }

    private function createProduct(array $overrides = []): MarketplaceProduct
    {
        return MarketplaceProduct::on('central')->create(array_merge([
            'tenant_id' => $this->tenantId,
            'name' => 'Test Product',
            'slug' => 'test-product-'.uniqid(),
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
}
