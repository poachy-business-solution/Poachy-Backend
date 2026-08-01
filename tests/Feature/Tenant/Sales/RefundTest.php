<?php

namespace Tests\Feature\Tenant\Sales;

use App\Enums\Tenant\PaymentStatus;
use App\Enums\Tenant\RefundMethod;
use App\Enums\Tenant\RefundReason;
use App\Enums\Tenant\RefundStatus;
use App\Models\Tenant\Customer;
use App\Models\Tenant\CustomerCreditTransaction;
use App\Models\Tenant\LoyaltyTransaction;
use App\Models\Tenant\ProductBatch;
use App\Models\Tenant\Sale;
use App\Models\Tenant\SaleItem;
use App\Models\Tenant\SaleItemBatchDepletion;
use App\Models\Tenant\SaleRefund;
use App\Models\Tenant\SaleRefundItem;
use App\Models\Tenant\TenantConfiguration;
use App\Services\Tenant\Inventory\InventoryMovementService;
use App\Services\Tenant\Inventory\ProductBatchService;
use App\Services\Tenant\Sales\CreditService;
use App\Services\Tenant\Sales\LoyaltyService;
use App\Services\Tenant\Sales\RefundService;
use App\Services\Tenant\Sales\ShiftSalesSummaryService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Mockery;
use Stancl\Tenancy\Contracts\Tenant;
use Tests\TestCase;

class RefundTest extends TestCase
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

        // Bind a fake tenant so tenant()->id works inside TenantConfiguration
        $fakeTenant = new \stdClass;
        $fakeTenant->id = 'test-tenant';
        app()->instance(Tenant::class, $fakeTenant);
    }

    protected function tearDown(): void
    {
        $this->dropTestTables();
        DB::connection('tenant')->statement('SET foreign_key_checks = 1');
        Mockery::close();
        parent::tearDown();
    }

    // =========================================================================
    // Happy Paths
    // =========================================================================

    public function test_full_cash_refund_restores_inventory_and_marks_sale_refunded(): void
    {
        $this->enableRefunds();

        [$sale, $saleItem] = $this->createPaidSale(total: 500.00, itemQuantity: 2.0, itemSubtotal: 500.00);

        $inventoryMock = Mockery::mock(InventoryMovementService::class);
        $inventoryMock->shouldReceive('recordReturn')->once();

        $loyaltyMock = Mockery::mock(LoyaltyService::class);
        $loyaltyMock->shouldReceive('isEnabled')->andReturn(false);

        $service = $this->makeRefundService(inventoryMock: $inventoryMock, loyaltyMock: $loyaltyMock);

        $refund = null;

        Model::withoutEvents(function () use ($service, $sale, $saleItem, &$refund): void {
            $refund = $service->processRefund($sale, [
                'store_id' => 1,
                'reason' => RefundReason::WRONG_ITEM->value,
                'refund_method' => RefundMethod::CASH->value,
                'notes' => null,
                'items' => [
                    [
                        'sale_item_id' => $saleItem->id,
                        'quantity_refunded' => 2.0,
                        'refund_amount' => 500.00,
                    ],
                ],
            ]);
        });

        $this->assertEquals(RefundStatus::COMPLETED, $refund->status);
        $this->assertEquals(500.00, (float) $refund->refund_amount);

        $this->assertSame(1, SaleRefundItem::count());
        $this->assertEquals(2.0, (float) SaleRefundItem::first()->quantity_refunded);

        $sale->refresh();
        $this->assertEquals(PaymentStatus::REFUNDED, $sale->payment_status);
    }

    public function test_partial_refund_correctly_tracks_remaining_refundable_quantity(): void
    {
        $this->enableRefunds();

        [$sale, $saleItem] = $this->createPaidSale(total: 400.00, itemQuantity: 4.0, itemSubtotal: 400.00);

        $inventoryMock = Mockery::mock(InventoryMovementService::class);
        $inventoryMock->shouldReceive('recordReturn')->once();

        $loyaltyMock = Mockery::mock(LoyaltyService::class);
        $loyaltyMock->shouldReceive('isEnabled')->andReturn(false);

        $service = $this->makeRefundService(inventoryMock: $inventoryMock, loyaltyMock: $loyaltyMock);

        Model::withoutEvents(function () use ($service, $sale, $saleItem): void {
            $service->processRefund($sale, [
                'store_id' => 1,
                'reason' => RefundReason::CUSTOMER_CHANGED_MIND->value,
                'refund_method' => RefundMethod::CASH->value,
                'notes' => null,
                'items' => [
                    [
                        'sale_item_id' => $saleItem->id,
                        'quantity_refunded' => 1.0,
                        'refund_amount' => 100.00,
                    ],
                ],
            ]);
        });

        $this->assertEquals(3.0, SaleRefundItem::getRemainingRefundableQuantity($saleItem->id));

        $sale->refresh();
        $this->assertNotEquals(PaymentStatus::REFUNDED, $sale->payment_status);
    }

    public function test_store_credit_refund_increments_customer_balance(): void
    {
        $this->enableRefunds();

        $customer = $this->createCustomer(storeCreditBalance: 0.00);
        [$sale, $saleItem] = $this->createPaidSale(
            total: 200.00,
            itemQuantity: 1.0,
            itemSubtotal: 200.00,
            customerId: $customer->id
        );

        $inventoryMock = Mockery::mock(InventoryMovementService::class);
        $inventoryMock->shouldReceive('recordReturn')->once();

        $loyaltyMock = Mockery::mock(LoyaltyService::class);
        $loyaltyMock->shouldReceive('isEnabled')->andReturn(false);

        $service = $this->makeRefundService(inventoryMock: $inventoryMock, loyaltyMock: $loyaltyMock);

        Model::withoutEvents(function () use ($service, $sale, $saleItem): void {
            $service->processRefund($sale, [
                'store_id' => 1,
                'reason' => RefundReason::NOT_AS_DESCRIBED->value,
                'refund_method' => RefundMethod::STORE_CREDIT->value,
                'notes' => null,
                'items' => [
                    [
                        'sale_item_id' => $saleItem->id,
                        'quantity_refunded' => 1.0,
                        'refund_amount' => 200.00,
                    ],
                ],
            ]);
        });

        $customer->refresh();
        $this->assertEquals(200.00, (float) $customer->store_credit_balance);
        $this->assertSame(1, CustomerCreditTransaction::count());
    }

    public function test_defective_item_refund_does_not_restore_inventory(): void
    {
        $this->enableRefunds();

        [$sale, $saleItem] = $this->createPaidSale(total: 150.00, itemQuantity: 1.0, itemSubtotal: 150.00);

        $inventoryMock = Mockery::mock(InventoryMovementService::class);
        $inventoryMock->shouldNotReceive('recordReturn');

        $loyaltyMock = Mockery::mock(LoyaltyService::class);
        $loyaltyMock->shouldReceive('isEnabled')->andReturn(false);

        $service = $this->makeRefundService(inventoryMock: $inventoryMock, loyaltyMock: $loyaltyMock);

        Model::withoutEvents(function () use ($service, $sale, $saleItem): void {
            $service->processRefund($sale, [
                'store_id' => 1,
                'reason' => RefundReason::DEFECTIVE->value,
                'refund_method' => RefundMethod::CASH->value,
                'notes' => null,
                'items' => [
                    [
                        'sale_item_id' => $saleItem->id,
                        'quantity_refunded' => 1.0,
                        'refund_amount' => 150.00,
                    ],
                ],
            ]);
        });

        $this->assertSame(1, SaleRefund::count());
        $this->assertEquals(RefundStatus::COMPLETED, SaleRefund::first()->status);
    }

    public function test_loyalty_points_reversed_proportionally_per_item(): void
    {
        $this->enableRefunds();
        $this->enableLoyalty();

        $customer = $this->createCustomer(loyaltyPoints: 100.0);
        [$sale, $saleItem] = $this->createPaidSale(
            total: 1000.00,
            itemQuantity: 4.0,
            itemSubtotal: 1000.00,
            customerId: $customer->id,
            loyaltyPointsEarned: 100.0
        );

        $inventoryMock = Mockery::mock(InventoryMovementService::class);
        $inventoryMock->shouldReceive('recordReturn')->once();

        $loyaltyMock = Mockery::mock(LoyaltyService::class);
        $loyaltyMock->shouldReceive('isEnabled')->andReturn(true);

        $service = $this->makeRefundService(inventoryMock: $inventoryMock, loyaltyMock: $loyaltyMock);

        Model::withoutEvents(function () use ($service, $sale, $saleItem): void {
            $service->processRefund($sale, [
                'store_id' => 1,
                'reason' => RefundReason::WRONG_ITEM->value,
                'refund_method' => RefundMethod::CASH->value,
                'notes' => null,
                'items' => [
                    [
                        'sale_item_id' => $saleItem->id,
                        'quantity_refunded' => 2.0,  // half of 4.0
                        'refund_amount' => 500.00,
                    ],
                ],
            ]);
        });

        // Proportion: item subtotal / sale subtotal = 1.0; qty proportion = 2/4 = 0.5
        // Points reversed = 1.0 * 100 * 0.5 = 50
        $customer->refresh();
        $this->assertEquals(50.0, (float) $customer->loyalty_points);

        $this->assertSame(1, LoyaltyTransaction::count());
        $this->assertEquals(-50.0, (float) LoyaltyTransaction::first()->points);
    }

    // =========================================================================
    // Batch-tracked restoration (SaleItemBatchDepletion reversal)
    // =========================================================================

    public function test_batch_tracked_full_refund_restores_the_depleted_batch(): void
    {
        $this->enableRefunds();

        [$sale, $saleItem] = $this->createPaidSale(total: 100.00, itemQuantity: 5.0, itemSubtotal: 100.00);
        $batch = $this->createBatch(productId: 1, quantityRemaining: 5.0, quantityReceived: 10.0);
        $this->createDepletion($saleItem->id, productId: 1, batchId: $batch->id, quantity: 5.0);

        $inventoryMock = Mockery::mock(InventoryMovementService::class);
        $inventoryMock->shouldReceive('recordReturn')->once();

        $loyaltyMock = Mockery::mock(LoyaltyService::class);
        $loyaltyMock->shouldReceive('isEnabled')->andReturn(false);

        $service = $this->makeRefundService(
            inventoryMock: $inventoryMock,
            loyaltyMock: $loyaltyMock,
            batchService: new ProductBatchService,
        );

        Model::withoutEvents(function () use ($service, $sale, $saleItem): void {
            $service->processRefund($sale, [
                'store_id' => 1,
                'reason' => RefundReason::WRONG_ITEM->value,
                'refund_method' => RefundMethod::CASH->value,
                'notes' => null,
                'items' => [
                    [
                        'sale_item_id' => $saleItem->id,
                        'quantity_refunded' => 5.0,
                        'refund_amount' => 100.00,
                    ],
                ],
            ]);
        });

        // Batch had 5 remaining out of 10 received; full refund of the 5-unit
        // sale item puts all 5 depleted units back: 5 + 5 = 10.
        $this->assertEquals(10.0, (float) $batch->fresh()->quantity_remaining_in_base_uom);
    }

    public function test_batch_tracked_partial_refund_restores_proportionally_across_batches(): void
    {
        $this->enableRefunds();

        // Sale item of 8 units originally depleted 5 from batch A and 3 from batch B.
        [$sale, $saleItem] = $this->createPaidSale(total: 800.00, itemQuantity: 8.0, itemSubtotal: 800.00);
        $batchA = $this->createBatch(productId: 1, quantityRemaining: 0.0, quantityReceived: 5.0);
        $batchB = $this->createBatch(productId: 1, quantityRemaining: 2.0, quantityReceived: 5.0);
        $this->createDepletion($saleItem->id, productId: 1, batchId: $batchA->id, quantity: 5.0);
        $this->createDepletion($saleItem->id, productId: 1, batchId: $batchB->id, quantity: 3.0);

        $inventoryMock = Mockery::mock(InventoryMovementService::class);
        $inventoryMock->shouldReceive('recordReturn')->once();

        $loyaltyMock = Mockery::mock(LoyaltyService::class);
        $loyaltyMock->shouldReceive('isEnabled')->andReturn(false);

        $service = $this->makeRefundService(
            inventoryMock: $inventoryMock,
            loyaltyMock: $loyaltyMock,
            batchService: new ProductBatchService,
        );

        Model::withoutEvents(function () use ($service, $sale, $saleItem): void {
            // Refund 4 of the original 8 units — half.
            $service->processRefund($sale, [
                'store_id' => 1,
                'reason' => RefundReason::CUSTOMER_CHANGED_MIND->value,
                'refund_method' => RefundMethod::CASH->value,
                'notes' => null,
                'items' => [
                    [
                        'sale_item_id' => $saleItem->id,
                        'quantity_refunded' => 4.0,
                        'refund_amount' => 400.00,
                    ],
                ],
            ]);
        });

        // Ratio = 4/8 = 0.5. Batch A restored 0.5*5 = 2.5 (0 + 2.5 = 2.5).
        // Batch B restored 0.5*3 = 1.5 (2 + 1.5 = 3.5).
        $this->assertEquals(2.5, (float) $batchA->fresh()->quantity_remaining_in_base_uom);
        $this->assertEquals(3.5, (float) $batchB->fresh()->quantity_remaining_in_base_uom);
    }

    public function test_non_batch_tracked_refund_does_not_touch_batch_service(): void
    {
        $this->enableRefunds();

        [$sale, $saleItem] = $this->createPaidSale(total: 100.00, itemQuantity: 1.0, itemSubtotal: 100.00);
        // No SaleItemBatchDepletion rows exist for this sale item — matches
        // a non-batch-tracked product, where depleteBatchesFIFO() never ran.

        $inventoryMock = Mockery::mock(InventoryMovementService::class);
        $inventoryMock->shouldReceive('recordReturn')->once();

        $loyaltyMock = Mockery::mock(LoyaltyService::class);
        $loyaltyMock->shouldReceive('isEnabled')->andReturn(false);

        $batchMock = Mockery::mock(ProductBatchService::class);
        $batchMock->shouldNotReceive('restoreBatchQuantity');

        $service = $this->makeRefundService(
            inventoryMock: $inventoryMock,
            loyaltyMock: $loyaltyMock,
            batchService: $batchMock,
        );

        Model::withoutEvents(function () use ($service, $sale, $saleItem): void {
            $service->processRefund($sale, [
                'store_id' => 1,
                'reason' => RefundReason::WRONG_ITEM->value,
                'refund_method' => RefundMethod::CASH->value,
                'notes' => null,
                'items' => [
                    [
                        'sale_item_id' => $saleItem->id,
                        'quantity_refunded' => 1.0,
                        'refund_amount' => 100.00,
                    ],
                ],
            ]);
        });

        $this->assertSame(1, SaleRefund::count());
    }

    // =========================================================================
    // Failure Paths
    // =========================================================================

    public function test_refunds_disabled_throws_validation_error(): void
    {
        [$sale, $saleItem] = $this->createPaidSale(total: 100.00, itemQuantity: 1.0, itemSubtotal: 100.00);

        $service = $this->makeRefundService();

        $this->expectException(ValidationException::class);

        Model::withoutEvents(function () use ($service, $sale, $saleItem): void {
            $service->processRefund($sale, [
                'store_id' => 1,
                'reason' => RefundReason::OTHER->value,
                'refund_method' => RefundMethod::CASH->value,
                'notes' => null,
                'items' => [
                    [
                        'sale_item_id' => $saleItem->id,
                        'quantity_refunded' => 1.0,
                        'refund_amount' => 100.00,
                    ],
                ],
            ]);
        });
    }

    public function test_refunding_already_fully_refunded_sale_throws_validation_error(): void
    {
        $this->enableRefunds();

        [$sale, $saleItem] = $this->createPaidSale(total: 100.00, itemQuantity: 1.0, itemSubtotal: 100.00);

        Model::withoutEvents(fn () => $sale->update(['payment_status' => PaymentStatus::REFUNDED]));

        Model::withoutEvents(function () use ($sale): void {
            SaleRefund::create([
                'original_sale_id' => $sale->id,
                'store_id' => 1,
                'refund_date' => now()->toDateString(),
                'refund_amount' => 100.00,
                'refund_method' => RefundMethod::CASH,
                'reason' => RefundReason::OTHER,
                'status' => RefundStatus::COMPLETED,
                'processed_by' => null,
                'approved_by' => null,
                'approved_at' => now(),
            ]);
        });

        $service = $this->makeRefundService();

        $this->expectException(ValidationException::class);

        Model::withoutEvents(function () use ($service, $sale, $saleItem): void {
            $service->processRefund($sale, [
                'store_id' => 1,
                'reason' => RefundReason::OTHER->value,
                'refund_method' => RefundMethod::CASH->value,
                'notes' => null,
                'items' => [
                    [
                        'sale_item_id' => $saleItem->id,
                        'quantity_refunded' => 1.0,
                        'refund_amount' => 100.00,
                    ],
                ],
            ]);
        });
    }

    public function test_refund_quantity_exceeds_remaining_throws_validation_error(): void
    {
        $this->enableRefunds();

        [$sale, $saleItem] = $this->createPaidSale(total: 100.00, itemQuantity: 2.0, itemSubtotal: 100.00);

        $service = $this->makeRefundService();

        $this->expectException(ValidationException::class);

        Model::withoutEvents(function () use ($service, $sale, $saleItem): void {
            $service->processRefund($sale, [
                'store_id' => 1,
                'reason' => RefundReason::WRONG_ITEM->value,
                'refund_method' => RefundMethod::CASH->value,
                'notes' => null,
                'items' => [
                    [
                        'sale_item_id' => $saleItem->id,
                        'quantity_refunded' => 5.0,  // exceeds original 2.0
                        'refund_amount' => 250.00,
                    ],
                ],
            ]);
        });
    }

    public function test_item_not_belonging_to_sale_throws_validation_error(): void
    {
        $this->enableRefunds();

        [$sale] = $this->createPaidSale(total: 100.00, itemQuantity: 1.0, itemSubtotal: 100.00);

        $otherSale = $this->createRawSale(total: 50.00);
        $foreignItem = Model::withoutEvents(fn () => SaleItem::create([
            'sale_id' => $otherSale->id,
            'product_id' => 1,
            'uom_id' => 1,
            'quantity' => 1.0,
            'quantity_in_base_uom' => 1.0,
            'unit_price' => 50.00,
            'unit_cost' => 25.00,
            'discount_amount' => 0.00,
            'tax_amount' => 0.00,
            'subtotal' => 50.00,
        ]));

        $service = $this->makeRefundService();

        $this->expectException(ValidationException::class);

        Model::withoutEvents(function () use ($service, $sale, $foreignItem): void {
            $service->processRefund($sale, [
                'store_id' => 1,
                'reason' => RefundReason::WRONG_ITEM->value,
                'refund_method' => RefundMethod::CASH->value,
                'notes' => null,
                'items' => [
                    [
                        'sale_item_id' => $foreignItem->id,
                        'quantity_refunded' => 1.0,
                        'refund_amount' => 50.00,
                    ],
                ],
            ]);
        });
    }

    public function test_cancel_processing_refund_sets_status_cancelled(): void
    {
        $refund = Model::withoutEvents(fn () => SaleRefund::create([
            'original_sale_id' => 1,
            'store_id' => 1,
            'refund_date' => now()->toDateString(),
            'refund_amount' => 100.00,
            'refund_method' => RefundMethod::CASH,
            'reason' => RefundReason::OTHER,
            'status' => RefundStatus::PROCESSING,
            'processed_by' => null,
            'approved_by' => null,
        ]));

        $service = $this->makeRefundService();
        $cancelled = $service->cancelRefund($refund);

        $this->assertEquals(RefundStatus::CANCELLED, $cancelled->status);
    }

    public function test_cancel_completed_refund_throws_validation_error(): void
    {
        $refund = Model::withoutEvents(fn () => SaleRefund::create([
            'original_sale_id' => 1,
            'store_id' => 1,
            'refund_date' => now()->toDateString(),
            'refund_amount' => 100.00,
            'refund_method' => RefundMethod::CASH,
            'reason' => RefundReason::OTHER,
            'status' => RefundStatus::COMPLETED,
            'processed_by' => null,
            'approved_by' => null,
        ]));

        $service = $this->makeRefundService();

        $this->expectException(ValidationException::class);

        $service->cancelRefund($refund);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /** @return array{Sale, SaleItem} */
    private function createPaidSale(
        float $total,
        float $itemQuantity,
        float $itemSubtotal,
        ?int $customerId = null,
        float $loyaltyPointsEarned = 0.0
    ): array {
        $sale = $this->createRawSale(
            total: $total,
            customerId: $customerId,
            loyaltyPointsEarned: $loyaltyPointsEarned
        );

        $saleItem = Model::withoutEvents(fn () => SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => 1,
            'uom_id' => 1,
            'quantity' => $itemQuantity,
            'quantity_in_base_uom' => $itemQuantity,
            'unit_price' => round($itemSubtotal / $itemQuantity, 2),
            'unit_cost' => round($itemSubtotal / $itemQuantity / 2, 2),
            'discount_amount' => 0.00,
            'tax_amount' => 0.00,
            'subtotal' => $itemSubtotal,
        ]));

        return [$sale, $saleItem];
    }

    private function createRawSale(
        float $total,
        ?int $customerId = null,
        float $loyaltyPointsEarned = 0.0
    ): Sale {
        return Model::withoutEvents(fn () => Sale::create([
            'sale_number' => 'SALE-'.uniqid(),
            'store_id' => 1,
            'customer_id' => $customerId,
            'sale_date' => now(),
            'subtotal' => $total,
            'tax_amount' => 0.00,
            'discount_amount' => 0.00,
            'total_amount' => $total,
            'payment_status' => PaymentStatus::PAID,
            'amount_paid' => $total,
            'amount_due' => 0.00,
            'loyalty_points_earned' => $loyaltyPointsEarned,
            'loyalty_points_redeemed' => 0.00,
        ]));
    }

    private function createCustomer(float $loyaltyPoints = 0.0, float $storeCreditBalance = 0.0): Customer
    {
        return Model::withoutEvents(fn () => Customer::create([
            'name' => 'Test Customer',
            'phone' => '+254700'.rand(100000, 999999),
            'loyalty_points' => $loyaltyPoints,
            'store_credit_balance' => $storeCreditBalance,
            'current_debt' => 0.00,
        ]));
    }

    private function createBatch(int $productId, float $quantityRemaining, float $quantityReceived): ProductBatch
    {
        return Model::withoutEvents(fn () => ProductBatch::create([
            'store_id' => 1,
            'product_id' => $productId,
            'batch_number' => 'BATCH-TEST-'.uniqid(),
            'quantity_received_in_purchase_uom' => $quantityReceived,
            'quantity_received_in_base_uom' => $quantityReceived,
            'quantity_remaining_in_base_uom' => $quantityRemaining,
            'cost_per_purchase_uom' => 5.00,
            'cost_per_base_uom' => 5.00,
            'total_cost' => $quantityReceived * 5.00,
        ]));
    }

    private function createDepletion(int $saleItemId, int $productId, int $batchId, float $quantity): SaleItemBatchDepletion
    {
        return SaleItemBatchDepletion::create([
            'sale_item_id' => $saleItemId,
            'product_id' => $productId,
            'batch_id' => $batchId,
            'quantity_in_base_uom' => $quantity,
        ]);
    }

    private function enableRefunds(): void
    {
        Model::withoutEvents(fn () => TenantConfiguration::updateOrCreate(
            ['config_key' => 'pos.refunds_enabled'],
            ['config_value' => true, 'config_type' => 'pos', 'is_active' => true]
        ));
    }

    private function enableLoyalty(): void
    {
        Model::withoutEvents(fn () => TenantConfiguration::updateOrCreate(
            ['config_key' => 'loyalty_enabled'],
            ['config_value' => true, 'config_type' => 'loyalty', 'is_active' => true]
        ));
    }

    private function makeRefundService(
        ?InventoryMovementService $inventoryMock = null,
        ?LoyaltyService $loyaltyMock = null,
        ?ProductBatchService $batchService = null,
    ): RefundService {
        $inventoryMock ??= Mockery::spy(InventoryMovementService::class);

        if ($loyaltyMock === null) {
            $loyaltyMock = Mockery::mock(LoyaltyService::class);
            $loyaltyMock->shouldReceive('isEnabled')->andReturn(false)->byDefault();
        }

        return new RefundService(
            inventoryMovementService: $inventoryMock,
            batchService: $batchService ?? Mockery::spy(ProductBatchService::class),
            loyaltyService: $loyaltyMock,
            creditService: Mockery::spy(CreditService::class),
            shiftSalesSummaryService: Mockery::spy(ShiftSalesSummaryService::class),
        );
    }

    private function dropTestTables(): void
    {
        foreach ([
            'sale_item_batch_depletions',
            'product_batches',
            'sale_refund_items',
            'sale_refunds',
            'loyalty_transactions',
            'customer_credit_transactions',
            'sale_items',
            'sales',
            'customers',
            'products',
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

        DB::connection($conn)->table('stores')->insert(['id' => 1, 'name' => 'Main Store', 'created_at' => now(), 'updated_at' => now()]);

        Schema::connection($conn)->create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug');
            $table->string('sku')->unique();
            $table->string('product_type')->default('simple');
            $table->string('stock_status')->default('in_stock');
            $table->boolean('requires_batch_tracking')->default(false);
            $table->unsignedBigInteger('base_uom_id')->default(1);
            $table->boolean('is_available_online')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        DB::connection($conn)->table('products')->insert([
            'id' => 1,
            'name' => 'Test Product',
            'slug' => 'test-product',
            'sku' => 'SKU-TEST-001',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

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
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->string('payment_status')->default('paid');
            $table->string('payment_method')->default('cash');
            $table->decimal('amount_paid', 12, 2)->default(0);
            $table->decimal('amount_due', 12, 2)->default(0);
            $table->decimal('loyalty_points_earned', 12, 2)->default(0);
            $table->decimal('loyalty_points_redeemed', 12, 2)->default(0);
            $table->unsignedBigInteger('served_by')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection($conn)->create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sale_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('product_variant_id')->nullable();
            $table->unsignedBigInteger('bundle_id')->nullable();
            $table->unsignedBigInteger('uom_id')->default(1);
            $table->decimal('quantity', 12, 4)->default(1);
            $table->decimal('quantity_in_base_uom', 12, 4)->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->unsignedBigInteger('tax_rate_id')->nullable();
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection($conn)->create('sale_refunds', function (Blueprint $table) {
            $table->id();
            $table->string('refund_number')->nullable();
            $table->unsignedBigInteger('original_sale_id');
            $table->unsignedBigInteger('store_id');
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->date('refund_date');
            $table->decimal('refund_amount', 12, 2)->default(0);
            $table->string('refund_method');
            $table->string('reason');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('processed_by')->nullable();
            $table->string('status')->default('processing');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->unsignedBigInteger('exchange_sale_id')->nullable();
            $table->timestamps();
        });

        Schema::connection($conn)->create('sale_refund_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('refund_id');
            $table->unsignedBigInteger('sale_item_id');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->decimal('quantity_refunded', 12, 4)->default(0);
            $table->decimal('quantity_refunded_in_base_uom', 12, 4)->default(0);
            $table->decimal('refund_amount', 12, 2)->default(0);
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

        Schema::connection($conn)->create('product_batches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('product_variant_id')->nullable();
            $table->unsignedBigInteger('purchase_order_id')->nullable();
            $table->string('batch_number')->unique();
            $table->unsignedBigInteger('purchase_uom_id')->default(1);
            $table->decimal('quantity_received_in_purchase_uom', 12, 4)->default(0);
            $table->decimal('quantity_received_in_base_uom', 12, 4)->default(0);
            $table->decimal('quantity_remaining_in_base_uom', 12, 4)->default(0);
            $table->decimal('cost_per_purchase_uom', 10, 2)->default(0);
            $table->decimal('cost_per_base_uom', 10, 2)->default(0);
            $table->decimal('total_cost', 10, 2)->default(0);
            $table->date('manufacture_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->boolean('is_expired')->default(false);
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection($conn)->create('sale_item_batch_depletions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sale_item_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('batch_id');
            $table->decimal('quantity_in_base_uom', 15, 4);
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
