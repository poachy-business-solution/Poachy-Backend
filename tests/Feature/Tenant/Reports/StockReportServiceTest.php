<?php

namespace Tests\Feature\Tenant\Reports;

use App\Services\Tenant\Reports\StockReportService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Tenant\Concerns\CreatesReportTables;
use Tests\TestCase;

class StockReportServiceTest extends TestCase
{
    use CreatesReportTables;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpReportTables();
        Carbon::setTestNow('2026-10-10 12:00:00');
        DB::connection('tenant')->table('products')->insert([
            ['id' => 1, 'name' => 'Sugar 2kg', 'reorder_level' => 5],
            ['id' => 2, 'name' => 'Cooking oil 1L', 'reorder_level' => 2],
            ['id' => 3, 'name' => 'Torch', 'reorder_level' => 1],
            ['id' => 4, 'name' => 'Bread', 'reorder_level' => 0],
        ]);
        DB::connection('tenant')->table('inventory')->insert([
            ['store_id' => 1, 'product_id' => 1, 'quantity_on_hand' => 40],
            ['store_id' => 1, 'product_id' => 2, 'quantity_on_hand' => 2],
            ['store_id' => 1, 'product_id' => 3, 'quantity_on_hand' => 10],
            ['store_id' => 1, 'product_id' => 4, 'quantity_on_hand' => 0],
        ]);
        DB::connection('tenant')->table('inventory_movements')->insert([
            'store_id' => 1, 'product_id' => 3, 'movement_type' => 'purchase', 'quantity_in_base_uom' => 10, 'unit_cost_in_base_uom' => 150,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        $this->tearDownReportTables();
        parent::tearDown();
    }

    public function test_stock_health_finds_low_dead_and_days_left(): void
    {
        $this->reportSale('2026-10-01 08:00:00', [['product_id' => 1, 'quantity' => 60, 'unit_price' => 250, 'unit_cost' => 200]]);
        $this->reportSale('2026-08-01 08:00:00', [['product_id' => 3, 'quantity' => 1, 'unit_price' => 300, 'unit_cost' => 150]]);

        $report = app(StockReportService::class)->stockHealth(1);
        $items = collect($report['items'])->keyBy('product_id');

        $this->assertSame('out', $items[4]['status']);
        $this->assertSame('low', $items[2]['status'], 'at its reorder level');
        $this->assertSame('dead', $items[3]['status'], 'last sold in August');
        $this->assertEquals(1500.0, $items[3]['stock_value']);
        $this->assertSame('ok', $items[1]['status']);
        $this->assertEquals(20.0, $items[1]['days_left'], '40 on hand, 60 sold in 30 days');
        $this->assertSame(['products' => 4, 'out' => 1, 'low' => 1, 'dead' => 1, 'dead_stock_value' => 1500.0], array_intersect_key($report['summary'], array_flip(['products', 'out', 'low', 'dead', 'dead_stock_value'])));
        $this->assertSame([4, 2, 3, 1], array_column($report['items'], 'product_id'), 'out, low, dead, ok');
    }

    public function test_under_a_week_left_counts_as_low(): void
    {
        $this->reportSale('2026-10-05 08:00:00', [['product_id' => 1, 'quantity' => 300, 'unit_price' => 250, 'unit_cost' => 200]]);

        $items = collect(app(StockReportService::class)->stockHealth(1)['items'])->keyBy('product_id');

        $this->assertEquals(4.0, $items[1]['days_left']);
        $this->assertSame('low', $items[1]['status']);
    }

    public function test_sales_by_hour_uses_the_shops_clock(): void
    {
        $this->reportSale('2026-10-10 06:15:00', [['product_id' => 1, 'quantity' => 1, 'unit_price' => 100, 'unit_cost' => 0]]);
        $this->reportSale('2026-10-10 06:45:00', [['product_id' => 1, 'quantity' => 1, 'unit_price' => 200, 'unit_cost' => 0]]);
        $this->reportSale('2026-10-10 14:00:00', [['product_id' => 1, 'quantity' => 1, 'unit_price' => 50, 'unit_cost' => 0]]);

        $report = app(StockReportService::class)->salesByHour('2026-10-10', '2026-10-10');

        $this->assertSame(2, $report['hours'][9]['sales_count'], '06:xx UTC is 09:xx in Nairobi');
        $this->assertEquals(300.0, $report['hours'][9]['sales_amount']);
        $this->assertSame(1, $report['hours'][17]['sales_count']);
        $this->assertSame(9, $report['busiest_hour']);
    }
}
