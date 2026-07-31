<?php

namespace Tests\Feature\Central\Marketplace;

use App\Enums\Central\DeliveryMethod;
use App\Enums\Central\DeliveryStatus;
use App\Enums\Central\OrderStatus;
use App\Models\MarketplaceCustomer;
use App\Models\MarketplaceOrder;
use App\Models\MarketplaceOrderDelivery;
use App\Models\User;
use App\Services\Central\Marketplace\MarketplaceDeliveryService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MarketplaceDeliveryServiceTest extends TestCase
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

        $this->tenantId = 'marketplace-delivery-test-'.uniqid();
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
        MarketplaceOrderDelivery::on('central')->whereHas('order', fn ($q) => $q->where('tenant_id', $this->tenantId))->delete();
        MarketplaceOrder::on('central')->where('tenant_id', $this->tenantId)->forceDelete();
        MarketplaceCustomer::on('central')->whereIn('id', $this->customerIds)->forceDelete();
        User::on('central')->whereIn('id', $this->userIds)->forceDelete();
        DB::connection('central')->table('tenants')->where('id', $this->tenantId)->delete();
        DB::connection('central')->statement('SET foreign_key_checks = 1');

        parent::tearDown();
    }

    private function makeService(): MarketplaceDeliveryService
    {
        return new MarketplaceDeliveryService;
    }

    private function createCustomer(): MarketplaceCustomer
    {
        $user = User::on('central')->create([
            'name' => 'Delivery Customer',
            'email' => 'delivery-customer-'.uniqid().'@test.com',
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
            'fulfillment_type' => 'delivery',
            'order_status' => OrderStatus::Confirmed,
        ], $overrides));
    }

    private function createDelivery(MarketplaceOrder $order, array $overrides = []): MarketplaceOrderDelivery
    {
        return MarketplaceOrderDelivery::on('central')->create(array_merge([
            'order_id' => $order->id,
            'delivery_method' => DeliveryMethod::Standard,
            'delivery_status' => DeliveryStatus::Pending,
        ], $overrides));
    }

    // =========================================================================
    // createDelivery()
    // =========================================================================

    public function test_create_delivery_creates_pending_record(): void
    {
        $order = $this->createOrder();

        $delivery = $this->makeService()->createDelivery($order, ['delivery_method' => 'express']);

        $this->assertSame($order->id, $delivery->order_id);
        $this->assertSame(DeliveryStatus::Pending, $delivery->delivery_status);
    }

    public function test_create_delivery_defaults_to_standard_method(): void
    {
        $order = $this->createOrder();

        $delivery = $this->makeService()->createDelivery($order);

        $this->assertSame(DeliveryMethod::Standard, $delivery->delivery_method);
    }

    public function test_create_delivery_is_idempotent_when_one_already_exists(): void
    {
        $order = $this->createOrder();
        $existing = $this->createDelivery($order);

        $result = $this->makeService()->createDelivery($order->fresh(), ['delivery_method' => 'express']);

        $this->assertSame($existing->id, $result->id);
        $this->assertSame(DeliveryMethod::Standard, $result->delivery_method, 'should not overwrite the existing delivery record');
    }

    // =========================================================================
    // updateDeliveryStatus()
    // =========================================================================

    public function test_update_status_sets_pickup_timestamp_on_picked_up(): void
    {
        $order = $this->createOrder();
        $delivery = $this->createDelivery($order);

        $updated = $this->makeService()->updateDeliveryStatus($delivery, DeliveryStatus::PickedUp);

        $this->assertSame(DeliveryStatus::PickedUp, $updated->delivery_status);
        $this->assertNotNull($updated->actual_pickup_time);
        $this->assertNull($updated->actual_delivery_time);
    }

    public function test_update_status_sets_delivery_timestamp_on_delivered(): void
    {
        $order = $this->createOrder();
        $delivery = $this->createDelivery($order);

        $updated = $this->makeService()->updateDeliveryStatus($delivery, DeliveryStatus::Delivered);

        $this->assertNotNull($updated->actual_delivery_time);
    }

    public function test_update_status_does_not_set_timestamps_for_other_statuses(): void
    {
        $order = $this->createOrder();
        $delivery = $this->createDelivery($order);

        $updated = $this->makeService()->updateDeliveryStatus($delivery, DeliveryStatus::InTransit);

        $this->assertNull($updated->actual_pickup_time);
        $this->assertNull($updated->actual_delivery_time);
    }

    public function test_update_status_sets_notes_when_provided(): void
    {
        $order = $this->createOrder();
        $delivery = $this->createDelivery($order);

        $updated = $this->makeService()->updateDeliveryStatus($delivery, DeliveryStatus::Failed, [
            'delivery_notes' => 'Recipient unavailable',
        ]);

        $this->assertSame('Recipient unavailable', $updated->delivery_notes);
    }

    // =========================================================================
    // assignCourier()
    // =========================================================================

    public function test_assign_courier_sets_status_and_courier_details(): void
    {
        $order = $this->createOrder();
        $delivery = $this->createDelivery($order);

        $updated = $this->makeService()->assignCourier($delivery, [
            'courier_company' => 'Fast Riders',
            'courier_name' => 'Jane Doe',
            'courier_phone' => '0712345678',
            'tracking_number' => 'TRK-123',
        ]);

        $this->assertSame(DeliveryStatus::Assigned, $updated->delivery_status);
        $this->assertSame('Fast Riders', $updated->courier_company);
        $this->assertSame('TRK-123', $updated->tracking_number);
    }

    // =========================================================================
    // updateLocation()
    // =========================================================================

    public function test_update_location_sets_coordinates_and_timestamp(): void
    {
        $order = $this->createOrder();
        $delivery = $this->createDelivery($order);

        $updated = $this->makeService()->updateLocation($delivery, -1.286389, 36.817223);

        $this->assertEquals(-1.286389, $updated->last_latitude);
        $this->assertEquals(36.817223, $updated->last_longitude);
        $this->assertNotNull($updated->last_location_update);
    }

    // =========================================================================
    // confirmDelivery()
    // =========================================================================

    public function test_confirm_delivery_sets_delivered_status_and_proof(): void
    {
        $order = $this->createOrder();
        $delivery = $this->createDelivery($order);

        $updated = $this->makeService()->confirmDelivery($delivery, [
            'proof_type' => 'signature', 'received_by_name' => 'John Recipient',
        ]);

        $this->assertSame(DeliveryStatus::Delivered, $updated->delivery_status);
        $this->assertSame('signature', $updated->delivery_proof_type);
        $this->assertSame('John Recipient', $updated->received_by_name);
        $this->assertNotNull($updated->actual_delivery_time);
    }

    public function test_confirm_delivery_completes_non_terminal_order(): void
    {
        $order = $this->createOrder(['order_status' => OrderStatus::Confirmed]);
        $delivery = $this->createDelivery($order);

        $this->makeService()->confirmDelivery($delivery);

        $this->assertSame(OrderStatus::Completed, $order->fresh()->order_status);
    }

    public function test_confirm_delivery_does_not_touch_already_terminal_order(): void
    {
        $order = $this->createOrder(['order_status' => OrderStatus::Cancelled]);
        $delivery = $this->createDelivery($order);

        $this->makeService()->confirmDelivery($delivery);

        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->order_status);
    }

    // =========================================================================
    // getDeliveryStatus()
    // =========================================================================

    public function test_get_delivery_status_returns_null_when_none_exists(): void
    {
        $order = $this->createOrder();

        $this->assertNull($this->makeService()->getDeliveryStatus($order));
    }

    public function test_get_delivery_status_returns_the_delivery_record(): void
    {
        $order = $this->createOrder();
        $delivery = $this->createDelivery($order);

        $result = $this->makeService()->getDeliveryStatus($order);

        $this->assertSame($delivery->id, $result->id);
    }
}
