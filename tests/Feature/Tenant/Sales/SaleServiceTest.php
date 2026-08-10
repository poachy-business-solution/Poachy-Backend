<?php

namespace Tests\Feature\Tenant\Sales;

use App\Http\Controllers\Api\Tenant\Sales\SaleController;
use App\Http\Requests\Tenant\Sales\CreateSaleRequest;
use App\Models\Tenant\Coupon;
use App\Models\Tenant\CouponUsage;
use App\Models\Tenant\Customer;
use App\Models\Tenant\CustomerCreditTransaction;
use App\Models\Tenant\LoyaltyTransaction;
use App\Models\Tenant\Sale;
use App\Models\Tenant\SaleItem;
use App\Models\Tenant\SalePayment;
use App\Models\Tenant\TenantConfiguration;
use App\Services\Tenant\Customer\CustomerService;
use App\Services\Tenant\Inventory\InventoryMovementService;
use App\Services\Tenant\Inventory\InventoryService;
use App\Services\Tenant\Inventory\ProductBatchService;
use App\Services\Tenant\Inventory\ProductSerialService;
use App\Services\Tenant\Sales\CreditService;
use App\Services\Tenant\Sales\LoyaltyService;
use App\Services\Tenant\Sales\SaleCalculationService;
use App\Services\Tenant\Sales\SaleService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Stancl\Tenancy\Contracts\Tenant as TenantContract;
use Tests\TestCase;

class SaleServiceTest extends TestCase
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

        Cache::tags(['tenant', 'test-tenant', 'config'])->flush();

        Auth::setUser(new class implements Authenticatable
        {
            public function getAuthIdentifierName()
            {
                return 'id';
            }

            public function getAuthIdentifier()
            {
                return 1;
            }

            public function getAuthPasswordName()
            {
                return 'password';
            }

            public function getAuthPassword()
            {
                return '';
            }

            public function getRememberToken()
            {
                return null;
            }

            public function setRememberToken($value) {}

            public function getRememberTokenName()
            {
                return 'remember_token';
            }
        });
    }

    protected function tearDown(): void
    {
        $this->dropTestTables();
        DB::connection('tenant')->statement('SET foreign_key_checks = 1');
        Mockery::close();
        parent::tearDown();
    }

    // =========================================================================
    // Happy path
    // =========================================================================

    public function test_creates_sale_with_items_and_payment_for_cash_sale_without_customer(): void
    {
        $this->seedInventory(productId: 1, qtyAvailable: 10);

        $invMock = Mockery::mock(InventoryMovementService::class);
        $invMock->shouldReceive('recordSale')->once();

        $service = $this->makeService(
            $this->mockCalculation($this->calculations([$this->lineItem()])),
            inventoryMovementService: $invMock
        );

        $sale = null;
        Model::withoutEvents(function () use ($service, &$sale) {
            $sale = $service->createSale($this->baseSaleData());
        });

        $this->assertSame(1, Sale::count());
        $this->assertSame(1, SaleItem::count());
        $this->assertSame(1, SalePayment::count());
        $this->assertEquals(200.0, (float) $sale->total_amount);
        $this->assertEquals('paid', $sale->payment_status->value);
    }

    public function test_creates_walk_in_sale_when_customer_id_key_is_omitted(): void
    {
        $this->seedInventory(productId: 1, qtyAvailable: 10);

        $service = $this->makeService(
            $this->mockCalculation($this->calculations([$this->lineItem()]))
        );

        $data = $this->baseSaleData();
        unset($data['customer_id']);

        $sale = null;
        Model::withoutEvents(function () use ($service, $data, &$sale) {
            $sale = $service->createSale($data);
        });

        $this->assertSame(1, Sale::count());
        $this->assertNull($sale->customer_id);
    }

    // =========================================================================
    // Validation / failure paths
    // =========================================================================

    public function test_insufficient_stock_for_plain_product_throws_and_rolls_back(): void
    {
        // No inventory row seeded for product 1 => "not found in store inventory".
        $service = $this->makeService(
            $this->mockCalculation($this->calculations([$this->lineItem()]))
        );

        $this->expectException(\RuntimeException::class);

        try {
            Model::withoutEvents(fn () => $service->createSale($this->baseSaleData()));
        } finally {
            $this->assertSame(0, Sale::count());
        }
    }

    public function test_insufficient_stock_for_bundle_component_throws(): void
    {
        $this->seedBundle(bundleId: 1, componentProductId: 3, quantityInBaseUomPerBundle: 5.0);
        $this->seedInventory(productId: 3, qtyAvailable: 1.0);

        $service = $this->makeService(
            $this->mockCalculation($this->calculations([$this->lineItem()]))
        );

        $data = $this->baseSaleData([
            'items' => [['bundle_id' => 1, 'quantity' => 1]],
        ]);

        $this->expectException(\RuntimeException::class);

        try {
            Model::withoutEvents(fn () => $service->createSale($data));
        } finally {
            $this->assertSame(0, Sale::count());
        }
    }

    public function test_creates_sale_for_bundle_with_null_product_id_on_the_sale_item(): void
    {
        // Regression test: sale_items.product_id was a NOT NULL FK even though every
        // consumer (SaleItem::getDisplayNameAttribute(), SalesDailyAggregateService::
        // determineSellableData()) was already written to expect null product_id / set
        // bundle_id for a bundle line — fixed via the make_product_id_nullable_on_sale_items_table
        // migration. This locks in that a bundle sale actually persists now.
        $this->seedBundle(bundleId: 1, componentProductId: 3, quantityInBaseUomPerBundle: 2.0);
        $this->seedInventory(productId: 3, qtyAvailable: 10.0);

        $bundleLineItem = $this->lineItem([
            'product_id' => null,
            'variant_id' => null,
            'bundle_id' => 1,
            'quantity' => 1,
            'quantity_in_base_uom' => 1,
            'unit_price' => 100.0,
            'line_total_after_discount' => 100.0,
        ]);

        $service = $this->makeService(
            $this->mockCalculation($this->calculations([$bundleLineItem]))
        );

        $data = $this->baseSaleData([
            'items' => [['bundle_id' => 1, 'quantity' => 1]],
            'payments' => [['method' => 'cash', 'amount' => 100.0]],
        ]);

        $sale = null;
        Model::withoutEvents(function () use ($service, $data, &$sale) {
            $sale = $service->createSale($data);
        });

        $this->assertSame(1, Sale::count());
        $this->assertSame(1, SaleItem::count());

        $saleItem = SaleItem::first();
        $this->assertNull($saleItem->product_id);
        $this->assertSame(1, $saleItem->bundle_id);
    }

    public function test_invalid_loyalty_redemption_throws(): void
    {
        $this->enableLoyalty();
        $this->seedInventory(productId: 1, qtyAvailable: 10);

        $customer = $this->createCustomer(loyaltyPoints: 100.0);

        $service = $this->makeService(
            $this->mockCalculation($this->calculations([$this->lineItem()], [
                'loyalty_points_to_redeem' => 500.0,
            ]))
        );

        $data = $this->baseSaleData(['customer_id' => $customer->id]);

        $this->expectException(\RuntimeException::class);

        try {
            Model::withoutEvents(fn () => $service->createSale($data));
        } finally {
            $this->assertSame(0, Sale::count());
        }
    }

    public function test_invalid_credit_sale_throws_when_credit_disabled(): void
    {
        $this->seedInventory(productId: 1, qtyAvailable: 10);

        $customer = $this->createCustomer();

        $service = $this->makeService(
            $this->mockCalculation($this->calculations([$this->lineItem()]))
        );

        $data = $this->baseSaleData([
            'customer_id' => $customer->id,
            'payments' => [
                ['method' => 'cash', 'amount' => 100.0],
                ['method' => 'credit', 'amount' => 0.0],
            ],
        ]);

        $this->expectException(\RuntimeException::class);

        try {
            Model::withoutEvents(fn () => $service->createSale($data));
        } finally {
            $this->assertSame(0, Sale::count());
        }
    }

    public function test_insufficient_payment_without_credit_throws(): void
    {
        $this->seedInventory(productId: 1, qtyAvailable: 10);

        $service = $this->makeService(
            $this->mockCalculation($this->calculations([$this->lineItem()]))
        );

        $data = $this->baseSaleData([
            'payments' => [['method' => 'cash', 'amount' => 50.0]],
        ]);

        $this->expectException(\RuntimeException::class);

        try {
            Model::withoutEvents(fn () => $service->createSale($data));
        } finally {
            $this->assertSame(0, Sale::count());
        }
    }

    // =========================================================================
    // Inventory deduction
    // =========================================================================

    public function test_batch_tracked_product_deducts_via_batch_service_but_non_batch_product_does_not(): void
    {
        $this->seedInventory(productId: 1, qtyAvailable: 10); // non-batch-tracked
        $this->seedInventory(productId: 2, qtyAvailable: 10); // requires_batch_tracking = true

        $batchMock = Mockery::mock(ProductBatchService::class);
        $batchMock->shouldReceive('depleteBatchesFIFO')
            ->once()
            ->with(1, 2, null, 1.0)
            ->andReturn(['depletions' => [], 'total_cost' => 0, 'average_cost' => 0]);

        $lineItems = [
            $this->lineItem(['product_id' => 1, 'quantity' => 2, 'quantity_in_base_uom' => 2]),
            $this->lineItem(['product_id' => 2, 'quantity' => 1, 'quantity_in_base_uom' => 1, 'unit_cost' => 30.0, 'line_total_after_discount' => 100.0]),
        ];

        $service = $this->makeService(
            $this->mockCalculation($this->calculations($lineItems)),
            productBatchService: $batchMock
        );

        $data = $this->baseSaleData([
            'items' => [
                ['product_id' => 1, 'quantity' => 2],
                ['product_id' => 2, 'quantity' => 1],
            ],
            'payments' => [['method' => 'cash', 'amount' => 300.0]],
        ]);

        Model::withoutEvents(fn () => $service->createSale($data));

        $this->assertSame(2, SaleItem::count());
    }

    public function test_serial_tracked_product_assigns_serials_but_does_not_deplete_batches(): void
    {
        $this->seedInventory(productId: 4, qtyAvailable: 10); // requires_serial_tracking = true

        $batchMock = Mockery::mock(ProductBatchService::class);
        $batchMock->shouldNotReceive('depleteBatchesFIFO');

        $serialMock = Mockery::mock(ProductSerialService::class);
        $serialMock->shouldReceive('assignSerialsForSale')
            ->once()
            ->with(1, 4, null, ['IMEI-001'], 1.0, Mockery::type('int'))
            ->andReturn(collect());

        $lineItems = [
            $this->lineItem([
                'product_id' => 4,
                'quantity' => 1,
                'quantity_in_base_uom' => 1,
                'unit_cost' => 40.0,
                'line_total_after_discount' => 500.0,
                'serial_numbers' => ['IMEI-001'],
            ]),
        ];

        $service = $this->makeService(
            $this->mockCalculation($this->calculations($lineItems)),
            productBatchService: $batchMock,
            productSerialService: $serialMock,
        );

        $data = $this->baseSaleData([
            'items' => [
                ['product_id' => 4, 'quantity' => 1, 'serial_numbers' => ['IMEI-001']],
            ],
            'payments' => [['method' => 'cash', 'amount' => 500.0]],
        ]);

        Model::withoutEvents(fn () => $service->createSale($data));

        $this->assertSame(1, SaleItem::count());
    }

    // =========================================================================
    // Payments
    // =========================================================================

    public function test_split_payment_creates_mixed_method_and_two_payment_rows(): void
    {
        $this->seedInventory(productId: 1, qtyAvailable: 10);

        $service = $this->makeService(
            $this->mockCalculation($this->calculations([$this->lineItem()]))
        );

        $data = $this->baseSaleData([
            'payments' => [
                ['method' => 'cash', 'amount' => 100.0],
                ['method' => 'mpesa', 'amount' => 100.0, 'reference' => 'MP-123'],
            ],
        ]);

        $sale = null;
        Model::withoutEvents(function () use ($service, $data, &$sale) {
            $sale = $service->createSale($data);
        });

        $this->assertSame('mixed', $sale->payment_method->value);
        $this->assertSame(2, SalePayment::count());
        $this->assertEquals(200.0, (float) SalePayment::sum('amount'));
    }

    public function test_credit_sale_records_credit_transaction_for_the_shortfall(): void
    {
        $this->enableCredit();
        $this->seedInventory(productId: 1, qtyAvailable: 10);

        $customer = $this->createCustomer(creditLimit: 1000.0);

        $service = $this->makeService(
            $this->mockCalculation($this->calculations([$this->lineItem()]))
        );

        $data = $this->baseSaleData([
            'customer_id' => $customer->id,
            'payments' => [
                ['method' => 'cash', 'amount' => 50.0],
                ['method' => 'credit', 'amount' => 0.0],
            ],
        ]);

        $sale = null;
        Model::withoutEvents(function () use ($service, $data, &$sale) {
            $sale = $service->createSale($data);
        });

        $this->assertEquals(150.0, (float) $sale->amount_due);
        $this->assertSame(1, CustomerCreditTransaction::count());
        $this->assertEquals(150.0, (float) CustomerCreditTransaction::first()->amount);
        $this->assertSame(2, SalePayment::count());

        $customer->refresh();
        $this->assertEquals(150.0, (float) $customer->current_debt);
    }

    // =========================================================================
    // Coupons
    // =========================================================================

    public function test_coupon_usage_recorded_and_discount_distributed_proportionally(): void
    {
        $this->seedInventory(productId: 1, qtyAvailable: 10);

        $coupon = Model::withoutEvents(fn () => Coupon::create([
            'code' => 'SAVE10',
            'description' => 'Save 10',
            'discount_type' => 'fixed_amount',
            'discount_value' => 30.0,
            'usage_count' => 0,
            'valid_from' => now()->subDay(),
            'valid_until' => now()->addDay(),
            'applicable_to' => 'all_products',
            'is_active' => true,
        ]));

        $lineItems = [
            $this->lineItem(['product_id' => 1, 'line_total_after_discount' => 150.0]),
            $this->lineItem(['product_id' => 1, 'line_total_after_discount' => 50.0]),
        ];

        $service = $this->makeService(
            $this->mockCalculation($this->calculations($lineItems, [
                'coupon_discount' => 30.0,
                'coupon_applied' => true,
                'coupon_data' => $coupon,
            ]))
        );

        $data = $this->baseSaleData([
            'coupon_code' => 'SAVE10',
            'items' => [
                ['product_id' => 1, 'quantity' => 1],
                ['product_id' => 1, 'quantity' => 1],
            ],
        ]);

        Model::withoutEvents(fn () => $service->createSale($data));

        $this->assertSame(1, CouponUsage::count());
        $this->assertEquals(30.0, (float) CouponUsage::first()->discount_applied);

        $coupon->refresh();
        $this->assertSame(1, $coupon->usage_count);

        // Proportional split: 150/200 * 30 = 22.5, 50/200 * 30 = 7.5
        $items = SaleItem::orderBy('id')->get();
        $this->assertEquals(22.5, (float) $items[0]->discount_amount);
        $this->assertEquals(7.5, (float) $items[1]->discount_amount);
    }

    // =========================================================================
    // Loyalty bug-fix regressions
    // =========================================================================

    public function test_loyalty_points_earned_matches_actual_award_on_cash_overpayment(): void
    {
        $this->enableLoyalty();
        $this->setLoyaltyEarningRate(0.1);
        $this->seedInventory(productId: 1, qtyAvailable: 10);

        $customer = $this->createCustomer(loyaltyPoints: 0.0);

        $service = $this->makeService(
            $this->mockCalculation($this->calculations([$this->lineItem()], [
                // Deliberately different from what should actually be stored/awarded,
                // to prove the stored column is derived from the real payment amount,
                // not from this pricing-engine value.
                'loyalty_points_earned' => 999.0,
            ]))
        );

        // Total is 200, customer pays 250 cash (overpayment).
        $data = $this->baseSaleData([
            'customer_id' => $customer->id,
            'payments' => [['method' => 'cash', 'amount' => 250.0]],
        ]);

        $sale = null;
        Model::withoutEvents(function () use ($service, $data, &$sale) {
            $sale = $service->createSale($data);
        });

        // Uncapped: 250 * 0.1 = 25.0 (old buggy code would have stored min(250,200)*0.1 = 20.0)
        $this->assertEquals(25.0, (float) $sale->loyalty_points_earned);

        $this->assertSame(1, LoyaltyTransaction::count());
        $this->assertEquals(25.0, (float) LoyaltyTransaction::first()->points);
    }

    public function test_customer_loyalty_points_not_double_incremented(): void
    {
        $this->enableLoyalty();
        $this->setLoyaltyEarningRate(0.1);
        $this->seedInventory(productId: 1, qtyAvailable: 10);

        $customer = $this->createCustomer(loyaltyPoints: 0.0);

        $service = $this->makeService(
            $this->mockCalculation($this->calculations([$this->lineItem()], [
                // Sentinel: if updateCustomerAggregates still incremented loyalty_points
                // with this value too, the final balance would be way off (25 + 999).
                'loyalty_points_earned' => 999.0,
            ]))
        );

        $data = $this->baseSaleData([
            'customer_id' => $customer->id,
            'payments' => [['method' => 'cash', 'amount' => 250.0]],
        ]);

        Model::withoutEvents(fn () => $service->createSale($data));

        $customer->refresh();
        $this->assertEquals(25.0, (float) $customer->loyalty_points);
    }

    public function test_customer_aggregates_update_without_touching_loyalty_points(): void
    {
        // Loyalty intentionally left disabled to isolate updateCustomerAggregates.
        $this->seedInventory(productId: 1, qtyAvailable: 10);

        $customer = $this->createCustomer(loyaltyPoints: 42.0);
        Model::withoutEvents(fn () => $customer->update([
            'total_lifetime_purchases' => 100.0,
            'total_visits' => 3,
        ]));

        $service = $this->makeService(
            $this->mockCalculation($this->calculations([$this->lineItem()]))
        );

        $data = $this->baseSaleData(['customer_id' => $customer->id]);

        Model::withoutEvents(fn () => $service->createSale($data));

        $customer->refresh();
        $this->assertEquals(300.0, (float) $customer->total_lifetime_purchases);
        $this->assertSame(4, $customer->total_visits);
        $this->assertEquals(42.0, (float) $customer->loyalty_points);
    }

    // =========================================================================
    // Sale numbering
    // =========================================================================

    public function test_sale_number_sequence_increments_for_same_store_and_month(): void
    {
        $this->seedInventory(productId: 1, qtyAvailable: 10);

        $service = $this->makeService(
            $this->mockCalculation($this->calculations([$this->lineItem()]))
        );

        $first = null;
        $second = null;
        Model::withoutEvents(function () use ($service, &$first, &$second) {
            $first = $service->createSale($this->baseSaleData());
            $second = $service->createSale($this->baseSaleData());
        });

        $firstSeq = (int) substr($first->sale_number, -6);
        $secondSeq = (int) substr($second->sale_number, -6);

        $this->assertSame($firstSeq + 1, $secondSeq);
    }

    // =========================================================================
    // Controller boundary
    // =========================================================================

    public function test_controller_maps_service_runtime_exception_to_422(): void
    {
        $mockService = Mockery::mock(SaleService::class);
        $mockService->shouldReceive('createSale')->andThrow(new ModelNotFoundException);

        $controller = new SaleController($mockService, Mockery::mock(SaleCalculationService::class));

        $request = CreateSaleRequest::create('/', 'POST', [
            'store_id' => 1,
            'items' => [['product_id' => 1, 'quantity' => 1]],
            'payments' => [['method' => 'cash', 'amount' => 100]],
        ]);
        $request->setContainer($this->app);
        $request->setUserResolver(fn () => new class
        {
            public function can($ability): bool
            {
                return true;
            }
        });
        $request->validateResolved();

        $response = $controller->createSale($request);

        $this->assertSame(422, $response->getStatusCode());
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function lineItem(array $overrides = []): array
    {
        return array_merge([
            'product_id' => 1,
            'variant_id' => null,
            'bundle_id' => null,
            'uom_id' => 1,
            'quantity' => 2.0,
            'quantity_in_base_uom' => 2.0,
            'unit_price' => 100.0,
            'unit_cost' => 50.0,
            'tax_rate_id' => null,
            'tax_rate_percentage' => 0.0,
            'line_total_after_discount' => 200.0,
            'promotion_discount' => 0.0,
            'promotion_id' => null,
            'promotion_details' => null,
        ], $overrides);
    }

    private function calculations(array $lineItems, array $overrides = []): array
    {
        $total = collect($lineItems)->sum('line_total_after_discount');

        return array_merge([
            'line_items' => $lineItems,
            'base_subtotal' => $total,
            'promotion_discount' => 0.0,
            'promotions_applied' => false,
            'subtotal_after_promotions' => $total,
            'coupon_discount' => 0.0,
            'coupon_applied' => false,
            'coupon_data' => null,
            'subtotal_after_coupon' => $total,
            'tax_amount' => 0.0,
            'total_amount' => $total,
            'loyalty_points_to_redeem' => 0.0,
            'loyalty_redemption_value' => 0.0,
            'amount_payable' => $total,
            'loyalty_points_earned' => 0.0,
            'loyalty_enabled' => false,
            'stacking_info' => [],
        ], $overrides);
    }

    private function mockCalculation(array $calculations): SaleCalculationService
    {
        $mock = Mockery::mock(SaleCalculationService::class);
        $mock->shouldReceive('calculateSaleTotals')->andReturn($calculations);

        return $mock;
    }

    private function makeService(
        SaleCalculationService $calculationService,
        ?InventoryMovementService $inventoryMovementService = null,
        ?ProductBatchService $productBatchService = null,
        ?ProductSerialService $productSerialService = null,
        ?LoyaltyService $loyaltyService = null,
        ?CreditService $creditService = null,
    ): SaleService {
        return new SaleService(
            calculationService: $calculationService,
            inventoryService: new InventoryService,
            inventoryMovementService: $inventoryMovementService ?? Mockery::spy(InventoryMovementService::class),
            productBatchService: $productBatchService ?? Mockery::spy(ProductBatchService::class),
            productSerialService: $productSerialService ?? Mockery::spy(ProductSerialService::class),
            customerService: new CustomerService,
            loyaltyService: $loyaltyService ?? new LoyaltyService,
            creditService: $creditService ?? new CreditService,
        );
    }

    private function baseSaleData(array $overrides = []): array
    {
        return array_merge([
            'store_id' => 1,
            'customer_id' => null,
            'items' => [['product_id' => 1, 'quantity' => 2]],
            'payments' => [['method' => 'cash', 'amount' => 200.0]],
            'coupon_code' => null,
            'loyalty_points_to_redeem' => 0.0,
            'notes' => null,
        ], $overrides);
    }

    private function seedInventory(int $productId, float $qtyAvailable, ?int $variantId = null, int $storeId = 1): void
    {
        DB::connection('tenant')->table('inventory')->insert([
            'store_id' => $storeId,
            'product_id' => $productId,
            'product_variant_id' => $variantId,
            'quantity_on_hand' => $qtyAvailable,
            'quantity_reserved' => 0,
            'quantity_available' => $qtyAvailable,
            'quantity_damaged' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedBundle(int $bundleId, int $componentProductId, float $quantityInBaseUomPerBundle): void
    {
        DB::connection('tenant')->table('products')->insert([
            'id' => $componentProductId,
            'name' => 'Bundle Component',
            'slug' => 'bundle-component-'.$componentProductId,
            'sku' => 'SKU-BUNDLE-COMP-'.$componentProductId,
            'requires_batch_tracking' => false,
            'base_uom_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('tenant')->table('product_bundles')->insert([
            'id' => $bundleId,
            'bundle_name' => 'Test Bundle',
            'bundle_sku' => 'BUNDLE-'.$bundleId,
            'base_uom_id' => 1,
            'bundle_price' => 100.0,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('tenant')->table('product_bundle_items')->insert([
            'bundle_id' => $bundleId,
            'product_id' => $componentProductId,
            'product_variant_id' => null,
            'uom_id' => 1,
            'quantity' => 1,
            'quantity_in_base_uom' => $quantityInBaseUomPerBundle,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createCustomer(
        float $loyaltyPoints = 0.0,
        float $creditLimit = 0.0,
        float $currentDebt = 0.0,
    ): Customer {
        return Model::withoutEvents(fn () => Customer::create([
            'name' => 'Test Customer',
            'phone' => '+254700'.rand(100000, 999999),
            'loyalty_points' => $loyaltyPoints,
            'credit_limit' => $creditLimit,
            'current_debt' => $currentDebt,
            'is_active' => true,
        ]));
    }

    private function enableLoyalty(): void
    {
        Model::withoutEvents(fn () => TenantConfiguration::updateOrCreate(
            ['config_key' => 'loyalty_enabled'],
            ['config_value' => true, 'config_type' => 'loyalty', 'is_active' => true]
        ));
    }

    private function setLoyaltyEarningRate(float $rate): void
    {
        Model::withoutEvents(fn () => TenantConfiguration::updateOrCreate(
            ['config_key' => 'loyalty_earning_rate'],
            ['config_value' => $rate, 'config_type' => 'loyalty', 'is_active' => true]
        ));
    }

    private function enableCredit(): void
    {
        Model::withoutEvents(fn () => TenantConfiguration::updateOrCreate(
            ['config_key' => 'credit_enabled'],
            ['config_value' => true, 'config_type' => 'credit', 'is_active' => true]
        ));
    }

    private function seedBaseData(): void
    {
        $conn = 'tenant';

        DB::connection($conn)->table('stores')->insert([
            'id' => 1,
            'name' => 'Main Store',
            'code' => 'MAIN',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection($conn)->table('units_of_measure')->insert([
            'id' => 1,
            'code' => 'pcs',
            'name' => 'Piece',
            'type' => 'count',
            'is_base_unit' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // NOTE: multi-row insert() derives its column list from the FIRST row's
        // keys — every row here must share identical keys (in the same set),
        // or a later row's values silently land in the wrong columns.
        DB::connection($conn)->table('products')->insert([
            [
                'id' => 1,
                'name' => 'Simple Product',
                'slug' => 'simple-product',
                'sku' => 'SKU-SIMPLE-001',
                'requires_batch_tracking' => false,
                'requires_serial_tracking' => false,
                'base_uom_id' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'name' => 'Batch Tracked Product',
                'slug' => 'batch-tracked-product',
                'sku' => 'SKU-BATCH-001',
                'requires_batch_tracking' => true,
                'requires_serial_tracking' => false,
                'base_uom_id' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 4,
                'name' => 'Serial Tracked Product',
                'slug' => 'serial-tracked-product',
                'sku' => 'SKU-SERIAL-001',
                'requires_batch_tracking' => false,
                'requires_serial_tracking' => true,
                'base_uom_id' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    private function dropTestTables(): void
    {
        foreach ([
            'coupon_usage',
            'coupons',
            'loyalty_transactions',
            'customer_credit_transactions',
            'sale_payments',
            'sale_items',
            'sales',
            'product_batches',
            'product_bundle_items',
            'product_bundles',
            'shift_assignments',
            'customers',
            'inventory',
            'products',
            'units_of_measure',
            'stores',
            'tenant_configurations',
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
            $table->string('code')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection($conn)->create('units_of_measure', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->string('type');
            $table->boolean('is_base_unit')->default(false);
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::connection($conn)->create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug');
            $table->string('sku')->unique();
            $table->string('product_type')->default('simple');
            $table->string('stock_status')->default('in_stock');
            $table->boolean('requires_batch_tracking')->default(false);
            $table->boolean('requires_serial_tracking')->default(false);
            $table->unsignedBigInteger('base_uom_id')->default(1);
            $table->boolean('is_available_online')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection($conn)->create('product_bundles', function (Blueprint $table) {
            $table->id();
            $table->string('bundle_name');
            $table->string('bundle_sku')->unique();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('base_uom_id')->default(1);
            $table->decimal('bundle_price', 15, 2)->default(0);
            $table->decimal('calculated_individual_price', 15, 2)->nullable();
            $table->decimal('discount_amount', 15, 2)->nullable();
            $table->unsignedBigInteger('tax_rate_id')->nullable();
            $table->boolean('is_available_online')->default(false);
            $table->boolean('is_active')->default(true);
            $table->decimal('online_price', 15, 2)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection($conn)->create('product_bundle_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bundle_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('product_variant_id')->nullable();
            $table->unsignedBigInteger('uom_id')->default(1);
            $table->decimal('quantity', 15, 4);
            $table->decimal('quantity_in_base_uom', 15, 4);
            $table->timestamps();
        });

        Schema::connection($conn)->create('product_batches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('product_variant_id')->nullable();
            $table->decimal('quantity_remaining_in_base_uom', 15, 4)->default(0);
            $table->decimal('cost_per_base_uom', 15, 2)->default(0);
            $table->boolean('is_expired')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection($conn)->create('inventory', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('product_variant_id')->nullable();
            $table->decimal('quantity_on_hand', 15, 4)->default(0);
            $table->decimal('quantity_reserved', 15, 4)->default(0);
            $table->decimal('quantity_available', 15, 4)->default(0);
            $table->decimal('quantity_damaged', 15, 4)->default(0);
            $table->date('last_restock_date')->nullable();
            $table->date('last_stock_take_date')->nullable();
            $table->unsignedBigInteger('last_restocked_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection($conn)->create('shift_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shift_id')->nullable();
            $table->unsignedBigInteger('store_id');
            $table->unsignedBigInteger('user_id');
            $table->date('shift_date');
            $table->string('status')->default('scheduled');
            $table->timestamps();
        });

        Schema::connection($conn)->create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('customer_number')->nullable();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('customer_type')->default('walk_in');
            $table->decimal('loyalty_points', 12, 2)->default(0);
            $table->decimal('current_debt', 12, 2)->default(0);
            $table->decimal('store_credit_balance', 12, 2)->default(0);
            $table->decimal('credit_limit', 12, 2)->default(0);
            $table->decimal('total_lifetime_purchases', 12, 2)->default(0);
            $table->integer('total_visits')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('accepts_marketing')->default(false);
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
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
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
            $table->unsignedBigInteger('uom_id')->default(1);
            $table->decimal('quantity', 15, 4);
            $table->decimal('quantity_in_base_uom', 15, 4);
            $table->decimal('unit_price', 15, 2);
            $table->decimal('unit_cost', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->unsignedBigInteger('tax_rate_id')->nullable();
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

        Schema::connection($conn)->create('loyalty_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->string('transaction_type');
            $table->decimal('points', 12, 2)->default(0);
            $table->decimal('balance_after', 12, 2)->default(0);
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('description')->nullable();
            $table->date('expires_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection($conn)->create('customer_credit_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->string('transaction_type');
            $table->decimal('amount', 12, 2)->default(0);
            $table->decimal('balance_after', 12, 2)->default(0);
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('payment_method')->nullable();
            $table->string('payment_reference')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection($conn)->create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('description');
            $table->string('discount_type');
            $table->decimal('discount_value', 15, 2);
            $table->decimal('min_purchase_amount', 15, 2)->nullable();
            $table->decimal('max_discount_amount', 15, 2)->nullable();
            $table->integer('usage_limit')->nullable();
            $table->integer('usage_count')->default(0);
            $table->integer('usage_limit_per_customer')->nullable();
            $table->date('valid_from');
            $table->date('valid_until');
            $table->string('applicable_to');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection($conn)->create('coupon_usage', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('coupon_id');
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('sale_id');
            $table->decimal('discount_applied', 15, 2);
            $table->timestamp('used_at')->useCurrent();
            $table->timestamps();
        });

        Schema::connection($conn)->create('tenant_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('config_key')->unique();
            $table->json('config_value')->nullable();
            $table->string('config_type')->default('general');
            $table->string('config_group')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }
}
