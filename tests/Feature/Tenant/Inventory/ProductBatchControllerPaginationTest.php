<?php

namespace Tests\Feature\Tenant\Inventory;

use App\Http\Controllers\Api\Tenant\Inventory\ProductBatchController;
use App\Http\Requests\Tenant\Inventory\Batch\GetBatchesRequest;
use App\Models\Tenant\ProductBatch;
use App\Services\Tenant\Inventory\ProductBatchService;
use App\Services\Tenant\Inventory\ProductSerialService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Stancl\Tenancy\Contracts\Tenant as TenantContract;
use Tests\TestCase;

class ProductBatchControllerPaginationTest extends TestCase
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

    private function controller(): ProductBatchController
    {
        return new ProductBatchController(new ProductBatchService, new ProductSerialService);
    }

    private function requestWith(array $query): GetBatchesRequest
    {
        $request = GetBatchesRequest::create('/', 'GET', $query);
        $request->setContainer($this->app);
        $request->validateResolved();

        return $request;
    }

    /**
     * index() without a product_id filter previously did ->get() on a
     * store's entire batch history — no bound on response size at all.
     */
    public function test_index_without_product_filter_paginates_instead_of_returning_everything(): void
    {
        for ($i = 0; $i < 25; $i++) {
            $this->createBatch(['purchase_order_id' => 1, 'batch_number' => 'BATCH-'.$i]);
        }

        $response = $this->controller()->index($this->requestWith(['store_id' => 1, 'per_page' => 10]));
        $data = $response->getData(true)['data'];

        $this->assertCount(10, $data['data']);
        $this->assertSame(25, $data['pagination']['total']);
        $this->assertSame(3, $data['pagination']['last_page']);
    }

    public function test_index_with_product_filter_still_returns_a_plain_collection(): void
    {
        $this->createBatch(['purchase_order_id' => 1]);
        $this->createBatch(['purchase_order_id' => 1, 'product_id' => 2, 'batch_number' => 'BATCH-OTHER']);

        $response = $this->controller()->index($this->requestWith(['store_id' => 1, 'product_id' => 1]));
        $data = $response->getData(true);

        $this->assertCount(1, $data['data']);
        $this->assertArrayNotHasKey('pagination', $data);
    }

    private function createBatch(array $overrides = []): ProductBatch
    {
        return Model::withoutEvents(fn () => ProductBatch::create(array_merge([
            'store_id' => 1,
            'product_id' => 1,
            'product_variant_id' => null,
            'purchase_order_id' => 1,
            'batch_number' => 'BATCH-'.uniqid(),
            'purchase_uom_id' => 1,
            'quantity_received_in_purchase_uom' => 10.0,
            'quantity_received_in_base_uom' => 100.0,
            'quantity_remaining_in_base_uom' => 100.0,
            'cost_per_purchase_uom' => 100.0,
            'cost_per_base_uom' => 10.0,
            'total_cost' => 1000.0,
            'manufacture_date' => null,
            'expiry_date' => null,
            'is_expired' => false,
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

        DB::connection($conn)->table('units_of_measure')->insert([
            ['id' => 1, 'code' => 'PCS', 'name' => 'Pieces', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    private function dropTestTables(): void
    {
        foreach ([
            'product_batches',
            'units_of_measure',
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

        Schema::connection($conn)->create('units_of_measure', function (Blueprint $table) {
            $table->id();
            $table->string('code');
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection($conn)->create('product_batches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('product_variant_id')->nullable();
            $table->unsignedBigInteger('purchase_order_id');
            $table->string('batch_number')->unique();
            $table->unsignedBigInteger('purchase_uom_id');
            $table->decimal('quantity_received_in_purchase_uom', 15, 4);
            $table->decimal('quantity_received_in_base_uom', 15, 4);
            $table->decimal('quantity_remaining_in_base_uom', 15, 4);
            $table->decimal('cost_per_purchase_uom', 15, 2);
            $table->decimal('cost_per_base_uom', 15, 2);
            $table->decimal('total_cost', 15, 2);
            $table->date('manufacture_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->boolean('is_expired')->default(false);
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }
}
