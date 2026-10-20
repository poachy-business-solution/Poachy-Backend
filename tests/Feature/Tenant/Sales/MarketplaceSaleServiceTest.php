<?php

namespace Tests\Feature\Tenant\Sales;

use App\Enums\Tenant\MarketplaceFulfillmentStatus;
use App\Events\Tenant\MarketplaceSaleFulfillmentSyncRequested;
use App\Http\Resources\Tenant\Sales\MarketplaceSaleResource;
use App\Models\CustomerAddress;
use App\Models\MarketplaceCustomer;
use App\Models\MarketplaceOrder;
use App\Models\Tenant\MarketplaceSale;
use App\Models\User;
use App\Services\Tenant\Sales\MarketplaceSaleService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Stancl\Tenancy\Contracts\Tenant as TenantContract;
use Tests\TestCase;

class MarketplaceSaleServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'tenant');
        Config::set('database.connections.tenant.database', 'poachy_test');
        DB::purge('tenant');
        DB::connection('tenant')->statement('SET foreign_key_checks = 0');

        $this->dropTestTables();
        $this->createMinimalSchema();

        $fakeTenant = new \stdClass;
        $fakeTenant->id = 'test-tenant';
        app()->instance(TenantContract::class, $fakeTenant);
    }

    protected function tearDown(): void
    {
        $this->dropTestTables();
        DB::connection('tenant')->statement('SET foreign_key_checks = 1');
        parent::tearDown();
    }

    private function service(): MarketplaceSaleService
    {
        return new MarketplaceSaleService;
    }

    private function createSale(array $overrides = []): MarketplaceSale
    {
        return Model::withoutEvents(fn () => MarketplaceSale::create(array_merge([
            'central_order_id' => 555,
            'sale_number' => 'MKT-ORD-'.uniqid(),
            'store_id' => 1,
            'sale_date' => now(),
            'subtotal' => 1000,
            'tax_amount' => 160,
            'total_amount' => 1160,
            'payment_status' => 'paid',
            'amount_paid' => 1160,
            'amount_due' => 0,
            'fulfillment_type' => 'delivery',
            'fulfillment_status' => 'pending',
        ], $overrides)));
    }

    public function test_valid_transition_updates_status_and_fires_sync_event(): void
    {
        Event::fake([MarketplaceSaleFulfillmentSyncRequested::class]);
        $sale = $this->createSale();

        $updated = $this->service()->updateFulfillmentStatus($sale, MarketplaceFulfillmentStatus::CONFIRMED);

        $this->assertSame(MarketplaceFulfillmentStatus::CONFIRMED, $updated->fulfillment_status);
        Event::assertDispatched(MarketplaceSaleFulfillmentSyncRequested::class, function ($event) use ($sale) {
            return $event->fulfillmentDTO->saleId === $sale->id
                && $event->fulfillmentDTO->fulfillmentStatus === 'confirmed';
        });
    }

    public function test_invalid_transition_throws_and_does_not_fire_event(): void
    {
        Event::fake([MarketplaceSaleFulfillmentSyncRequested::class]);
        $sale = $this->createSale(['fulfillment_status' => 'pending']);

        $this->expectException(\RuntimeException::class);

        try {
            // PENDING cannot jump straight to DELIVERED
            $this->service()->updateFulfillmentStatus($sale, MarketplaceFulfillmentStatus::DELIVERED);
        } finally {
            Event::assertNotDispatched(MarketplaceSaleFulfillmentSyncRequested::class);
        }
    }

    public function test_terminal_status_cannot_transition_further(): void
    {
        $sale = $this->createSale(['fulfillment_status' => 'delivered']);

        $this->expectException(\RuntimeException::class);

        $this->service()->updateFulfillmentStatus($sale, MarketplaceFulfillmentStatus::CANCELLED);
    }

    public function test_notes_are_persisted_on_transition(): void
    {
        Event::fake([MarketplaceSaleFulfillmentSyncRequested::class]);
        $sale = $this->createSale();

        $updated = $this->service()->updateFulfillmentStatus(
            $sale,
            MarketplaceFulfillmentStatus::CONFIRMED,
            [],
            'Confirmed by cashier'
        );

        $this->assertSame('Confirmed by cashier', $updated->notes);
    }

    public function test_delivery_data_is_passed_through_to_sync_dto_but_not_persisted_on_sale(): void
    {
        Event::fake([MarketplaceSaleFulfillmentSyncRequested::class]);
        $sale = $this->createSale(['fulfillment_status' => 'confirmed']);

        $this->service()->updateFulfillmentStatus(
            $sale,
            MarketplaceFulfillmentStatus::PREPARING,
            ['courier_name' => 'Jane', 'courier_phone' => '0700000000']
        );

        Event::assertDispatched(MarketplaceSaleFulfillmentSyncRequested::class, function ($event) {
            return $event->fulfillmentDTO->courierName === 'Jane'
                && $event->fulfillmentDTO->courierPhone === '0700000000';
        });

        // MarketplaceSale itself has no courier columns — nothing to assert
        // persisted there; confirmed by the schema simply having none.
        $this->assertFalse(Schema::connection('tenant')->hasColumn('marketplace_sales', 'courier_name'));
    }

    public function test_the_list_carries_the_buyer_and_delivery_address_from_this_shops_central_order(): void
    {
        Config::set('database.connections.central.host', env('CENTRAL_DB_HOST', '127.0.0.1'));
        Config::set('database.connections.central.database', env('CENTRAL_DB_DATABASE', 'poachy'));
        DB::purge('central');
        DB::connection('central')->statement('SET foreign_key_checks = 0');
        $run = uniqid();
        $user = User::on('central')->create(['name' => 'Wanjiru Buyer', 'email' => "tab-{$run}@example.com", 'password' => bcrypt('x'), 'user_type' => 'customer']);
        $customer = MarketplaceCustomer::on('central')->create(['user_id' => $user->id, 'customer_number' => 'MKT-T-'.$run, 'phone' => '+254711'.random_int(100000, 999999), 'is_active' => true]);
        $address = CustomerAddress::on('central')->create(['customer_id' => $customer->id, 'recipient_name' => 'Wanjiru', 'recipient_phone' => '0711000000', 'address_line' => 'Kimathi St 4', 'city' => 'Nairobi', 'county' => 'Nairobi', 'delivery_instructions' => 'Blue gate']);
        $order = MarketplaceOrder::on('central')->create(['order_number' => 'ORD-'.$run, 'customer_id' => $customer->id, 'tenant_id' => 'test-tenant', 'merchant_name' => 'Test',
            'subtotal' => 1000, 'tax_amount' => 160, 'discount_amount' => 0, 'delivery_fee' => 150, 'total_amount' => 1310, 'fulfillment_type' => 'delivery',
            'delivery_address_id' => $address->id, 'order_status' => 'confirmed', 'customer_notes' => 'Call on arrival']);
        $other = MarketplaceOrder::on('central')->create(['order_number' => 'ORD-X-'.$run, 'customer_id' => $customer->id, 'tenant_id' => 'another-shop', 'merchant_name' => 'Other',
            'subtotal' => 1, 'tax_amount' => 0, 'discount_amount' => 0, 'delivery_fee' => 0, 'total_amount' => 1, 'fulfillment_type' => 'pickup', 'order_status' => 'confirmed']);

        try {
            $this->createSale(['central_order_id' => $order->id, 'fulfillment_status' => 'pending']);
            $this->createSale(['central_order_id' => $other->id]); // another shop's order id never leaks its buyer

            $rows = collect($this->service()->list(['open' => 1])->items())
                ->map(fn ($sale) => (new MarketplaceSaleResource($sale))->resolve())->keyBy('central_order_id');

            $mine = $rows[$order->id];
            $this->assertSame(['name' => 'Wanjiru Buyer', 'phone' => $customer->phone, 'notes' => 'Call on arrival'], $mine['buyer']);
            $this->assertSame('Kimathi St 4, Nairobi, Nairobi', $mine['delivery']['address']);
            $this->assertSame('Blue gate', $mine['delivery']['instructions']);
            $this->assertEquals(150, $mine['delivery']['fee']);
            $this->assertSame(['confirmed'], $mine['next_statuses']->all());
            $this->assertFalse($mine['is_overdue']);
            $this->assertNull($rows[$other->id]['buyer']);
        } finally {
            MarketplaceOrder::on('central')->whereIn('id', [$order->id, $other->id])->forceDelete();
            $address->forceDelete();
            $customer->forceDelete();
            $user->forceDelete();
            DB::connection('central')->statement('SET foreign_key_checks = 1');
        }
    }

    private function dropTestTables(): void
    {
        foreach (['marketplace_sale_items', 'marketplace_sales', 'stores'] as $table) {
            Schema::connection('tenant')->dropIfExists($table);
        }
    }

    private function createMinimalSchema(): void
    {
        Schema::connection('tenant')->create('stores', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('Test Store');
            $table->timestamps();
            $table->softDeletes();
        });

        DB::connection('tenant')->table('stores')->insert(['id' => 1, 'name' => 'Main Store', 'created_at' => now(), 'updated_at' => now()]);

        Schema::connection('tenant')->create('marketplace_sales', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('central_order_id')->nullable();
            $table->string('sale_number')->unique();
            $table->unsignedBigInteger('store_id');
            $table->dateTime('sale_date');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->string('payment_status')->default('paid');
            $table->decimal('amount_paid', 12, 2)->default(0);
            $table->decimal('amount_due', 12, 2)->default(0);
            $table->string('payment_method')->nullable();
            $table->string('payment_reference')->nullable();
            $table->string('fulfillment_type')->default('delivery');
            $table->string('fulfillment_status')->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection('tenant')->create('marketplace_sale_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('marketplace_sale_id');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('product_variant_id')->nullable();
            $table->unsignedBigInteger('bundle_id')->nullable();
            $table->unsignedBigInteger('uom_id')->nullable();
            $table->decimal('quantity', 12, 4)->default(1);
            $table->decimal('quantity_in_base_uom', 12, 4)->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->timestamps();
        });
    }
}
