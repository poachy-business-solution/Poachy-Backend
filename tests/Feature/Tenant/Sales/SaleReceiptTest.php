<?php

namespace Tests\Feature\Tenant\Sales;

use App\Enums\Tenant\PaymentMethod;
use App\Http\Controllers\Api\Tenant\Sales\SaleController;
use App\Models\Tenant\Sale;
use App\Models\Tenant\SaleItem;
use App\Models\Tenant\SalePayment;
use App\Services\Tenant\Sales\SaleCalculationService;
use App\Services\Tenant\Sales\SaleService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Stancl\Tenancy\Contracts\Tenant as TenantContract;
use Tests\TestCase;

class SaleReceiptTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'tenant');
        Config::set('database.connections.tenant.database', 'poachy_test');
        DB::purge('tenant');
        DB::connection('tenant')->statement('SET foreign_key_checks = 0');

        $this->createMinimalSchema();

        $fakeTenant = new \stdClass;
        $fakeTenant->id = 'test-tenant';
        app()->instance(TenantContract::class, $fakeTenant);
    }

    protected function tearDown(): void
    {
        $this->dropTestTables();
        DB::connection('tenant')->statement('SET foreign_key_checks = 1');
        Mockery::close();
        parent::tearDown();
    }

    private function makeController(): SaleController
    {
        return new SaleController(
            Mockery::mock(SaleService::class),
            Mockery::mock(SaleCalculationService::class)
        );
    }

    public function test_generate_receipt_returns_structured_data_with_split_payments(): void
    {
        DB::connection('tenant')->table('stores')->insert([
            'id' => 1,
            'name' => 'Main Store',
            'address' => '123 Test Street',
            'phone' => '0712345678',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $sale = Model::withoutEvents(fn () => Sale::create([
            'sale_number' => 'SALE-'.uniqid(),
            'store_id' => 1,
            'sale_date' => now(),
            'subtotal' => 500.00,
            'tax_amount' => 50.00,
            'discount_amount' => 0.00,
            'total_amount' => 550.00,
            'payment_status' => 'paid',
            'amount_paid' => 550.00,
            'amount_due' => 0.00,
            'payment_method' => PaymentMethod::MPESA,
        ]));

        Model::withoutEvents(fn () => SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => 1,
            'uom_id' => 1,
            'quantity' => 2,
            'quantity_in_base_uom' => 2,
            'unit_price' => 250.00,
            'discount_amount' => 0,
            'tax_amount' => 50.00,
            'subtotal' => 500.00,
        ]));

        Model::withoutEvents(function () use ($sale) {
            SalePayment::create(['sale_id' => $sale->id, 'amount' => 300.00, 'payment_method' => PaymentMethod::CASH]);
            SalePayment::create(['sale_id' => $sale->id, 'amount' => 250.00, 'payment_method' => PaymentMethod::MPESA]);
        });

        $response = $this->makeController()->generateReceipt($sale);
        $data = json_decode($response->getContent(), true);

        $receipt = $data['data']['receipt'];

        $this->assertSame('SALE', $receipt['receipt_type']);
        $this->assertSame($sale->sale_number, $receipt['sale_number']);
        $this->assertSame('Main Store', $receipt['store']['name']);
        $this->assertCount(1, $receipt['items']);
        $this->assertEquals(500.00, $receipt['items'][0]['subtotal']);
        $this->assertCount(2, $receipt['payments']);
        $this->assertEqualsCanonicalizing(
            [300.00, 250.00],
            array_column($receipt['payments'], 'amount')
        );
        $this->assertEquals(550.00, $receipt['summary']['total_amount']);
        $this->assertEquals(0.0, $receipt['summary']['change_due']);
    }

    public function test_generate_receipt_computes_change_due_for_cash_overpayment(): void
    {
        DB::connection('tenant')->table('stores')->insert([
            'id' => 1,
            'name' => 'Main Store',
            'address' => '123 Test Street',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $sale = Model::withoutEvents(fn () => Sale::create([
            'sale_number' => 'SALE-'.uniqid(),
            'store_id' => 1,
            'sale_date' => now(),
            'subtotal' => 100.00,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total_amount' => 100.00,
            'payment_status' => 'paid',
            'amount_paid' => 150.00,
            'amount_due' => 0,
            'payment_method' => PaymentMethod::CASH,
        ]));

        $response = $this->makeController()->generateReceipt($sale);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(50.0, $data['data']['receipt']['summary']['change_due']);
    }

    private function dropTestTables(): void
    {
        foreach (['sale_payments', 'sale_items', 'sales', 'stores', 'products', 'units_of_measure'] as $table) {
            Schema::connection('tenant')->dropIfExists($table);
        }
    }

    private function createMinimalSchema(): void
    {
        $conn = 'tenant';

        Schema::connection($conn)->create('units_of_measure', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('Pieces');
            $table->string('code')->default('PCS');
            $table->timestamps();
        });

        DB::connection($conn)->table('units_of_measure')->insert([
            'id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::connection($conn)->create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('Test Product');
            $table->timestamps();
            $table->softDeletes();
        });

        DB::connection($conn)->table('products')->insert([
            'id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::connection($conn)->create('stores', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('address')->nullable();
            $table->string('phone')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection($conn)->create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('sale_number')->unique();
            $table->unsignedBigInteger('store_id');
            $table->unsignedBigInteger('shift_assignment_id')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->dateTime('sale_date');
            $table->decimal('subtotal', 15, 2);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2);
            $table->string('payment_status')->default('paid');
            $table->decimal('amount_paid', 15, 2)->default(0);
            $table->decimal('amount_due', 15, 2)->default(0);
            $table->string('payment_method')->default('cash');
            $table->string('payment_reference')->nullable();
            $table->unsignedBigInteger('coupon_id')->nullable();
            $table->decimal('loyalty_points_earned', 15, 2)->default(0);
            $table->decimal('loyalty_points_redeemed', 15, 2)->default(0);
            $table->unsignedBigInteger('served_by')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection($conn)->create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sale_id');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('product_variant_id')->nullable();
            $table->unsignedBigInteger('bundle_id')->nullable();
            $table->unsignedBigInteger('uom_id')->nullable();
            $table->decimal('quantity', 15, 4);
            $table->decimal('quantity_in_base_uom', 15, 4);
            $table->decimal('unit_price', 15, 2);
            $table->decimal('unit_cost', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('subtotal', 15, 2);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection($conn)->create('sale_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sale_id');
            $table->decimal('amount', 15, 2);
            $table->string('payment_method');
            $table->string('reference_number')->nullable();
            $table->dateTime('payment_date')->nullable();
            $table->unsignedBigInteger('received_by_user_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }
}
