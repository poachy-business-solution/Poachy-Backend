<?php

namespace Tests\Feature\Tenant\Sales;

use App\Enums\Tenant\MarketplaceFulfillmentStatus;
use App\Events\Tenant\MarketplaceSaleFulfillmentSyncRequested;
use App\Models\Tenant\MarketplaceSale;
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
