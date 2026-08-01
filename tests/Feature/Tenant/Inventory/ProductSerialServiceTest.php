<?php

namespace Tests\Feature\Tenant\Inventory;

use App\Models\Tenant\ProductSerial;
use App\Services\Tenant\Inventory\ProductSerialService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Stancl\Tenancy\Contracts\Tenant as TenantContract;
use Tests\TestCase;

class ProductSerialServiceTest extends TestCase
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
        $this->seedBaseData();

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

    // =========================================================================
    // assignSerialsForSale()
    // =========================================================================

    public function test_assigns_available_serials_to_sale_item(): void
    {
        $one = $this->createSerial(['serial_number' => 'IMEI-001']);
        $two = $this->createSerial(['serial_number' => 'IMEI-002']);

        $result = $this->service()->assignSerialsForSale(
            storeId: 1,
            productId: 1,
            variantId: null,
            serialNumbers: ['IMEI-001', 'IMEI-002'],
            quantity: 2.0,
            saleItemId: 99
        );

        $this->assertCount(2, $result);
        $this->assertSame('sold', $one->fresh()->status->value);
        $this->assertSame(99, $one->fresh()->sale_item_id);
        $this->assertSame('sold', $two->fresh()->status->value);
    }

    public function test_assign_throws_when_serial_count_mismatches_quantity(): void
    {
        $this->createSerial(['serial_number' => 'IMEI-001']);

        $this->expectException(\RuntimeException::class);

        $this->service()->assignSerialsForSale(
            storeId: 1,
            productId: 1,
            variantId: null,
            serialNumbers: ['IMEI-001'],
            quantity: 2.0,
            saleItemId: 99
        );
    }

    public function test_assign_throws_when_serial_not_found(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->service()->assignSerialsForSale(
            storeId: 1,
            productId: 1,
            variantId: null,
            serialNumbers: ['IMEI-MISSING'],
            quantity: 1.0,
            saleItemId: 99
        );
    }

    public function test_assign_throws_when_serial_already_sold(): void
    {
        $this->createSerial(['serial_number' => 'IMEI-001', 'status' => 'sold']);

        $this->expectException(\RuntimeException::class);

        $this->service()->assignSerialsForSale(
            storeId: 1,
            productId: 1,
            variantId: null,
            serialNumbers: ['IMEI-001'],
            quantity: 1.0,
            saleItemId: 99
        );
    }

    // =========================================================================
    // restoreSerialsForRefund()
    // =========================================================================

    public function test_restores_sold_serial_and_clears_sale_item_link(): void
    {
        $serial = $this->createSerial(['serial_number' => 'IMEI-001', 'status' => 'sold', 'sale_item_id' => 42]);

        $this->service()->restoreSerialsForRefund(saleItemId: 42, serialNumbers: ['IMEI-001']);

        $serial->refresh();
        $this->assertSame('available', $serial->status->value);
        $this->assertNull($serial->sale_item_id);
    }

    public function test_restore_throws_when_serial_not_sold_on_that_sale_item(): void
    {
        $this->createSerial(['serial_number' => 'IMEI-001', 'status' => 'sold', 'sale_item_id' => 1]);

        $this->expectException(\RuntimeException::class);

        // Wrong sale_item_id — serial belongs to a different sale item.
        $this->service()->restoreSerialsForRefund(saleItemId: 42, serialNumbers: ['IMEI-001']);
    }

    // =========================================================================
    // autoAssignSerialsFIFO()
    // =========================================================================

    public function test_auto_assign_picks_oldest_available_serials_first(): void
    {
        $newer = $this->createSerial(['serial_number' => 'IMEI-NEW', 'purchase_order_id' => 2, 'created_at' => now()]);
        $older = $this->createSerial(['serial_number' => 'IMEI-OLD', 'purchase_order_id' => 1, 'created_at' => now()->subMinute()]);

        $result = $this->service()->autoAssignSerialsFIFO(
            storeId: 1,
            productId: 1,
            variantId: null,
            quantity: 1,
            marketplaceSaleItemId: 55
        );

        $this->assertCount(1, $result);
        $this->assertSame($older->id, $result->first()->id);
        $this->assertSame('sold', $older->fresh()->status->value);
        $this->assertSame(55, $older->fresh()->marketplace_sale_item_id);
        $this->assertSame('available', $newer->fresh()->status->value);
    }

    public function test_auto_assign_throws_when_insufficient_available_serials(): void
    {
        $this->createSerial(['serial_number' => 'IMEI-001']);

        $this->expectException(\RuntimeException::class);

        $this->service()->autoAssignSerialsFIFO(
            storeId: 1,
            productId: 1,
            variantId: null,
            quantity: 2,
            marketplaceSaleItemId: 55
        );
    }

    public function test_auto_assign_excludes_already_sold_serials(): void
    {
        $this->createSerial(['serial_number' => 'IMEI-SOLD', 'status' => 'sold']);
        $available = $this->createSerial(['serial_number' => 'IMEI-AVAILABLE']);

        $result = $this->service()->autoAssignSerialsFIFO(
            storeId: 1,
            productId: 1,
            variantId: null,
            quantity: 1,
            marketplaceSaleItemId: 55
        );

        $this->assertSame($available->id, $result->first()->id);
    }

    // =========================================================================
    // restoreSerialsForMarketplaceCancellation()
    // =========================================================================

    public function test_restores_all_serials_linked_to_marketplace_sale_item(): void
    {
        $one = $this->createSerial(['serial_number' => 'IMEI-001', 'status' => 'sold', 'marketplace_sale_item_id' => 77]);
        $two = $this->createSerial(['serial_number' => 'IMEI-002', 'status' => 'sold', 'marketplace_sale_item_id' => 77]);
        $unrelated = $this->createSerial(['serial_number' => 'IMEI-003', 'status' => 'sold', 'marketplace_sale_item_id' => 88]);

        $result = $this->service()->restoreSerialsForMarketplaceCancellation(marketplaceSaleItemId: 77);

        $this->assertCount(2, $result);
        $this->assertSame('available', $one->fresh()->status->value);
        $this->assertNull($one->fresh()->marketplace_sale_item_id);
        $this->assertSame('available', $two->fresh()->status->value);
        $this->assertSame('sold', $unrelated->fresh()->status->value);
    }

    public function test_restore_marketplace_cancellation_is_noop_when_no_serials_linked(): void
    {
        $result = $this->service()->restoreSerialsForMarketplaceCancellation(marketplaceSaleItemId: 999);

        $this->assertCount(0, $result);
    }

    // =========================================================================
    // getSerialsForProduct()
    // =========================================================================

    public function test_get_serials_for_product_only_available_filters_sold(): void
    {
        $available = $this->createSerial(['serial_number' => 'IMEI-001']);
        $this->createSerial(['serial_number' => 'IMEI-002', 'status' => 'sold']);

        $all = $this->service()->getSerialsForProduct(storeId: 1, productId: 1, variantId: null, onlyAvailable: false);
        $onlyAvailable = $this->service()->getSerialsForProduct(storeId: 1, productId: 1, variantId: null, onlyAvailable: true);

        $this->assertCount(2, $all);
        $this->assertCount(1, $onlyAvailable);
        $this->assertSame($available->id, $onlyAvailable->first()->id);
    }

    // =========================================================================
    // findBySerialNumber()
    // =========================================================================

    public function test_find_by_serial_number_returns_matching_serial(): void
    {
        $serial = $this->createSerial(['serial_number' => 'IMEI-UNIQUE']);

        $found = $this->service()->findBySerialNumber('IMEI-UNIQUE');

        $this->assertSame($serial->id, $found->id);
    }

    public function test_find_by_serial_number_returns_null_when_not_found(): void
    {
        $this->assertNull($this->service()->findBySerialNumber('IMEI-DOES-NOT-EXIST'));
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function service(): ProductSerialService
    {
        return new ProductSerialService;
    }

    private function createSerial(array $overrides = []): ProductSerial
    {
        return Model::withoutEvents(fn () => ProductSerial::create(array_merge([
            'store_id' => 1,
            'product_id' => 1,
            'product_variant_id' => null,
            'purchase_order_id' => 1,
            'serial_number' => 'IMEI-'.uniqid(),
            'status' => 'available',
            'cost' => 50.00,
            'supplier_id' => null,
        ], $overrides)));
    }

    private function seedBaseData(): void
    {
        $conn = 'tenant';

        DB::connection($conn)->table('stores')->insert([
            ['id' => 1, 'name' => 'Main Store', 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::connection($conn)->table('products')->insert([
            ['id' => 1, 'name' => 'Product One', 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::connection($conn)->table('purchase_orders')->insert([
            ['id' => 1, 'po_number' => 'PO-1', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'po_number' => 'PO-2', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    private function dropTestTables(): void
    {
        foreach ([
            'product_serials',
            'purchase_orders',
            'suppliers',
            'product_variants',
            'products',
            'stores',
        ] as $table) {
            Schema::connection('tenant')->dropIfExists($table);
        }
    }

    private function createMinimalSchema(): void
    {
        $conn = 'tenant';

        Schema::connection($conn)->create('stores', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('Test Store');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection($conn)->create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('Test Product');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection($conn)->create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->string('sku')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection($conn)->create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('Test Supplier');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection($conn)->create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('po_number')->unique();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection($conn)->create('product_serials', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('product_variant_id')->nullable();
            $table->unsignedBigInteger('purchase_order_id');
            $table->string('serial_number')->unique();
            $table->string('status')->default('available');
            $table->decimal('cost', 15, 2)->default(0);
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->unsignedBigInteger('sale_item_id')->nullable();
            $table->unsignedBigInteger('marketplace_sale_item_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }
}
