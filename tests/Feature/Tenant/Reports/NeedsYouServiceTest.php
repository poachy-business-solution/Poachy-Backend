<?php

namespace Tests\Feature\Tenant\Reports;

use App\Services\Tenant\Reports\NeedsYouService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Tenant\Concerns\CreatesReportTables;
use Tests\TestCase;

class NeedsYouServiceTest extends TestCase
{
    use CreatesReportTables;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpReportTables();
        Carbon::setTestNow('2026-10-10 12:00:00');
        DB::connection('tenant')->table('users')->insert(['id' => 3, 'name' => 'Brian']);
        DB::connection('tenant')->table('products')->insert([
            ['id' => 1, 'name' => 'Sugar 2kg', 'reorder_level' => 5],
            ['id' => 2, 'name' => 'Cooking oil 1L', 'reorder_level' => 0],
        ]);
        DB::connection('tenant')->table('inventory')->insert([
            ['store_id' => 1, 'product_id' => 1, 'quantity_on_hand' => 3],
            ['store_id' => 1, 'product_id' => 2, 'quantity_on_hand' => 50],
        ]);
        DB::connection('tenant')->table('expense_categories')->insert([['id' => 1, 'name' => 'Transport'], ['id' => 2, 'name' => 'Airtime']]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        $this->tearDownReportTables();
        parent::tearDown();
    }

    public function test_everything_that_needs_the_owner_in_plain_words(): void
    {
        DB::connection('tenant')->table('expenses')->insert([
            ['store_id' => 1, 'category_id' => 1, 'amount' => 300, 'expense_date' => '2026-10-10', 'approval_status' => 'pending', 'created_by' => 3, 'created_at' => '2026-10-10 08:00:00'],
            ['store_id' => 1, 'category_id' => 2, 'amount' => 100, 'expense_date' => '2026-10-10', 'approval_status' => 'pending', 'created_by' => 3, 'created_at' => '2026-10-10 09:00:00'],
        ]);
        DB::connection('tenant')->table('trade_partners')->insert(['id' => 1, 'name' => 'Hassan Shop']);
        DB::connection('tenant')->table('trade_lines')->insert([
            'trade_partner_id' => 1, 'direction' => 'lent', 'product_id' => 1, 'quantity' => 3, 'unit_price' => 230,
            'amount_paid' => 0, 'status' => 'open', 'opened_at' => '2026-10-01 08:00:00',
        ]);
        DB::connection('tenant')->table('shift_assignments')->insert([
            'store_id' => 1, 'user_id' => 3, 'shift_date' => '2026-10-09', 'actual_start' => '2026-10-09 05:00:00', 'actual_end' => '2026-10-09 15:00:00',
            'status' => 'completed', 'opening_cash' => 1000, 'opening_cash_source' => 'counted', 'closing_cash' => 700, 'cash_variance_reason' => 'Gave wrong change',
        ]);
        DB::connection('tenant')->table('customers')->insert(['name' => 'Asha', 'current_debt' => 1200]);

        $items = collect(app(NeedsYouService::class)->items(1))->keyBy('type');

        $this->assertSame(['low_stock', 'till_short', 'expenses_to_approve', 'shop_owes', 'customers_owe'], $items->keys()->all());
        $this->assertSame('1 item running low', $items['low_stock']['title']);
        $this->assertSame('Sugar 2kg', $items['low_stock']['subtitle']);
        $this->assertSame('Till short by KSh 300', $items['till_short']['title']);
        $this->assertStringContainsString('Gave wrong change', $items['till_short']['subtitle']);
        $this->assertSame('2 expenses to approve', $items['expenses_to_approve']['title']);
        $this->assertSame('Airtime 100 · Transport 300 · by Brian', $items['expenses_to_approve']['subtitle']);
        $this->assertSame('Hassan Shop owes KSh 690', $items['shop_owes']['title']);
        $this->assertSame('3 × Sugar 2kg not back · 9 days', $items['shop_owes']['subtitle']);
        $this->assertSame('1 customer owes KSh 1,200', $items['customers_owe']['title']);
    }

    public function test_nothing_to_show_on_a_quiet_day(): void
    {
        DB::connection('tenant')->table('inventory')->update(['quantity_on_hand' => 100]);

        $this->assertSame([], app(NeedsYouService::class)->items(1));
    }

    public function test_owed_both_ways_lists_oldest_first(): void
    {
        DB::connection('tenant')->table('customers')->insert([
            ['id' => 1, 'name' => 'Asha', 'current_debt' => 500],
            ['id' => 2, 'name' => 'Juma', 'current_debt' => 900],
        ]);
        $this->reportSale('2026-09-01 08:00:00', [['product_id' => 1, 'quantity' => 1, 'unit_price' => 900, 'unit_cost' => 0]], 'credit', extra: ['customer_id' => 2]);
        $this->reportSale('2026-10-01 08:00:00', [['product_id' => 1, 'quantity' => 1, 'unit_price' => 500, 'unit_cost' => 0]], 'credit', extra: ['customer_id' => 1]);
        DB::connection('tenant')->table('trade_partners')->insert(['id' => 1, 'name' => 'Faduma Electronics']);
        DB::connection('tenant')->table('trade_lines')->insert([
            'trade_partner_id' => 1, 'direction' => 'borrowed', 'quantity' => 2, 'unit_price' => 400, 'status' => 'open', 'opened_at' => '2026-10-08 08:00:00',
        ]);
        DB::connection('tenant')->table('suppliers')->insert(['name' => 'Bidco', 'outstanding_balance' => 4000]);

        $owed = app(NeedsYouService::class)->owedBothWays();

        $this->assertSame(['Juma', 'Asha'], array_column($owed['owed_to_you']['customers'], 'name'));
        $this->assertEquals(1400.0, $owed['owed_to_you']['total']);
        $this->assertSame('Faduma Electronics', $owed['you_owe']['shops'][0]['name']);
        $this->assertEquals(4800.0, $owed['you_owe']['total']);
    }
}
