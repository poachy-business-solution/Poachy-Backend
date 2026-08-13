<?php

namespace Tests\Feature\Tenant\Sync;

use App\Jobs\Tenant\ProcessInboundOrderSync;
use App\Jobs\Tenant\ProcessInboundPaymentSync;
use App\Models\Tenant\MarketplaceSale;
use App\Services\Tenant\Inventory\ProductBatchService;
use App\Services\Tenant\Inventory\ProductSerialService;
use App\Services\Tenant\Inventory\StockReservationService;
use App\Services\Tenant\Marketplace\MarketplaceOrderStaffNotificationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Stancl\Tenancy\Contracts\Tenant;
use Tests\TestCase;

class InboundMarketplaceStaffNotificationTest extends TestCase
{
    private const TEST_DB = 'poachy_test';

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'tenant');
        Config::set('database.connections.tenant.database', self::TEST_DB);
        DB::purge('tenant');

        DB::connection('tenant')->statement('SET foreign_key_checks = 0');
        $this->dropTestTables();
        $this->createMinimalSchema();

        $fakeTenant = new \stdClass;
        $fakeTenant->id = 'test-tenant';
        app()->instance(Tenant::class, $fakeTenant);

        Http::fake();
    }

    protected function tearDown(): void
    {
        $this->dropTestTables();
        DB::connection('tenant')->statement('SET foreign_key_checks = 1');
        Mockery::close();

        parent::tearDown();
    }

    public function test_inbound_order_sync_notifies_staff_after_successful_reservation(): void
    {
        $reservationService = Mockery::mock(StockReservationService::class);
        $reservationService
            ->shouldReceive('reserveStock')
            ->once()
            ->with('MarketplaceOrder', 123, [], 30);

        $notifications = Mockery::mock(MarketplaceOrderStaffNotificationService::class);
        $notifications
            ->shouldReceive('notifyNewOrderReserved')
            ->once()
            ->with(Mockery::on(fn (array $payload) => $payload['order_id'] === 123), []);

        $job = new ProcessInboundOrderSync($this->payload());

        $job->handle($reservationService, $notifications);

        $this->assertTrue(true);
    }

    public function test_inbound_payment_sync_notifies_staff_after_sale_is_created(): void
    {
        DB::connection('tenant')->table('inventory')->insert([
            'id' => 10,
            'store_id' => 5,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('tenant')->table('inventory_reservations')->insert([
            'id' => 20,
            'inventory_id' => 10,
            'reference_type' => 'MarketplaceOrder',
            'reference_id' => 123,
            'quantity_reserved' => 1,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $reservationService = Mockery::mock(StockReservationService::class);
        $reservationService
            ->shouldReceive('confirmAllReservationsForReference')
            ->once()
            ->with('MarketplaceOrder', 123);

        $notifications = Mockery::mock(MarketplaceOrderStaffNotificationService::class);
        $notifications
            ->shouldReceive('notifyPaymentConfirmed')
            ->once()
            ->with(
                Mockery::on(fn (array $payload) => $payload['order_id'] === 123),
                Mockery::on(fn (MarketplaceSale $sale) => $sale->central_order_id === 123 && $sale->store_id === 5),
            );

        $job = new ProcessInboundPaymentSync($this->payload());

        $job->handle(
            $reservationService,
            Mockery::mock(ProductBatchService::class),
            Mockery::mock(ProductSerialService::class),
            $notifications,
        );

        $this->assertTrue(MarketplaceSale::where('central_order_id', 123)->exists());
    }

    private function payload(): array
    {
        return [
            'order_id' => 123,
            'order_number' => 'MKT-ORD-123',
            'payment_method' => 'mpesa',
            'amount' => 500,
            'transaction_reference' => 'TXN-123',
            'fulfillment_type' => 'pickup',
            'items' => [],
        ];
    }

    private function createMinimalSchema(): void
    {
        $conn = 'tenant';

        Schema::connection($conn)->create('inventory', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection($conn)->create('inventory_reservations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('inventory_id');
            $table->string('reference_type');
            $table->unsignedBigInteger('reference_id');
            $table->decimal('quantity_reserved', 12, 4)->default(0);
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection($conn)->create('marketplace_sales', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('central_order_id')->unique();
            $table->string('sale_number')->unique();
            $table->unsignedBigInteger('store_id');
            $table->timestamp('sale_date')->useCurrent();
            $table->decimal('subtotal', 15, 2);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2);
            $table->string('payment_status')->default('paid');
            $table->decimal('amount_paid', 15, 2)->default(0);
            $table->decimal('amount_due', 15, 2)->default(0);
            $table->string('payment_method');
            $table->string('payment_reference')->nullable();
            $table->string('fulfillment_type')->default('delivery');
            $table->string('fulfillment_status')->default('pending');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection($conn)->create('marketplace_sale_items', function (Blueprint $table) {
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

        Schema::connection($conn)->create('products', function (Blueprint $table) {
            $table->id();
            $table->boolean('requires_batch_tracking')->default(false);
            $table->boolean('requires_serial_tracking')->default(false);
            $table->timestamps();
        });
    }

    private function dropTestTables(): void
    {
        foreach (['products', 'marketplace_sale_items', 'marketplace_sales', 'inventory_reservations', 'inventory'] as $table) {
            Schema::connection('tenant')->dropIfExists($table);
        }
    }
}
