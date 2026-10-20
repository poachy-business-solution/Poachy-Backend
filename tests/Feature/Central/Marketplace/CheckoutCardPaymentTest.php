<?php

namespace Tests\Feature\Central\Marketplace;

use App\Enums\Central\MarketplacePaymentStatus;
use App\Enums\Central\OrderStatus;
use App\Jobs\Central\ProcessCheckoutReservation;
use App\Jobs\Central\ProcessPaymentConfirmation;
use App\Models\BusinessDetail;
use App\Models\CustomerAddress;
use App\Models\MarketplaceCustomer;
use App\Models\MarketplaceOrder;
use App\Models\MarketplaceProduct;
use App\Models\ShoppingCart;
use App\Models\SyncQueueOutbound;
use App\Models\User;
use App\Services\Shared\Card\Gateways\FakeCardGateway;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Item 34: cart → checkout → pay, over HTTP with the fake gateways. Card (Paystack): initialize
 * sends the buyer to a page; only a correctly signed webhook (or a verify on return) gives value,
 * after the gateway's own record is checked; replays are harmless. M-Pesa STK goes through the
 * fake driver and the simulated callback.
 */
class CheckoutCardPaymentTest extends TestCase
{
    private string $run;

    private string $tenantId;

    private User $user;

    private MarketplaceCustomer $customer;

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
        Config::set('services.central_api.token', 'central-test-token');
        Config::set('paystack.driver', 'fake');
        Config::set('paystack.secret_key', 'sk_test_fake');
        Config::set('mpesa.driver', 'fake');
        Config::set('app.frontend_url', 'https://web.example');
        DB::purge('central');
        DB::setDefaultConnection('central');
        DB::connection('central')->statement('SET foreign_key_checks = 0');
        Queue::fake();

        $this->run = strtolower(uniqid());
        $this->tenantId = "checkout-card-{$this->run}";
        DB::connection('central')->table('tenants')->insert(['id' => $this->tenantId, 'created_at' => now(), 'updated_at' => now()]);
        BusinessDetail::on('central')->create(['tenant_id' => $this->tenantId, 'business_name' => 'Card Duka '.$this->run, 'business_phone' => '+254700000'.random_int(100, 999),
            'business_type_id' => DB::connection('central')->table('business_types')->value('id'),
            'business_category_id' => DB::connection('central')->table('business_categories')->value('id'),
            'city' => 'Nairobi', 'county' => 'Nairobi', 'status' => 'active', 'is_verified' => true, 'verified_at' => now()]);
        $this->user = User::on('central')->create(['name' => 'Card Buyer', 'email' => "card-buyer-{$this->run}@example.com", 'password' => bcrypt('Password123'), 'user_type' => 'customer']);
        $this->customer = MarketplaceCustomer::on('central')->create(['user_id' => $this->user->id, 'customer_number' => 'MKT-'.$this->run, 'phone' => '+2547'.random_int(10000000, 99999999), 'is_active' => true, 'phone_verified' => true]);
        $this->product = MarketplaceProduct::on('central')->create([
            'tenant_id' => $this->tenantId, 'tenant_product_id' => random_int(1000, 9999), 'name' => 'Block heels', 'slug' => 'block-heels-'.$this->run, 'sku' => 'SKU-'.$this->run,
            'online_price' => 2500, 'base_uom_code' => 'pair', 'base_uom_name' => 'Pair', 'tax_rate' => 0, 'available_quantity' => 5, 'stock_status' => 'in_stock', 'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        $orderIds = MarketplaceOrder::on('central')->where('tenant_id', $this->tenantId)->pluck('id');
        DB::connection('central')->table('marketplace_order_items')->whereIn('order_id', $orderIds)->delete();
        DB::connection('central')->table('marketplace_order_payments')->whereIn('order_id', $orderIds)->delete();
        DB::connection('central')->table('marketplace_order_deliveries')->whereIn('order_id', $orderIds)->delete();
        MarketplaceOrder::on('central')->whereIn('id', $orderIds)->forceDelete();
        SyncQueueOutbound::on('central')->where('tenant_id', $this->tenantId)->delete();
        $cartIds = ShoppingCart::on('central')->where('customer_id', $this->customer->id)->pluck('id');
        DB::connection('central')->table('shopping_cart_items')->whereIn('cart_id', $cartIds)->delete();
        ShoppingCart::on('central')->whereIn('id', $cartIds)->delete();
        MarketplaceProduct::on('central')->where('tenant_id', $this->tenantId)->forceDelete();
        BusinessDetail::on('central')->where('tenant_id', $this->tenantId)->forceDelete();
        $this->customer->forceDelete();
        $this->user->forceDelete();
        DB::connection('central')->table('tenants')->where('id', $this->tenantId)->delete();
        DB::connection('central')->statement('SET foreign_key_checks = 1');
        parent::tearDown();
    }

    public function test_card_checkout_pays_only_through_a_signed_and_verified_webhook(): void
    {
        $order = $this->checkout('card', ref: 'IG');
        $this->assertSame('ig', $order->ref);
        $this->assertSame('own_link', $order->source());
        $this->assertSame('card', $order->payments()->first()->payment_method->value);
        Queue::assertPushed(ProcessCheckoutReservation::class, fn ($job) => $job->orderId === $order->id); // the hold goes to the shop

        // Nothing can be paid until the shop has held the stock.
        $this->postJson("/api/v1/central/marketplace/orders/{$order->id}/payment", [])->assertStatus(422);
        $this->shopConfirms($order);

        // Initialize: the fake gateway hands back a page; re-initializing returns the same page.
        $first = $this->postJson("/api/v1/central/marketplace/orders/{$order->id}/payment", [])->assertOk()->json('data');
        $this->assertStringStartsWith('PCO-'.$order->order_number.'-', $first['instructions']['reference']);
        $this->assertNotEmpty($first['instructions']['authorization_url']);
        $this->assertSame('processing', $first['payment']['payment_status']);
        $this->assertSame($first['instructions']['authorization_url'], $first['payment']['authorization_url']);
        $again = $this->postJson("/api/v1/central/marketplace/orders/{$order->id}/payment", [])->assertOk()->json('data');
        $this->assertSame($first['instructions']['reference'], $again['instructions']['reference']);
        $reference = $first['instructions']['reference'];
        $tx = FakeCardGateway::find($reference);
        $this->assertEquals(2500, $tx['amount']);
        $this->assertStringContainsString('/account/orders/'.$order->order_number.'/paid', $tx['callback_url'] ?? '');

        // A webhook with a bad signature is refused and changes nothing; a verify before paying is still processing.
        $body = $this->webhookBody($reference, 'success');
        $this->call('POST', '/api/v1/central/marketplace/payments/paystack/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_PAYSTACK_SIGNATURE' => 'nope'], $body)->assertUnauthorized();
        $this->assertSame('processing', $order->payments()->first()->fresh()->payment_status->value);
        $this->getJson("/api/v1/central/marketplace/orders/{$order->id}/payment?verify=1")->assertOk()->assertJsonPath('data.payment_status', 'processing');

        // The buyer pays on the gateway (simulated): the signed webhook gives value, after verify.
        Artisan::call('poachy:simulate-paystack', ['reference' => $reference]);
        $payment = $order->payments()->first()->fresh();
        $this->assertSame(MarketplacePaymentStatus::Completed, $payment->payment_status);
        $this->assertSame('paystack', $payment->payment_provider);
        $this->assertSame(OrderStatus::Confirmed, $order->fresh()->order_status);
        Queue::assertPushed(ProcessPaymentConfirmation::class, 1);

        // Replays are harmless.
        Artisan::call('poachy:simulate-paystack', ['reference' => $reference]);
        Queue::assertPushed(ProcessPaymentConfirmation::class, 1);
        $this->getJson("/api/v1/central/marketplace/orders/{$order->id}/payment?verify=1")->assertOk()->assertJsonPath('data.payment_status', 'completed')->assertJsonPath('data.authorization_url', null);
    }

    public function test_declined_card_and_amount_tampering_never_confirm_the_order(): void
    {
        $order = $this->checkout('card');
        $this->shopConfirms($order);
        $reference = $this->postJson("/api/v1/central/marketplace/orders/{$order->id}/payment", [])->assertOk()->json('data.instructions.reference');

        Artisan::call('poachy:simulate-paystack', ['reference' => $reference, '--fail' => true]);
        $payment = $order->payments()->first()->fresh();
        $this->assertSame(MarketplacePaymentStatus::Failed, $payment->payment_status);
        $this->assertSame(OrderStatus::Pending, $order->fresh()->order_status);
        Queue::assertNotPushed(ProcessPaymentConfirmation::class);

        // A "success" webhook whose gateway record says a different amount is not trusted.
        $second = $this->checkout('card');
        $this->shopConfirms($second);
        $ref2 = $this->postJson("/api/v1/central/marketplace/orders/{$second->id}/payment", [])->assertOk()->json('data.instructions.reference');
        $tx = FakeCardGateway::find($ref2);
        $tx['amount'] = 25;
        $tx['status'] = 'success';
        $tx['paid_at'] = now()->toIso8601String();
        FakeCardGateway::put($ref2, $tx);
        $body = $this->webhookBody($ref2, 'success');
        $this->call('POST', '/api/v1/central/marketplace/payments/paystack/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_PAYSTACK_SIGNATURE' => FakeCardGateway::sign($body)], $body)->assertOk();
        $this->assertSame('AMOUNT_MISMATCH', $second->payments()->first()->fresh()->failure_code);
        Queue::assertNotPushed(ProcessPaymentConfirmation::class);

        // Unknown references and other events are acknowledged and ignored.
        $body = $this->webhookBody('PCO-NOPE', 'success');
        $this->call('POST', '/api/v1/central/marketplace/payments/paystack/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_PAYSTACK_SIGNATURE' => FakeCardGateway::sign($body)], $body)->assertOk();
    }

    public function test_mpesa_stk_checkout_pays_through_the_fake_prompt_and_its_callback(): void
    {
        $order = $this->checkout('mpesa');
        $this->shopConfirms($order);
        $this->postJson("/api/v1/central/marketplace/orders/{$order->id}/payment", [])->assertStatus(422); // phone needed
        $data = $this->postJson("/api/v1/central/marketplace/orders/{$order->id}/payment", ['phone_number' => '0722000111'])->assertOk()->json('data');
        $this->assertSame('processing', $data['payment']['payment_status']);
        $checkoutRequestId = $data['instructions']['checkout_request_id'];

        Artisan::call('poachy:simulate-mpesa', ['type' => 'stk', 'id' => $checkoutRequestId]);
        $this->assertSame(MarketplacePaymentStatus::Completed, $order->payments()->first()->fresh()->payment_status);
        $this->assertSame(OrderStatus::Confirmed, $order->fresh()->order_status);
        Queue::assertPushed(ProcessPaymentConfirmation::class, 1);
    }

    public function test_cash_and_bank_transfer_are_not_offered_online(): void
    {
        Sanctum::actingAs($this->user, ['*'], 'central');
        $session = (string) Str::uuid();
        $this->postJson('/api/v1/central/marketplace/cart/items', ['marketplace_product_id' => $this->product->id, 'quantity' => 1], ['X-Cart-Session-Id' => $session])->assertSuccessful();
        $this->postJson('/api/v1/central/marketplace/checkout', ['fulfillment_type' => 'pickup', 'payment_method' => 'cash_on_delivery'], ['X-Cart-Session-Id' => $session, 'Idempotency-Key' => (string) Str::uuid()])
            ->assertUnprocessable()->assertJsonValidationErrors('payment_method');
        $this->postJson('/api/v1/central/marketplace/checkout', ['fulfillment_type' => 'pickup', 'payment_method' => 'card', 'ref' => 'bad ref!'], ['X-Cart-Session-Id' => $session, 'Idempotency-Key' => (string) Str::uuid()])
            ->assertUnprocessable()->assertJsonValidationErrors('ref');
    }

    public function test_the_cart_shows_the_vat_checkout_will_charge(): void
    {
        $this->product->update(['tax_rate' => 16]);
        Sanctum::actingAs($this->user, ['*'], 'central');

        // Item 48: no VAT until the shop switches "Charge VAT" on.
        $plain = (string) Str::uuid();
        $this->postJson('/api/v1/central/marketplace/cart/items', ['marketplace_product_id' => $this->product->id, 'quantity' => 2], ['X-Cart-Session-Id' => $plain])
            ->assertSuccessful()->assertJsonPath('data.tax_rate', 0)->assertJsonPath('data.tax_amount', 0);
        $this->getJson('/api/v1/central/marketplace/cart', ['X-Cart-Session-Id' => $plain])->assertOk()->assertJsonPath('data.total', 5000);
        $this->deleteJson('/api/v1/central/marketplace/cart', [], ['X-Cart-Session-Id' => $plain])->assertSuccessful();

        BusinessDetail::on('central')->where('tenant_id', $this->tenantId)->update(['charge_vat' => true]);
        $session = (string) Str::uuid();
        $this->postJson('/api/v1/central/marketplace/cart/items', ['marketplace_product_id' => $this->product->id, 'quantity' => 2], ['X-Cart-Session-Id' => $session])
            ->assertSuccessful()->assertJsonPath('data.tax_rate', 16)->assertJsonPath('data.tax_amount', 800);
        $this->getJson('/api/v1/central/marketplace/cart', ['X-Cart-Session-Id' => $session])->assertOk()
            ->assertJsonPath('data.subtotal', 5000)->assertJsonPath('data.tax_amount', 800)->assertJsonPath('data.total', 5800)
            ->assertJsonPath('data.tenant_groups.0.tax_amount', 800);

        $orders = $this->postJson('/api/v1/central/marketplace/checkout', ['fulfillment_type' => 'pickup', 'payment_method' => 'mpesa'], ['X-Cart-Session-Id' => $session, 'Idempotency-Key' => (string) Str::uuid()])
            ->assertCreated()->json('data');
        $this->assertEquals(5800, $orders[0]['total_amount']);
    }

    public function test_checkout_keys_addresses_and_delivery_fees_are_scoped_to_the_buyer(): void
    {
        $address = CustomerAddress::on('central')->create(['customer_id' => $this->customer->id, 'recipient_name' => 'Card Buyer', 'recipient_phone' => '0722000111',
            'address_line' => 'Moi Avenue 1', 'city' => 'Nairobi', 'county' => 'Nairobi']);
        $otherUser = User::on('central')->create(['name' => 'Other Buyer', 'email' => "other-buyer-{$this->run}@example.com", 'password' => bcrypt('Password123'), 'user_type' => 'customer']);
        $other = MarketplaceCustomer::on('central')->create(['user_id' => $otherUser->id, 'customer_number' => 'MKT-O-'.$this->run, 'phone' => '+2547'.random_int(10000000, 99999999), 'is_active' => true, 'phone_verified' => true]);

        try {
            $mine = $this->checkout('mpesa');
            $key = $mine->checkout_idempotency_key;

            // Another buyer replaying my key gets their own checkout, never my orders.
            Sanctum::actingAs($otherUser, ['*'], 'central');
            $session = (string) Str::uuid();
            $this->postJson('/api/v1/central/marketplace/cart/items', ['marketplace_product_id' => $this->product->id, 'quantity' => 1], ['X-Cart-Session-Id' => $session])->assertSuccessful();
            $theirs = $this->postJson('/api/v1/central/marketplace/checkout', ['fulfillment_type' => 'pickup', 'payment_method' => 'mpesa'], ['X-Cart-Session-Id' => $session, 'Idempotency-Key' => $key])->assertCreated()->json('data');
            $this->assertNotContains($mine->id, array_column($theirs, 'id'));
            $this->assertSame(1, MarketplaceOrder::on('central')->where('checkout_idempotency_key', $key)->where('customer_id', $this->customer->id)->count());
            $this->assertSame(1, MarketplaceOrder::on('central')->where('checkout_idempotency_key', $key)->where('customer_id', $other->id)->count());

            // My address can't be used or priced by someone else.
            $session = (string) Str::uuid(); // a converted cart's session id is spent
            $this->postJson('/api/v1/central/marketplace/cart/items', ['marketplace_product_id' => $this->product->id, 'quantity' => 1], ['X-Cart-Session-Id' => $session])->assertSuccessful();
            $this->postJson('/api/v1/central/marketplace/checkout', ['fulfillment_type' => 'delivery', 'delivery_method' => 'standard', 'delivery_address_id' => $address->id, 'payment_method' => 'mpesa'],
                ['X-Cart-Session-Id' => $session, 'Idempotency-Key' => (string) Str::uuid()])->assertUnprocessable()->assertJsonValidationErrors('delivery_address_id');
            $this->postJson('/api/v1/central/marketplace/delivery/preview', ['tenant_id' => $this->tenantId, 'delivery_address_id' => $address->id])->assertNotFound();

            // The owner may price it; signed out may not.
            Sanctum::actingAs($this->user, ['*'], 'central');
            $this->postJson('/api/v1/central/marketplace/delivery/preview', ['tenant_id' => $this->tenantId, 'delivery_address_id' => $address->id])->assertOk()->assertJsonPath('data.address.id', $address->id);
            $this->app['auth']->forgetGuards();
            $this->postJson('/api/v1/central/marketplace/delivery/preview', ['tenant_id' => $this->tenantId, 'delivery_address_id' => $address->id])->assertUnauthorized();
        } finally {
            $cartIds = ShoppingCart::on('central')->where('customer_id', $other->id)->pluck('id');
            DB::connection('central')->table('shopping_cart_items')->whereIn('cart_id', $cartIds)->delete();
            ShoppingCart::on('central')->whereIn('id', $cartIds)->delete();
            $address->forceDelete();
            $other->forceDelete();
            $otherUser->forceDelete();
        }
    }

    private function checkout(string $method, ?string $ref = null): MarketplaceOrder
    {
        Sanctum::actingAs($this->user, ['*'], 'central');
        $session = (string) Str::uuid();
        $this->postJson('/api/v1/central/marketplace/cart/items', ['marketplace_product_id' => $this->product->id, 'quantity' => 1], ['X-Cart-Session-Id' => $session])->assertSuccessful();
        $key = (string) Str::uuid();
        $response = $this->postJson('/api/v1/central/marketplace/checkout', array_filter(['fulfillment_type' => 'pickup', 'payment_method' => $method, 'ref' => $ref]), ['X-Cart-Session-Id' => $session, 'Idempotency-Key' => $key])->assertSuccessful();
        $orders = $response->json('data.orders') ?? $response->json('data');
        $orderId = $orders[0]['id'] ?? $orders['id'];

        // Same key → same order.
        $this->postJson('/api/v1/central/marketplace/checkout', array_filter(['fulfillment_type' => 'pickup', 'payment_method' => $method, 'ref' => $ref]), ['X-Cart-Session-Id' => $session, 'Idempotency-Key' => $key])->assertSuccessful();
        $this->assertSame(1, MarketplaceOrder::on('central')->where('checkout_idempotency_key', $key)->count());

        return MarketplaceOrder::on('central')->findOrFail($orderId);
    }

    /** The shop's reservation answer, as the tenant job sends it. */
    private function shopConfirms(MarketplaceOrder $order): void
    {
        $this->withToken('central-test-token')->postJson('/api/v1/central/sync/inbound/order-confirmation', [
            'tenant_id' => $this->tenantId, 'order_id' => $order->id, 'status' => 'confirmed',
        ])->assertStatus(202);
        // The job is faked; apply what it would do.
        $order->forceFill(['reservation_status' => 'confirmed', 'reservation_confirmed_at' => now()])->save();
        Sanctum::actingAs($this->user, ['*'], 'central');
    }

    private function webhookBody(string $reference, string $status): string
    {
        return json_encode(['event' => $status === 'success' ? 'charge.success' : 'charge.failed', 'data' => ['id' => 123, 'status' => $status, 'reference' => $reference, 'amount' => 250000, 'currency' => 'KES']]);
    }
}
