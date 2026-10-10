<?php

namespace Tests\Feature\Tenant\Reports;

use App\Services\Tenant\Reports\StaffReportService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Tenant\Concerns\CreatesReportTables;
use Tests\Feature\Tenant\Concerns\InteractsWithTenantAuthorization;
use Tests\TestCase;

class StaffReportServiceTest extends TestCase
{
    use CreatesReportTables, InteractsWithTenantAuthorization {
        CreatesReportTables::setUpReportTables insteadof InteractsWithTenantAuthorization;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpReportTables();
        foreach (['users', 'stores'] as $table) {
            Schema::connection('tenant')->dropIfExists($table);
        }
        $this->createTenantAuthorizationSchema();
        DB::connection('tenant')->table('stores')->insert(['id' => 1, 'name' => 'CBD', 'is_active' => true, 'is_main_store' => true]);
        Carbon::setTestNow('2026-10-10 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        $this->dropTenantAuthorizationSchema();
        $this->tearDownReportTables();
        parent::tearDown();
    }

    public function test_each_persons_sales_haggling_giveaways_refunds_and_till(): void
    {
        $faith = $this->makeTenantUserWithRole('cashier');
        $owner = $this->makeTenantUserWithRole('owner');

        $this->reportSale('2026-10-10 07:00:00', [
            ['product_id' => 1, 'quantity' => 2, 'unit_price' => 260, 'list_unit_price' => 240, 'unit_cost' => 200],
            ['product_id' => 2, 'quantity' => 1, 'unit_price' => 0, 'list_unit_price' => 60, 'unit_cost' => 40, 'is_giveaway' => true],
        ], extra: ['served_by' => $faith->id]);
        $this->reportSale('2026-10-10 08:00:00', [
            ['product_id' => 1, 'quantity' => 1, 'unit_price' => 220, 'list_unit_price' => 240, 'unit_cost' => 200],
        ], extra: ['served_by' => $owner->id]);
        DB::connection('tenant')->table('sale_refunds')->insert([
            'original_sale_id' => 1, 'store_id' => 1, 'refund_date' => '2026-10-10', 'refund_amount' => 260,
            'refund_method' => 'cash', 'reason' => 'faulty', 'status' => 'completed', 'processed_by' => $owner->id, 'created_at' => '2026-10-10 09:00:00',
        ]);
        DB::connection('tenant')->table('shift_assignments')->insert([
            'store_id' => 1, 'user_id' => $faith->id, 'shift_date' => '2026-10-10', 'actual_start' => '2026-10-10 05:00:00',
            'actual_end' => '2026-10-10 15:00:00', 'status' => 'completed', 'opening_cash' => 1000, 'opening_cash_source' => 'counted', 'closing_cash' => 1400,
        ]);
        DB::connection('tenant')->table('sales')->where('served_by', $faith->id)->update(['shift_assignment_id' => 1]);

        $staff = collect(app(StaffReportService::class)->forPeriod('2026-10-10', '2026-10-10')['staff'])->keyBy('user_id');

        $f = $staff[$faith->id];
        $this->assertSame('cashier', $f['role']);
        $this->assertSame(1, $f['sales_count']);
        $this->assertEquals(520.0, $f['sales_amount']);
        $this->assertEquals(40.0, $f['haggling_uplift']);
        $this->assertSame(1, $f['giveaway_lines']);
        $this->assertEquals(60.0, $f['giveaway_value']);
        $this->assertSame(1, $f['till']['sessions']);
        $this->assertEquals(-120.0, $f['till']['over_short'], 'expected 1,520, counted 1,400');
        $this->assertSame(1, $f['till']['short_count']);

        $o = $staff[$owner->id];
        $this->assertSame(1, $o['below_floor_lines']);
        $this->assertEquals(20.0, $o['below_floor_amount']);
        $this->assertSame(1, $o['refunds_count']);
        $this->assertEquals(260.0, $o['refunds_amount']);
    }

    public function test_a_till_counted_against_an_unknown_float_has_no_over_short(): void
    {
        $faith = $this->makeTenantUserWithRole('cashier');
        DB::connection('tenant')->table('shift_assignments')->insert([
            'store_id' => 1, 'user_id' => $faith->id, 'shift_date' => '2026-10-10', 'actual_start' => '2026-10-10 05:00:00',
            'status' => 'completed', 'opening_cash_source' => 'not_counted', 'closing_cash' => 900,
        ]);

        $till = app(StaffReportService::class)->forPeriod('2026-10-10', '2026-10-10')['staff'][0]['till'];

        $this->assertSame(1, $till['sessions']);
        $this->assertSame(0, $till['counted']);
        $this->assertNull($till['history'][0]['over_short']);
    }
}
