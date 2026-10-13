<?php

namespace Tests\Feature\Tenant\Sales;

use App\Enums\Tenant\RefundMethod;
use App\Enums\Tenant\RefundReason;
use App\Enums\Tenant\RefundStatus;
use App\Models\Tenant\Sale;
use App\Models\Tenant\SaleItem;
use App\Models\Tenant\SalePayment;
use App\Models\Tenant\SaleRefund;
use App\Services\Tenant\Sales\OwnerSummaryService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Facades\Schema;
use Stancl\Tenancy\Contracts\Tenant as TenantContract;
use Tests\TestCase;

class OwnerSummaryServiceTest extends TestCase
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

        DB::connection('tenant')->table('products')->insert([
            ['id' => 1, 'name' => 'Mango Juice 1L'],
            ['id' => 2, 'name' => 'Sugar 2kg'],
        ]);

        Carbon::setTestNow('2026-10-08 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        $this->dropTestTables();
        DB::connection('tenant')->statement('SET foreign_key_checks = 1');
        parent::tearDown();
    }

    private function summary(int $offset = 180): array
    {
        return app(OwnerSummaryService::class)->forDay('2026-10-08', $offset);
    }

    /**
     * @param  array<int, array{0: string, 1: float}>  $payments
     * @param  array<int, array<string, mixed>>  $items
     */
    private function sale(string $at, float $total, array $payments, array $items = []): Sale
    {
        return Model::withoutEvents(function () use ($at, $total, $payments, $items) {
            $paid = collect($payments)->filter(fn ($p) => $p[0] !== 'credit')->sum(fn ($p) => $p[1]);
            $sale = Sale::create([
                'sale_number' => 'S-'.uniqid(),
                'store_id' => 1,
                'sale_date' => Carbon::parse($at, 'UTC'),
                'subtotal' => $total,
                'total_amount' => $total,
                'payment_status' => 'paid',
                'amount_paid' => $paid,
                'amount_due' => max(0, $total - $paid),
                'payment_method' => count($payments) > 1 ? 'mixed' : $payments[0][0],
            ]);
            foreach ($payments as [$method, $amount]) {
                SalePayment::create(['sale_id' => $sale->id, 'payment_method' => $method, 'amount' => $amount]);
            }
            foreach ($items as $item) {
                SaleItem::create(array_merge([
                    'sale_id' => $sale->id,
                    'quantity' => 1,
                    'quantity_in_base_uom' => 1,
                    'unit_price' => $total,
                    'unit_cost' => 0,
                    'discount_amount' => 0,
                    'tax_amount' => 0,
                    'subtotal' => $total,
                ], $item));
            }

            return $sale;
        });
    }

    public function test_cash_is_net_of_change_and_credit_is_not_money_in(): void
    {
        $this->sale('2026-10-08 06:00:00', 348.0, [['cash', 500.0]]);
        $this->sale('2026-10-08 07:00:00', 300.0, [['mpesa', 250.0], ['cash', 100.0]]);
        $this->sale('2026-10-08 08:00:00', 1000.0, [['cash', 400.0], ['credit', 600.0]]);

        $summary = $this->summary();
        $methods = collect($summary['by_method'])->keyBy('method');

        $this->assertEquals(1648.0, $summary['takings']['gross_sales']);
        $this->assertEquals(600.0, $summary['takings']['on_credit']);
        $this->assertSame(3, $summary['takings']['transactions']);
        // 500 - 152 change + 100 - 50 change + 400
        $this->assertEquals(798.0, $methods['cash']['net']);
        $this->assertEquals(250.0, $methods['mpesa']['net']);
        $this->assertFalse($methods->has('credit'));
    }

    public function test_the_day_is_the_shops_local_day(): void
    {
        // 22:30 UTC on the 7th is 01:30 on the 8th in Nairobi (UTC+3).
        $this->sale('2026-10-07 22:30:00', 100.0, [['cash', 100.0]]);
        // 21:30 UTC on the 8th is already the 9th in Nairobi.
        $this->sale('2026-10-08 21:30:00', 50.0, [['cash', 50.0]]);

        $this->assertEquals(100.0, $this->summary(180)['takings']['gross_sales']);
        $this->assertEquals(50.0, $this->summary(0)['takings']['gross_sales']);
    }

    public function test_refunds_reduce_takings_and_the_method_they_were_paid_from(): void
    {
        $sale = $this->sale('2026-10-08 06:00:00', 200.0, [['cash', 200.0]]);
        Model::withoutEvents(fn () => SaleRefund::create([
            'original_sale_id' => $sale->id,
            'store_id' => 1,
            'refund_date' => '2026-10-08',
            'refund_amount' => 80.0,
            'refund_method' => RefundMethod::CASH,
            'reason' => RefundReason::CUSTOMER_CHANGED_MIND,
            'status' => RefundStatus::COMPLETED,
        ]));

        $summary = $this->summary();

        $this->assertEquals(80.0, $summary['takings']['refunds']);
        $this->assertEquals(120.0, $summary['takings']['net']);
        $this->assertEquals(120.0, $summary['by_method'][0]['net']);
    }

    public function test_top_sellers_rank_by_money_skip_giveaways_and_group_open_items(): void
    {
        $this->sale('2026-10-08 06:00:00', 300.0, [['cash', 300.0]], [
            ['product_id' => 1, 'quantity' => 2, 'subtotal' => 300.0],
        ]);
        $this->sale('2026-10-08 07:00:00', 500.0, [['cash', 500.0]], [
            ['product_id' => 2, 'quantity' => 1, 'subtotal' => 500.0],
            ['product_id' => 1, 'quantity' => 1, 'subtotal' => 0, 'unit_price' => 0, 'is_giveaway' => true],
        ]);
        $this->sale('2026-10-08 08:00:00', 30.0, [['cash', 30.0]], [
            ['product_id' => null, 'description' => 'Loose sweets', 'quantity' => 2, 'subtotal' => 20.0],
            ['product_id' => null, 'description' => 'loose sweets ', 'quantity' => 1, 'subtotal' => 10.0],
        ]);

        $top = $this->summary()['top_sellers'];

        $this->assertSame(['Sugar 2kg', 'Mango Juice 1L', 'Loose sweets'], array_column($top, 'name'));
        $this->assertEquals(2.0, $top[1]['quantity'], 'the free unit is not counted as sold');
        $this->assertEquals(30.0, $top[2]['revenue']);
    }

    public function test_route_requires_financial_reports_permission(): void
    {
        $route = collect(RouteFacade::getRoutes())->first(
            fn ($route) => $route->uri() === 'api/v1/tenant/reports/owner-summary'
        );

        $this->assertNotNull($route);
        $this->assertContains('permission:view-financial-reports,tenant', $route->middleware());
    }

    private function dropTestTables(): void
    {
        foreach (['sale_items', 'sale_refunds', 'sale_payments', 'sales', 'products'] as $table) {
            Schema::connection('tenant')->dropIfExists($table);
        }
    }

    private function createMinimalSchema(): void
    {
        $conn = 'tenant';

        Schema::connection($conn)->create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection($conn)->create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('sale_number');
            $table->unsignedBigInteger('store_id');
            $table->unsignedBigInteger('shift_assignment_id')->nullable();
            $table->dateTime('sale_date');
            $table->decimal('subtotal', 15, 2);
            $table->decimal('total_amount', 15, 2);
            $table->string('payment_status');
            $table->decimal('amount_paid', 15, 2)->default(0);
            $table->decimal('amount_due', 15, 2)->default(0);
            $table->string('payment_method');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection($conn)->create('sale_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sale_id');
            $table->decimal('amount', 15, 2);
            $table->string('payment_method');
            $table->timestamps();
        });

        Schema::connection($conn)->create('sale_refunds', function (Blueprint $table) {
            $table->id();
            $table->string('refund_number')->nullable();
            $table->unsignedBigInteger('original_sale_id');
            $table->unsignedBigInteger('store_id')->nullable();
            $table->date('refund_date');
            $table->decimal('refund_amount', 15, 2);
            $table->string('refund_method');
            $table->string('reason');
            $table->string('status');
            $table->unsignedBigInteger('processed_by')->nullable();
            $table->timestamps();
        });

        Schema::connection($conn)->create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sale_id');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('product_variant_id')->nullable();
            $table->unsignedBigInteger('bundle_id')->nullable();
            $table->string('description')->nullable();
            $table->unsignedBigInteger('uom_id')->nullable();
            $table->decimal('quantity', 12, 4);
            $table->decimal('quantity_in_base_uom', 12, 4);
            $table->decimal('unit_price', 15, 2);
            $table->decimal('list_unit_price', 15, 2)->nullable();
            $table->boolean('is_giveaway')->default(false);
            $table->string('price_override_reason')->nullable();
            $table->decimal('unit_cost', 15, 2)->nullable();
            $table->decimal('discount_amount', 15, 2);
            $table->unsignedBigInteger('tax_rate_id')->nullable();
            $table->decimal('tax_amount', 15, 2);
            $table->decimal('subtotal', 15, 2);
            $table->timestamps();
            $table->softDeletes();
        });
    }
}
