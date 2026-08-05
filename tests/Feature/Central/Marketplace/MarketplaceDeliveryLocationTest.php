<?php

namespace Tests\Feature\Central\Marketplace;

use App\Models\MarketplaceOrder;
use App\Models\MarketplaceOrderDelivery;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MarketplaceDeliveryLocationTest extends TestCase
{
    private string $tenantId;

    private int $userId;

    private int $customerId;

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
        DB::purge('central');
        DB::setDefaultConnection('central');
        DB::connection('central')->statement('SET foreign_key_checks = 0');

        $this->tenantId = 'mkt-loc-test-'.uniqid();
        DB::connection('central')->table('tenants')->insertOrIgnore([
            'id' => $this->tenantId, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::connection('central')->table('domains')->insert([
            'domain' => 'mkt-loc-'.uniqid().'.poachy.test', 'tenant_id' => $this->tenantId,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->userId = DB::connection('central')->table('users')->insertGetId([
            'name' => 'Test Customer', 'email' => uniqid().'@example.test',
            'password' => bcrypt('password'), 'user_type' => 'customer',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->customerId = DB::connection('central')->table('marketplace_customers')->insertGetId([
            'customer_number' => 'MKT-CUST-'.uniqid(), 'user_id' => $this->userId,
            'phone' => '+254700'.rand(100000, 999999),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        DB::connection('central')->statement('SET foreign_key_checks = 0');
        $orderIds = MarketplaceOrder::on('central')->where('tenant_id', $this->tenantId)->pluck('id');
        MarketplaceOrderDelivery::on('central')->whereIn('order_id', $orderIds)->delete();
        MarketplaceOrder::on('central')->where('tenant_id', $this->tenantId)->forceDelete();
        DB::connection('central')->table('marketplace_customers')->where('id', $this->customerId)->delete();
        DB::connection('central')->table('users')->where('id', $this->userId)->delete();
        DB::connection('central')->table('domains')->where('tenant_id', $this->tenantId)->delete();
        DB::connection('central')->table('tenants')->where('id', $this->tenantId)->delete();
        DB::connection('central')->statement('SET foreign_key_checks = 1');

        parent::tearDown();
    }

    private function createOrder(array $overrides = []): MarketplaceOrder
    {
        return Model::withoutEvents(fn () => MarketplaceOrder::on('central')->create(array_merge([
            'order_number' => 'MKT-ORD-'.uniqid(),
            'customer_id' => $this->customerId,
            'tenant_id' => $this->tenantId,
            'merchant_name' => 'Test Merchant',
            'subtotal' => 1000,
            'total_amount' => 1160,
            'fulfillment_type' => 'delivery',
            'order_status' => 'confirmed',
        ], $overrides)));
    }

    private function url(int $orderId): string
    {
        return "/api/v1/central/marketplace-orders/{$orderId}/delivery/location";
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $order = $this->createOrder();

        $response = $this->postJson($this->url($order->id), ['latitude' => 1.0, 'longitude' => 1.0]);

        $response->assertStatus(403);
    }

    public function test_returns_404_when_order_does_not_exist(): void
    {
        $response = $this->withToken('central-test-token')
            ->postJson($this->url(999999999), ['latitude' => -1.286389, 'longitude' => 36.817223]);

        $response->assertStatus(404);
    }

    public function test_returns_422_when_no_delivery_record_exists_yet(): void
    {
        $order = $this->createOrder();

        $response = $this->withToken('central-test-token')
            ->postJson($this->url($order->id), ['latitude' => -1.286389, 'longitude' => 36.817223]);

        $response->assertStatus(422);
    }

    public function test_updates_latitude_and_longitude_on_existing_delivery(): void
    {
        $order = $this->createOrder();
        $delivery = Model::withoutEvents(fn () => MarketplaceOrderDelivery::on('central')->create([
            'order_id' => $order->id,
            'delivery_method' => 'standard',
            'delivery_status' => 'assigned',
        ]));

        $response = $this->withToken('central-test-token')
            ->postJson($this->url($order->id), ['latitude' => -1.286389, 'longitude' => 36.817223]);

        $response->assertStatus(200);
        $fresh = $delivery->fresh();
        $this->assertEquals(-1.286389, (float) $fresh->last_latitude);
        $this->assertEquals(36.817223, (float) $fresh->last_longitude);
        $this->assertNotNull($fresh->last_location_update);
    }

    public function test_rejects_invalid_latitude(): void
    {
        $order = $this->createOrder();

        $response = $this->withToken('central-test-token')
            ->postJson($this->url($order->id), ['latitude' => 200, 'longitude' => 36.817223]);

        $response->assertStatus(422);
    }
}
