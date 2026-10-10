<?php

namespace Tests\Feature\Tenant\Reports;

use App\Services\Tenant\Reports\OwnerOverviewService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route as RouteFacade;
use Tests\Feature\Tenant\Concerns\CreatesReportTables;
use Tests\TestCase;

class OwnerOverviewServiceTest extends TestCase
{
    use CreatesReportTables;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpReportTables();
        Carbon::setTestNow('2026-10-10 12:00:00');
        DB::connection('tenant')->table('products')->insert([
            ['id' => 1, 'name' => 'Sugar 2kg'],
            ['id' => 2, 'name' => 'Cooking oil 1L'],
        ]);
        DB::connection('tenant')->table('expense_categories')->insert([
            ['id' => 1, 'name' => 'Transport'],
            ['id' => 2, 'name' => 'Rent'],
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        $this->tearDownReportTables();
        parent::tearDown();
    }

    private function overview(string $from, ?string $to = null, ?int $storeId = null): array
    {
        return app(OwnerOverviewService::class)->overview($from, $to ?? $from, $storeId);
    }

    public function test_profit_is_sales_less_vat_cost_of_goods_and_expenses(): void
    {
        $this->reportSale('2026-10-10 07:00:00', [['product_id' => 1, 'quantity' => 2, 'unit_price' => 250, 'unit_cost' => 205]], tax: 80);
        $this->reportSale('2026-10-10 08:00:00', [['product_id' => 2, 'quantity' => 1, 'unit_price' => 380, 'unit_cost' => 300]], 'mpesa');
        DB::connection('tenant')->table('expenses')->insert([
            ['store_id' => 1, 'category_id' => 1, 'amount' => 300, 'expense_date' => '2026-10-10', 'approval_status' => 'pending'],
            ['store_id' => 1, 'category_id' => 1, 'amount' => 999, 'expense_date' => '2026-10-10', 'approval_status' => 'rejected'],
        ]);

        $money = $this->overview('2026-10-10')['money'];

        $this->assertEquals(960.0, $money['sales']);
        $this->assertEquals(80.0, $money['vat']);
        $this->assertEquals(710.0, $money['cost_of_goods']);
        $this->assertEquals(300.0, $money['expenses']);
        $this->assertEquals(-130.0, $money['profit']);
        $this->assertSame(2, $money['transactions']);
    }

    public function test_refunds_take_back_the_sale_and_its_cost(): void
    {
        $sale = $this->reportSale('2026-10-10 07:00:00', [['product_id' => 1, 'quantity' => 2, 'unit_price' => 250, 'unit_cost' => 200]]);
        $refundId = DB::connection('tenant')->table('sale_refunds')->insertGetId([
            'original_sale_id' => $sale->id, 'store_id' => 1, 'refund_date' => '2026-10-10', 'refund_amount' => 250,
            'refund_method' => 'cash', 'reason' => 'changed_mind', 'status' => 'completed', 'created_at' => '2026-10-10 09:00:00',
        ]);
        DB::connection('tenant')->table('sale_refund_items')->insert([
            'refund_id' => $refundId, 'sale_item_id' => $sale->items()->first()->id, 'quantity_refunded' => 1, 'quantity_refunded_in_base_uom' => 1, 'refund_amount' => 250,
        ]);

        $money = $this->overview('2026-10-10')['money'];

        $this->assertEquals(250.0, $money['sales']);
        $this->assertEquals(200.0, $money['cost_of_goods']);
        $this->assertEquals(50.0, $money['profit']);
    }

    public function test_monthly_bills_are_spread_across_the_days(): void
    {
        DB::connection('tenant')->table('expenses')->insert([
            'store_id' => 1, 'category_id' => 2, 'amount' => 700, 'expense_date' => '2026-10-01', 'is_recurring' => true,
            'recurrence_frequency' => 'weekly', 'recurrence_interval' => 1, 'recurrence_start_date' => '2026-10-01',
        ]);
        DB::connection('tenant')->table('expenses')->insert([
            'store_id' => 1, 'category_id' => 2, 'amount' => 700, 'expense_date' => '2026-10-08', 'parent_expense_id' => 1,
        ]);

        $day = $this->overview('2026-10-10');
        $week = $this->overview('2026-10-04', '2026-10-10');

        $this->assertEquals(100.0, $day['money']['spread_bills']);
        $this->assertEquals(100.0, $day['money']['expenses'], 'the generated instance is not counted twice');
        $this->assertEquals(700.0, $week['money']['expenses']);
        $this->assertSame([['category' => 'Rent', 'amount' => 100.0, 'share_pct' => 100.0, 'spread' => true]], $day['expenses_by_category']);
    }

    public function test_the_day_is_the_shops_day_in_nairobi(): void
    {
        $this->reportSale('2026-10-09 21:30:00', [['product_id' => 1, 'quantity' => 1, 'unit_price' => 100, 'unit_cost' => 0]]);
        $this->reportSale('2026-10-10 21:30:00', [['product_id' => 1, 'quantity' => 1, 'unit_price' => 999, 'unit_cost' => 0]]);

        $this->assertEquals(100.0, $this->overview('2026-10-10')['money']['sales']);
    }

    public function test_a_day_compares_with_the_same_weekday_last_week_and_yesterday(): void
    {
        $this->reportSale('2026-10-03 08:00:00', [['product_id' => 1, 'quantity' => 1, 'unit_price' => 200, 'unit_cost' => 100]]);
        $this->reportSale('2026-10-09 08:00:00', [['product_id' => 1, 'quantity' => 1, 'unit_price' => 400, 'unit_cost' => 100]]);
        $this->reportSale('2026-10-10 08:00:00', [['product_id' => 1, 'quantity' => 1, 'unit_price' => 250, 'unit_cost' => 100]]);

        $comparisons = $this->overview('2026-10-10')['comparisons'];

        $this->assertSame('same_weekday_last_week', $comparisons['headline']['key']);
        $this->assertSame('last Saturday', $comparisons['headline']['label']);
        $this->assertEquals(50.0, $comparisons['headline']['profit_change_pct']);
        $this->assertEquals(-50.0, $comparisons['items'][1]['profit_change_pct']);
    }

    public function test_a_range_compares_with_the_period_before(): void
    {
        $comparisons = $this->overview('2026-10-04', '2026-10-10')['comparisons'];

        $this->assertSame('previous_period', $comparisons['headline']['key']);
        $this->assertSame('2026-09-27', $comparisons['headline']['from']);
        $this->assertSame('2026-10-03', $comparisons['headline']['to']);
    }

    public function test_owed_both_ways_and_branches(): void
    {
        DB::connection('tenant')->table('customers')->insert(['name' => 'Asha', 'current_debt' => 1500]);
        DB::connection('tenant')->table('suppliers')->insert(['name' => 'Bidco', 'outstanding_balance' => 4000]);
        DB::connection('tenant')->table('trade_partners')->insert(['id' => 1, 'name' => 'Hassan Shop']);
        DB::connection('tenant')->table('trade_lines')->insert([
            ['trade_partner_id' => 1, 'direction' => 'lent', 'quantity' => 3, 'unit_price' => 230, 'status' => 'open', 'opened_at' => now()],
            ['trade_partner_id' => 1, 'direction' => 'borrowed', 'quantity' => 2, 'unit_price' => 400, 'status' => 'open', 'opened_at' => now()],
        ]);
        $this->reportSale('2026-10-10 08:00:00', [['product_id' => 1, 'quantity' => 1, 'unit_price' => 300, 'unit_cost' => 100]], storeId: 2);

        $overview = $this->overview('2026-10-10');

        $this->assertSame(
            ['owed_to_you' => 2190.0, 'customers' => 1500.0, 'shops_owe_you' => 690.0, 'you_owe' => 4800.0, 'shops_you_owe' => 800.0, 'suppliers' => 4000.0],
            $overview['owed'],
        );
        $this->assertSame('Westlands', $overview['branches'][0]['name']);
        $this->assertSame([], $this->overview('2026-10-10', storeId: 1)['branches']);
    }

    public function test_break_even_is_daily_costs_over_the_margin(): void
    {
        $this->reportSale('2026-10-05 08:00:00', [['product_id' => 1, 'quantity' => 10, 'unit_price' => 100, 'unit_cost' => 75]]);
        DB::connection('tenant')->table('expenses')->insert(['store_id' => 1, 'category_id' => 1, 'amount' => 3000, 'expense_date' => '2026-10-05']);

        $breakEven = $this->overview('2026-10-10')['break_even'];

        $this->assertEquals(100.0, $breakEven['daily_costs']);
        $this->assertEquals(25.0, $breakEven['margin_pct']);
        $this->assertEquals(400.0, $breakEven['daily_sales_needed']);
    }

    public function test_lines_sold_without_a_known_cost_are_flagged(): void
    {
        $this->reportSale('2026-10-10 08:00:00', [
            ['product_id' => 1, 'quantity' => 1, 'unit_price' => 100, 'unit_cost' => 0],
            ['product_id' => 2, 'quantity' => 1, 'unit_price' => 100, 'unit_cost' => 60],
            ['product_id' => 1, 'quantity' => 1, 'unit_price' => 100, 'unit_cost' => 0, 'is_giveaway' => true],
        ]);

        $this->assertSame(1, $this->overview('2026-10-10')['money']['items_without_cost']);
    }

    public function test_route_is_for_owners_and_managers(): void
    {
        $route = collect(RouteFacade::getRoutes())->first(fn ($r) => $r->uri() === 'api/v1/tenant/reports/owner-overview');

        $this->assertContains('permission:view-financial-reports,tenant', $route->middleware());
    }
}
