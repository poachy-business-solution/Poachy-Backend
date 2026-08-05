<?php

namespace Tests\Feature\Tenant\Inventory;

use App\Http\Controllers\Api\Tenant\Inventory\ProductSerialController;
use App\Http\Requests\Tenant\Inventory\Serial\GetSerialsRequest;
use App\Models\Tenant\ProductSerial;
use App\Services\Tenant\Inventory\ProductSerialService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Stancl\Tenancy\Contracts\Tenant as TenantContract;
use Tests\TestCase;

class ProductSerialControllerPaginationTest extends TestCase
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

    private function controller(): ProductSerialController
    {
        return new ProductSerialController(new ProductSerialService);
    }

    private function requestWith(array $query): GetSerialsRequest
    {
        $request = GetSerialsRequest::create('/', 'GET', $query);
        $request->setContainer($this->app);
        $request->validateResolved();

        return $request;
    }

    /**
     * index() without a product_id filter previously did ->get() on a
     * store's entire serial history — no bound on response size at all.
     */
    public function test_index_without_product_filter_paginates_instead_of_returning_everything(): void
    {
        for ($i = 0; $i < 25; $i++) {
            $this->createSerial(['purchase_order_id' => 1, 'serial_number' => 'IMEI-'.$i]);
        }

        $response = $this->controller()->index($this->requestWith(['store_id' => 1, 'per_page' => 10]));
        $data = $response->getData(true)['data'];

        $this->assertCount(10, $data['data']);
        $this->assertSame(25, $data['pagination']['total']);
        $this->assertSame(3, $data['pagination']['last_page']);
    }

    public function test_index_with_product_filter_still_returns_a_plain_collection(): void
    {
        $this->createSerial(['purchase_order_id' => 1]);
        $this->createSerial(['purchase_order_id' => 1, 'product_id' => 2, 'serial_number' => 'IMEI-OTHER']);

        $response = $this->controller()->index($this->requestWith(['store_id' => 1, 'product_id' => 1]));
        $data = $response->getData(true);

        $this->assertCount(1, $data['data']);
        $this->assertArrayNotHasKey('pagination', $data);
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
            ['id' => 2, 'name' => 'Product Two', 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::connection($conn)->table('purchase_orders')->insert([
            ['id' => 1, 'po_number' => 'PO-1', 'created_at' => now(), 'updated_at' => now()],
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
