<?php

namespace Tests\Feature\Tenant\Sales;

use App\Enums\Tenant\RefundMethod;
use App\Enums\Tenant\RefundReason;
use App\Enums\Tenant\RefundStatus;
use App\Models\Tenant\Sale;
use App\Models\Tenant\SaleRefund;
use App\Models\Tenant\ShiftSalesSummary;
use App\Services\Tenant\Sales\ShiftSalesSummaryService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Stancl\Tenancy\Contracts\Tenant as TenantContract;
use Tests\TestCase;

class ShiftSalesSummaryServiceTest extends TestCase
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
        parent::tearDown();
    }

    private function createSale(int $shiftAssignmentId, float $total, string $paymentMethod = 'cash'): Sale
    {
        return Model::withoutEvents(fn () => Sale::create([
            'sale_number' => 'SALE-'.uniqid(),
            'store_id' => 1,
            'shift_assignment_id' => $shiftAssignmentId,
            'sale_date' => now(),
            'subtotal' => $total,
            'total_amount' => $total,
            'payment_status' => 'paid',
            'amount_paid' => $total,
            'amount_due' => 0,
            'payment_method' => $paymentMethod,
        ]));
    }

    private function createRefund(Sale $sale, float $amount): SaleRefund
    {
        return Model::withoutEvents(fn () => SaleRefund::create([
            'original_sale_id' => $sale->id,
            'store_id' => 1,
            'refund_date' => now()->toDateString(),
            'refund_amount' => $amount,
            'refund_method' => RefundMethod::CASH,
            'reason' => RefundReason::CUSTOMER_CHANGED_MIND,
            'status' => RefundStatus::COMPLETED,
        ]));
    }

    public function test_recalculate_summary_correctly_populates_refund_totals(): void
    {
        $sale = $this->createSale(shiftAssignmentId: 1, total: 1000.00);
        $this->createRefund($sale, 200.00);
        $this->createRefund($sale, 50.00);

        $summary = (new ShiftSalesSummaryService)->recalculateSummary(1);

        $this->assertSame(2, $summary->total_refunds);
        $this->assertSame(250.00, (float) $summary->total_refund_amount);
    }

    public function test_recalculate_summary_does_not_count_refunds_from_other_shifts(): void
    {
        $saleThisShift = $this->createSale(shiftAssignmentId: 1, total: 1000.00);
        $saleOtherShift = $this->createSale(shiftAssignmentId: 2, total: 500.00);

        $this->createRefund($saleThisShift, 100.00);
        $this->createRefund($saleOtherShift, 999.00);

        $summary = (new ShiftSalesSummaryService)->recalculateSummary(1);

        $this->assertSame(1, $summary->total_refunds);
        $this->assertSame(100.00, (float) $summary->total_refund_amount);
    }

    public function test_recalculate_summary_computes_gross_total_sales_amount(): void
    {
        $sale = $this->createSale(shiftAssignmentId: 1, total: 1000.00);
        $this->createRefund($sale, 200.00);

        $summary = (new ShiftSalesSummaryService)->recalculateSummary(1);

        // total_sales_amount stays gross — net_sales is the only place refunds are netted out.
        $this->assertSame(1000.00, (float) $summary->total_sales_amount);
        $this->assertSame(800.00, $summary->net_sales);
    }

    public function test_net_sales_is_not_double_subtracted_after_a_refund_via_update_from_sale(): void
    {
        $sale = $this->createSale(shiftAssignmentId: 1, total: 1000.00);

        $service = new ShiftSalesSummaryService;
        $service->updateFromSale($sale->fresh(['payments']));

        // Simulate exactly what RefundService::updateShiftSummary() does now.
        $summary = ShiftSalesSummary::where('shift_assignment_id', 1)->firstOrFail();
        $summary->total_refunds = 1;
        $summary->total_refund_amount = 200.00;
        $summary->save();

        $this->assertSame(1000.00, (float) $summary->fresh()->total_sales_amount);
        $this->assertSame(800.00, $summary->fresh()->net_sales);
    }

    private function dropTestTables(): void
    {
        foreach (['sale_refunds', 'sale_payments', 'sales', 'shift_sales_summary'] as $table) {
            Schema::connection('tenant')->dropIfExists($table);
        }
    }

    private function createMinimalSchema(): void
    {
        $conn = 'tenant';

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

        Schema::connection($conn)->create('sale_refunds', function (Blueprint $table) {
            $table->id();
            $table->string('refund_number')->nullable();
            $table->unsignedBigInteger('original_sale_id');
            $table->unsignedBigInteger('store_id')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->date('refund_date');
            $table->decimal('refund_amount', 15, 2);
            $table->string('refund_method');
            $table->string('reason');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('processed_by')->nullable();
            $table->string('status');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->unsignedBigInteger('exchange_sale_id')->nullable();
            $table->timestamps();
        });

        Schema::connection($conn)->create('shift_sales_summary', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shift_assignment_id')->unique();
            $table->integer('total_transactions')->default(0);
            $table->decimal('total_sales_amount', 15, 2)->default(0);
            $table->decimal('total_cash_sales', 15, 2)->default(0);
            $table->decimal('total_card_sales', 15, 2)->default(0);
            $table->decimal('total_mpesa_sales', 15, 2)->default(0);
            $table->decimal('total_credit_sales', 15, 2)->default(0);
            $table->integer('total_refunds')->default(0);
            $table->decimal('total_refund_amount', 15, 2)->default(0);
            $table->decimal('total_discounts_given', 15, 2)->default(0);
            $table->integer('unique_customers')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }
}
