<?php

namespace Tests\Feature\Central\Marketplace;

use App\Enums\Central\FulfillmentType;
use App\Enums\Central\MarketplacePaymentStatus;
use App\Enums\Central\OrderStatus;
use App\Events\Central\Marketplace\CheckoutCompleted;
use App\Jobs\Central\ProcessCheckoutReservation;
use App\Models\CustomerAddress;
use App\Models\MarketplaceCustomer;
use App\Models\MarketplaceOrder;
use App\Models\MarketplaceOrderDelivery;
use App\Models\MarketplaceOrderItem;
use App\Models\MarketplaceOrderPayment;
use App\Models\MarketplaceProduct;
use App\Models\ShoppingCart;
use App\Models\ShoppingCartItem;
use App\Models\User;
use App\Services\Central\Marketplace\CheckoutService;
use App\Services\Central\Marketplace\DeliveryFeeService;
use App\Services\Central\Marketplace\ShoppingCartService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CheckoutServiceTest extends TestCase
{
    private string $tenantId;

    private string $tenantIdTwo;

    private User $user;

    private MarketplaceCustomer $customer;

    private CheckoutService $service;

    private ShoppingCartService $cartService;

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

        Queue::fake();
        Event::fake([CheckoutCompleted::class]);

        $this->tenantId = 'checkout-test-'.uniqid();
        $this->tenantIdTwo = 'checkout-test-2-'.uniqid();
        DB::connection('central')->table('tenants')->insertOrIgnore([
            ['id' => $this->tenantId, 'created_at' => now(), 'updated_at' => now()],
            ['id' => $this->tenantIdTwo, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->user = User::on('central')->create([
            'name' => 'Checkout Test Customer',
            'email' => 'checkout-customer-'.uniqid().'@test.com',
            'password' => bcrypt('password'),
            'user_type' => 'customer',
        ]);

        $this->customer = MarketplaceCustomer::on('central')->create([
            'user_id' => $this->user->id,
            'customer_number' => 'MKT-'.uniqid(),
            'phone' => '0712'.rand(100000, 999999),
            'phone_verified' => true,
        ]);

        $this->cartService = new ShoppingCartService();
        $this->service = new CheckoutService($this->cartService, new DeliveryFeeService());
    }

    protected function tearDown(): void
    {
        DB::connection('central')->statement('SET foreign_key_checks = 0');

        $orderIds = MarketplaceOrder::on('central')
            ->whereIn('tenant_id', [$this->tenantId, $this->tenantIdTwo])
            ->pluck('id');
        MarketplaceOrderItem::on('central')->whereIn('order_id', $orderIds)->delete();
        MarketplaceOrderPayment::on('central')->whereIn('order_id', $orderIds)->delete();
        MarketplaceOrderDelivery::on('central')->whereIn('order_id', $orderIds)->delete();
        MarketplaceOrder::on('central')->whereIn('id', $orderIds)->forceDelete();

        $cartIds = ShoppingCart::on('central')->where('customer_id', $this->customer->id)->pluck('id');
        ShoppingCartItem::on('central')->whereIn('cart_id', $cartIds)->delete();
        ShoppingCart::on('central')->whereIn('id', $cartIds)->delete();

        CustomerAddress::on('central')->where('customer_id', $this->customer->id)->delete();
        MarketplaceProduct::on('central')->whereIn('tenant_id', [$this->tenantId, $this->tenantIdTwo])->forceDelete();
        MarketplaceCustomer::on('central')->where('id', $this->customer->id)->forceDelete();
        User::on('central')->where('id', $this->user->id)->forceDelete();
        DB::connection('central')->table('tenants')->whereIn('id', [$this->tenantId, $this->tenantIdTwo])->delete();

        DB::connection('central')->statement('SET foreign_key_checks = 1');
        parent::tearDown();
    }

    // =========================================================================
    // initiateCheckout() — idempotency & locking
    // =========================================================================

    public function test_initiate_checkout_returns_existing_orders_for_reused_idempotency_key(): void
    {
        $cart = $this->cartWithItem();
        $key = 'idem-'.uniqid();

        $firstRun = $this->service->initiateCheckout($cart, $this->checkoutData(['idempotency_key' => $key]));
        $secondRun = $this->service->initiateCheckout($cart->fresh(), $this->checkoutData(['idempotency_key' => $key]));

        $this->assertSame($firstRun->pluck('id')->all(), $secondRun->pluck('id')->all());
        $this->assertSame(1, MarketplaceOrder::on('central')->where('checkout_idempotency_key', $key)->count());
    }

    public function test_initiate_checkout_throws_when_lock_already_held(): void
    {
        $cart = $this->cartWithItem();
        $key = 'idem-locked-'.uniqid();
        $lock = Cache::lock("checkout:lock:{$key}", 60);
        $lock->get();

        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('checkout_in_progress');

            $this->service->initiateCheckout($cart, $this->checkoutData(['idempotency_key' => $key]));
        } finally {
            $lock->release();
        }
    }

    // =========================================================================
    // processCheckout() — validation
    // =========================================================================

    public function test_checkout_throws_when_cart_is_empty(): void
    {
        $cart = $this->createCart();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cart is empty.');

        $this->service->initiateCheckout($cart, $this->checkoutData());
    }

    public function test_checkout_throws_when_cart_not_active(): void
    {
        $cart = $this->cartWithItem();
        $cart->markAsConverted();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cart is no longer active.');

        $this->service->initiateCheckout($cart, $this->checkoutData());
    }

    public function test_checkout_halts_when_a_price_increased(): void
    {
        $cart = $this->cartWithItem(['online_price' => 100], quantity: 1, priceAfterAdd: 150);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('prices have increased');

        $this->service->initiateCheckout($cart, $this->checkoutData());
    }

    public function test_checkout_proceeds_when_a_price_decreased(): void
    {
        $cart = $this->cartWithItem(['online_price' => 100], quantity: 1, priceAfterAdd: 80);

        $orders = $this->service->initiateCheckout($cart, $this->checkoutData());

        $this->assertCount(1, $orders);
        $this->assertEquals(80, (float) $orders->first()->items->first()->unit_price);
    }

    public function test_checkout_throws_when_stock_insufficient(): void
    {
        $cart = $this->createCart();
        $product = $this->createProduct(['available_quantity' => 1]);
        $this->cartService->addItem($cart, ['marketplace_product_id' => $product->id, 'quantity' => 1]);
        $product->update(['available_quantity' => 0, 'stock_status' => 'out_of_stock']);

        $this->expectException(\RuntimeException::class);

        $this->service->initiateCheckout($cart, $this->checkoutData());
    }

    public function test_checkout_throws_for_delivery_without_address_id(): void
    {
        $cart = $this->cartWithItem();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('A delivery address is required');

        $this->service->initiateCheckout($cart, $this->checkoutData([
            'fulfillment_type' => 'delivery',
            'delivery_address_id' => null,
        ]));
    }

    public function test_checkout_throws_when_delivery_address_not_found(): void
    {
        $cart = $this->cartWithItem();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('could not be found');

        $this->service->initiateCheckout($cart, $this->checkoutData([
            'fulfillment_type' => 'delivery',
            'delivery_address_id' => 999999,
        ]));
    }

    // =========================================================================
    // processCheckout() — happy paths
    // =========================================================================

    public function test_checkout_creates_pickup_order_with_pending_payment_and_no_delivery_record(): void
    {
        $cart = $this->cartWithItem(['online_price' => 500, 'tax_rate' => 16], quantity: 2);

        $orders = $this->service->initiateCheckout($cart, $this->checkoutData());

        $this->assertCount(1, $orders);
        $order = $orders->first();
        $this->assertSame(OrderStatus::Pending, $order->order_status);
        $this->assertEquals(1000, (float) $order->subtotal);
        $this->assertEquals(160, (float) $order->tax_amount);
        $this->assertEquals(1160, (float) $order->total_amount);
        $this->assertEquals(0, (float) $order->delivery_fee);
        $this->assertNull($order->delivery);
        $this->assertSame(1, $order->payments()->count());
        $this->assertSame(MarketplacePaymentStatus::Pending, $order->payments()->first()->payment_status);
        $this->assertTrue($cart->fresh()->status->value === 'converted');
    }

    public function test_checkout_creates_delivery_order_with_zero_fee_when_zones_not_configured(): void
    {
        $address = CustomerAddress::on('central')->create($this->addressData());
        $cart = $this->cartWithItem();

        $orders = $this->service->initiateCheckout($cart, $this->checkoutData([
            'fulfillment_type' => 'delivery',
            'delivery_address_id' => $address->id,
        ]));

        $order = $orders->first();
        $this->assertSame(FulfillmentType::Delivery, $order->fulfillment_type);
        $this->assertEquals(0, (float) $order->delivery_fee);
        $this->assertNotNull($order->delivery);
    }

    public function test_checkout_splits_multi_tenant_cart_into_separate_orders(): void
    {
        $cart = $this->createCart();
        $productA = $this->createProduct(['online_price' => 100, 'tenant_id' => $this->tenantId]);
        $productB = $this->createProduct(['online_price' => 200, 'tenant_id' => $this->tenantIdTwo]);
        $this->cartService->addItem($cart, ['marketplace_product_id' => $productA->id, 'quantity' => 1]);
        $this->cartService->addItem($cart, ['marketplace_product_id' => $productB->id, 'quantity' => 1]);

        $orders = $this->service->initiateCheckout($cart, $this->checkoutData());

        $this->assertCount(2, $orders);
        $this->assertEqualsCanonicalizing(
            [$this->tenantId, $this->tenantIdTwo],
            $orders->pluck('tenant_id')->all(),
        );
    }

    public function test_checkout_dispatches_reservation_job_per_order_and_fires_event(): void
    {
        $cart = $this->createCart();
        $productA = $this->createProduct(['tenant_id' => $this->tenantId]);
        $productB = $this->createProduct(['tenant_id' => $this->tenantIdTwo]);
        $this->cartService->addItem($cart, ['marketplace_product_id' => $productA->id, 'quantity' => 1]);
        $this->cartService->addItem($cart, ['marketplace_product_id' => $productB->id, 'quantity' => 1]);

        $orders = $this->service->initiateCheckout($cart, $this->checkoutData());

        Queue::assertPushed(ProcessCheckoutReservation::class, $orders->count());
        Event::assertDispatched(CheckoutCompleted::class, fn ($e) => $e->orders->count() === $orders->count());
    }

    public function test_checkout_succeeds_for_bundle_product_with_null_tenant_product_id(): void
    {
        $cart = $this->createCart();
        $bundle = $this->createProduct([
            'tenant_product_type' => 'bundle',
            'tenant_product_id' => null,
            'tenant_bundle_id' => 7,
        ]);
        $this->cartService->addItem($cart, ['marketplace_product_id' => $bundle->id, 'quantity' => 1]);

        $orders = $this->service->initiateCheckout($cart, $this->checkoutData());

        $this->assertNull($orders->first()->items->first()->tenant_product_id);
        $this->assertSame(7, $orders->first()->items->first()->tenant_bundle_id);
    }

    // =========================================================================
    // validateCheckoutEligibility()
    // =========================================================================

    public function test_validate_eligibility_returns_eligible_for_valid_cart(): void
    {
        $cart = $this->cartWithItem();

        $result = $this->service->validateCheckoutEligibility($cart, []);

        $this->assertTrue($result['eligible']);
        $this->assertSame([], $result['issues']);
    }

    public function test_validate_eligibility_reports_out_of_stock_issue(): void
    {
        $cart = $this->createCart();
        $product = $this->createProduct(['available_quantity' => 5]);
        $this->cartService->addItem($cart, ['marketplace_product_id' => $product->id, 'quantity' => 1]);
        $product->update(['stock_status' => 'out_of_stock']);

        $result = $this->service->validateCheckoutEligibility($cart, []);

        $this->assertFalse($result['eligible']);
        $this->assertNotEmpty($result['issues']);
    }

    public function test_validate_eligibility_reports_price_change_without_throwing(): void
    {
        $cart = $this->createCart();
        $product = $this->createProduct(['online_price' => 100]);
        $this->cartService->addItem($cart, ['marketplace_product_id' => $product->id, 'quantity' => 1]);
        $product->update(['online_price' => 300]);

        $result = $this->service->validateCheckoutEligibility($cart, []);

        $this->assertFalse($result['eligible']);
        $this->assertStringContainsString('Price changed', $result['issues'][0]);
    }

    // =========================================================================
    // calculateOrderTotals()
    // =========================================================================

    public function test_calculate_order_totals_sums_subtotal_and_tax(): void
    {
        $cart = $this->createCart();
        $product = $this->createProduct(['online_price' => 250, 'tax_rate' => 16]);
        $item = $this->cartService->addItem($cart, ['marketplace_product_id' => $product->id, 'quantity' => 3]);

        $totals = $this->service->calculateOrderTotals(collect([$item]));

        $this->assertEquals(750, $totals['subtotal']);
        $this->assertEquals(120, $totals['tax_amount']);
        $this->assertEquals(870, $totals['total_amount']);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function createCart(): ShoppingCart
    {
        return ShoppingCart::on('central')->create([
            'customer_id' => $this->customer->id,
            'session_id' => 'checkout-sess-'.uniqid(),
            'status' => 'active',
        ]);
    }

    private function createProduct(array $overrides = []): MarketplaceProduct
    {
        return MarketplaceProduct::on('central')->create(array_merge([
            'tenant_id' => $this->tenantId,
            'name' => 'Checkout Test Product',
            'slug' => 'checkout-test-product-'.uniqid(),
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

    private function cartWithItem(array $productOverrides = [], float $quantity = 1, ?float $priceAfterAdd = null): ShoppingCart
    {
        $cart = $this->createCart();
        $product = $this->createProduct($productOverrides);
        $this->cartService->addItem($cart, ['marketplace_product_id' => $product->id, 'quantity' => $quantity]);

        if ($priceAfterAdd !== null) {
            $product->update(['online_price' => $priceAfterAdd]);
        }

        return $cart->fresh();
    }

    private function checkoutData(array $overrides = []): array
    {
        return array_merge([
            'idempotency_key' => 'idem-'.uniqid(),
            'fulfillment_type' => 'pickup',
            'payment_method' => 'mpesa',
        ], $overrides);
    }

    private function addressData(): array
    {
        return [
            'customer_id' => $this->customer->id,
            'recipient_name' => 'Test Recipient',
            'recipient_phone' => '0712345678',
            'address_line' => '123 Test Street',
            'city' => 'Nairobi',
            'county' => 'Nairobi',
        ];
    }
}
