<?php

namespace Tests\Feature\Tenant\Trade;

use App\Enums\Tenant\InventoryMovementType;
use App\Enums\Tenant\TradeDirection;
use App\Models\Tenant\CashMovement;
use App\Models\Tenant\Product;
use App\Models\Tenant\TradeLine;
use App\Models\Tenant\TradePartner;
use App\Models\Tenant\User;
use App\Services\Tenant\Inventory\InventoryMovementService;
use App\Services\Tenant\Product\ProductStockReceivingService;
use App\Services\Tenant\Shift\TillService;
use App\Services\Tenant\Trade\TradeService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Mockery;
use Mockery\MockInterface;
use Tests\Feature\Tenant\Concerns\InteractsWithTenantAuthorization;
use Tests\TestCase;

class TradeServiceTest extends TestCase
{
    use InteractsWithTenantAuthorization;

    private const TABLES = ['trade_events', 'trade_lines', 'trade_partners', 'cash_movements', 'suppliers', 'products', 'product_batches', 'inventory', 'inventory_movements'];

    private User $owner;

    private TradePartner $hassan;

    /** @var MockInterface&InventoryMovementService */
    private MockInterface $movements;

    /** @var MockInterface&ProductStockReceivingService */
    private MockInterface $receiving;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenantAuthorization();
        foreach (self::TABLES as $table) {
            Schema::connection('tenant')->dropIfExists($table);
        }
        $this->createTradeSchema();
        Carbon::setTestNow('2026-10-10 09:00:00');

        DB::connection('tenant')->table('stores')->insert(['id' => 1, 'name' => 'CBD', 'is_active' => true, 'is_main_store' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::connection('tenant')->table('products')->insert([
            ['id' => 1, 'name' => 'Sugar 2kg', 'base_uom_id' => 1, 'requires_serial_tracking' => false, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'Cooking oil 1L', 'base_uom_id' => 1, 'requires_serial_tracking' => false, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'name' => 'Phone', 'base_uom_id' => 1, 'requires_serial_tracking' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::connection('tenant')->table('inventory_movements')->insert([
            'store_id' => 1, 'product_id' => 1, 'movement_type' => 'purchase', 'quantity_in_base_uom' => 20, 'unit_cost_in_base_uom' => 205, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->owner = $this->makeTenantUserWithRole('owner');
        $this->hassan = TradePartner::create(['name' => 'Hassan Shop']);

        $this->movements = Mockery::mock(InventoryMovementService::class);
        $this->movements->shouldReceive('recordMovement')->byDefault();
        $this->receiving = Mockery::mock(ProductStockReceivingService::class);
        $this->receiving->shouldReceive('receive')->byDefault()->andReturn([]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        foreach (self::TABLES as $table) {
            Schema::connection('tenant')->dropIfExists($table);
        }
        $this->tearDownTenantAuthorization();
        parent::tearDown();
    }

    private function service(): TradeService
    {
        $till = Mockery::mock(TillService::class);
        $till->shouldReceive('sessionForSale')->andReturnNull();

        return new TradeService($this->movements, $this->receiving, $till);
    }

    public function test_they_took_goods_moves_stock_out_and_opens_a_line_at_the_agreed_price(): void
    {
        $this->movements->shouldReceive('recordMovement')->once()->withArgs(fn (array $m) => $m['product_id'] === 1
            && $m['quantity'] === -3.0
            && $m['movement_type'] === InventoryMovementType::TRANSFER_OUT
            && $m['unit_cost'] === 205.0
            && $m['reference_type'] === 'TradeLine');

        $line = $this->service()->recordGoodsOut($this->hassan, $this->owner, 1, [['product_id' => 1, 'quantity' => 3, 'unit_price' => 230]])->first();

        $this->assertSame(TradeDirection::LENT, $line->direction);
        $this->assertEquals(205.0, (float) $line->unit_cost);
        $this->assertSame(['they_owe' => 690.0, 'you_owe' => 0.0, 'net' => 690.0], array_intersect_key($this->hassan->fresh()->balance(), array_flip(['they_owe', 'you_owe', 'net'])));
        $this->assertEquals(615.0, $this->hassan->fresh()->balance()['goods_out_value'], 'goods out valued at my cost');
    }

    public function test_i_took_goods_receives_stock_at_the_agreed_price_from_the_partners_supplier(): void
    {
        $this->receiving->shouldReceive('receive')->once()->withArgs(fn (Product $product, array $data) => $product->id === 2
            && $data['unit_cost'] === 380.0
            && $data['quantity'] === 2.0
            && $data['supplier_id'] !== null)->andReturn([]);

        $line = $this->service()->recordGoodsIn($this->hassan, $this->owner, 1, [['product_id' => 2, 'quantity' => 2, 'unit_price' => 380]])->first();

        $this->assertSame(TradeDirection::BORROWED, $line->direction);
        $this->assertNotNull($this->hassan->fresh()->supplier_id, 'a supplier record now stands for the partner');
        $this->assertEquals(760.0, $this->hassan->fresh()->balance()['you_owe']);
    }

    public function test_goods_brought_back_reduce_what_is_owed_and_restock(): void
    {
        $line = $this->service()->recordGoodsOut($this->hassan, $this->owner, 1, [['product_id' => 1, 'quantity' => 3, 'unit_price' => 230]])->first();
        $this->movements->shouldReceive('recordMovement')->once()->withArgs(fn (array $m) => $m['quantity'] === 1.0 && $m['movement_type'] === InventoryMovementType::TRANSFER_IN);

        $this->service()->recordReturn($this->hassan, $this->owner, 1, [['trade_line_id' => $line->id, 'quantity' => 1]]);

        $this->assertEquals(460.0, $this->hassan->fresh()->balance()['they_owe']);
        $this->assertSame(TradeLine::STATUS_OPEN, $line->fresh()->status);
    }

    public function test_goods_with_an_unknown_cost_come_back_without_inventing_one(): void
    {
        $line = $this->service()->recordGoodsOut($this->hassan, $this->owner, 1, [['product_id' => 2, 'quantity' => 2, 'unit_price' => 380]])->first();
        $this->assertNull($line->unit_cost);
        $this->movements->shouldReceive('recordMovement')->once()->withArgs(fn (array $m) => $m['unit_cost'] === null);

        $this->service()->recordReturn($this->hassan, $this->owner, 1, [['trade_line_id' => $line->id, 'quantity' => 1]]);
    }

    public function test_cannot_bring_back_more_than_is_out(): void
    {
        $line = $this->service()->recordGoodsOut($this->hassan, $this->owner, 1, [['product_id' => 1, 'quantity' => 3, 'unit_price' => 230]])->first();

        $this->expectException(ValidationException::class);
        $this->service()->recordReturn($this->hassan, $this->owner, 1, [['trade_line_id' => $line->id, 'quantity' => 4]]);
    }

    public function test_payments_settle_the_oldest_lines_first(): void
    {
        $first = $this->service()->recordGoodsOut($this->hassan, $this->owner, 1, [['product_id' => 1, 'quantity' => 3, 'unit_price' => 230]])->first();
        Carbon::setTestNow('2026-10-11 09:00:00');
        $second = $this->service()->recordGoodsOut($this->hassan, $this->owner, 1, [['product_id' => 2, 'quantity' => 2, 'unit_price' => 380]])->first();

        $this->service()->recordPayment($this->hassan, $this->owner, 1, 'to_me', 900, 'mpesa', 'QKX1');

        $this->assertSame(TradeLine::STATUS_SETTLED, $first->fresh()->status);
        $this->assertEquals(210.0, (float) $second->fresh()->amount_paid);
        $this->assertEquals(550.0, $this->hassan->fresh()->balance()['they_owe']);
        $this->assertSame(0, CashMovement::query()->count(), 'M-Pesa never touches the drawer');
    }

    public function test_cash_payments_go_through_the_drawer(): void
    {
        $this->service()->recordGoodsIn($this->hassan, $this->owner, 1, [['product_id' => 2, 'quantity' => 2, 'unit_price' => 380]]);

        $event = $this->service()->recordPayment($this->hassan, $this->owner, 1, 'by_me', 760, 'cash');

        $movement = CashMovement::query()->sole();
        $this->assertSame(CashMovement::DIRECTION_OUT, $movement->direction);
        $this->assertSame($movement->id, $event->cash_movement_id);
        $this->assertEquals(0.0, $this->hassan->fresh()->balance()['you_owe']);
    }

    public function test_cannot_pay_more_than_is_owed(): void
    {
        $this->service()->recordGoodsOut($this->hassan, $this->owner, 1, [['product_id' => 1, 'quantity' => 1, 'unit_price' => 230]]);

        try {
            $this->service()->recordPayment($this->hassan, $this->owner, 1, 'to_me', 500, 'cash');
            $this->fail('Expected a validation error');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('KSh 230.00', $e->errors()['amount'][0]);
        }
    }

    public function test_paid_goods_cannot_also_be_brought_back(): void
    {
        $line = $this->service()->recordGoodsOut($this->hassan, $this->owner, 1, [['product_id' => 1, 'quantity' => 2, 'unit_price' => 230]])->first();
        $this->service()->recordPayment($this->hassan, $this->owner, 1, 'to_me', 300, 'mpesa');

        $this->expectException(ValidationException::class);
        $this->service()->recordReturn($this->hassan, $this->owner, 1, [['trade_line_id' => $line->id, 'quantity' => 2]]);
    }

    public function test_price_hint_offers_the_last_price_and_my_cost(): void
    {
        $this->service()->recordGoodsOut($this->hassan, $this->owner, 1, [['product_id' => 1, 'quantity' => 1, 'unit_price' => 230]]);
        Carbon::setTestNow('2026-10-12 09:00:00');
        $this->service()->recordGoodsOut($this->hassan, $this->owner, 1, [['product_id' => 1, 'quantity' => 1, 'unit_price' => 240]]);

        $hint = $this->service()->priceHint($this->hassan, 1, 1);

        $this->assertEquals(240.0, $hint['last_price']);
        $this->assertEquals(205.0, $hint['my_cost']);
    }

    public function test_serial_tracked_goods_are_refused(): void
    {
        $this->expectException(ValidationException::class);
        $this->service()->recordGoodsOut($this->hassan, $this->owner, 1, [['product_id' => 3, 'quantity' => 1, 'unit_price' => 9000]]);
    }

    private function createTradeSchema(): void
    {
        $schema = Schema::connection('tenant');
        $schema->create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('base_uom_id')->default(1);
            $table->boolean('requires_serial_tracking')->default(false);
            $table->timestamps();
        });
        $schema->create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('supplier_type');
            $table->string('phone')->nullable();
            $table->text('notes')->nullable();
            $table->decimal('credit_limit', 12, 2)->default(0);
            $table->decimal('outstanding_balance', 12, 2)->default(0);
            $table->string('payment_terms')->default('cod');
            $table->decimal('rating', 3, 2)->default(0);
            $table->integer('total_orders')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
        $schema->create('product_batches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('product_variant_id')->nullable();
            $table->decimal('quantity_remaining_in_base_uom', 15, 4)->default(0);
            $table->decimal('cost_per_base_uom', 15, 4)->default(0);
            $table->boolean('is_expired')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
        $schema->create('inventory', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('product_variant_id')->nullable();
            $table->decimal('quantity_on_hand', 15, 4)->default(0);
            $table->decimal('quantity_reserved', 15, 4)->default(0);
            $table->decimal('quantity_available', 15, 4)->default(0);
            $table->decimal('quantity_damaged', 15, 4)->default(0);
            $table->decimal('average_cost', 15, 4)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        $schema->create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('product_variant_id')->nullable();
            $table->string('movement_type');
            $table->decimal('quantity_in_base_uom', 15, 4);
            $table->decimal('unit_cost_in_base_uom', 15, 4)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        $schema->create('cash_movements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id');
            $table->unsignedBigInteger('shift_assignment_id')->nullable();
            $table->unsignedBigInteger('recorded_by');
            $table->string('direction', 3);
            $table->string('reason', 30);
            $table->decimal('amount', 15, 2);
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->string('note', 500)->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
        });
        $schema->create('trade_partners', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone', 30)->nullable();
            $table->string('note', 500)->nullable();
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        $schema->create('trade_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('trade_partner_id');
            $table->unsignedBigInteger('store_id');
            $table->string('direction', 10);
            $table->unsignedBigInteger('product_id');
            $table->decimal('quantity', 15, 4);
            $table->decimal('unit_price', 15, 2);
            $table->decimal('unit_cost', 15, 2)->nullable();
            $table->decimal('quantity_returned', 15, 4)->default(0);
            $table->decimal('amount_paid', 15, 2)->default(0);
            $table->string('status', 10)->default('open');
            $table->timestamp('opened_at');
            $table->timestamp('settled_at')->nullable();
            $table->unsignedBigInteger('recorded_by');
            $table->string('note', 500)->nullable();
            $table->timestamps();
        });
        $schema->create('trade_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('trade_partner_id');
            $table->unsignedBigInteger('store_id');
            $table->unsignedBigInteger('trade_line_id')->nullable();
            $table->string('type', 20);
            $table->unsignedBigInteger('product_id')->nullable();
            $table->decimal('quantity', 15, 4)->nullable();
            $table->decimal('amount', 15, 2);
            $table->string('payment_method', 10)->nullable();
            $table->string('payment_reference', 100)->nullable();
            $table->unsignedBigInteger('cash_movement_id')->nullable();
            $table->unsignedBigInteger('recorded_by');
            $table->timestamp('occurred_at');
            $table->string('note', 500)->nullable();
            $table->timestamps();
        });
    }
}
