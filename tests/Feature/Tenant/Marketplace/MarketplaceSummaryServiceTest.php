<?php

namespace Tests\Feature\Tenant\Marketplace;

use App\Models\MarketplaceReturnCase;
use App\Services\Tenant\Marketplace\MarketplaceSummaryService;
use App\Services\Tenant\Reports\NeedsYouService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Tenant\Concerns\CreatesReportTables;
use Tests\TestCase;

/**
 * Item 36: the app's Marketplace tab — online orders by step, paid online sales, earnings,
 * and the Needs-you alerts (late handovers, new orders).
 */
class MarketplaceSummaryServiceTest extends TestCase
{
    use CreatesReportTables;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpReportTables();
        Carbon::setTestNow('2026-10-10 09:00:00'); // 12:00 in Nairobi
        DB::connection('tenant')->table('inventory')->truncate(); // nothing low on stock
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        $this->tearDownReportTables();
        parent::tearDown();
    }

    private function online(string $number, string $status, string $paidAt, float $total, string $type = 'pickup', int $store = 1, string $payment = 'paid'): void
    {
        DB::connection('tenant')->table('marketplace_sales')->insert([
            'sale_number' => $number, 'store_id' => $store, 'sale_date' => $paidAt, 'total_amount' => $total, 'amount_paid' => $total,
            'payment_status' => $payment, 'fulfillment_type' => $type, 'fulfillment_status' => $status, 'created_at' => $paidAt, 'updated_at' => $paidAt,
        ]);
    }

    public function test_orders_sales_and_alerts_in_plain_words(): void
    {
        $this->online('MKT-1', 'pending', '2026-10-10 07:00:00', 500);                 // today, new
        $this->online('MKT-2', 'confirmed', '2026-10-10 08:30:00', 300, 'delivery');   // today, new, to deliver
        $this->online('MKT-3', 'preparing', '2026-10-08 08:00:00', 1000);              // 2 days ago → overdue
        $this->online('MKT-4', 'ready', '2026-10-09 10:00:00', 200);                   // 23 h ago, not overdue yet
        $this->online('MKT-5', 'delivered', '2026-10-05 10:00:00', 700);
        $this->online('MKT-6', 'cancelled', '2026-10-10 06:00:00', 900);               // never counts
        $this->online('MKT-7', 'pending', '2026-10-10 07:30:00', 400, 'pickup', 2);   // other branch

        $summary = app(MarketplaceSummaryService::class)->summary(1);

        $this->assertSame(['to_prepare' => 2, 'preparing' => 1, 'ready' => 1, 'overdue' => 1, 'delivered_7d' => 1], $summary['orders']);
        $this->assertSame(['count' => 2, 'amount' => 800.0], $summary['sales']['today']);
        $this->assertSame(['count' => 5, 'amount' => 2700.0], $summary['sales']['last_7_days']);
        // Earnings come from the payout ledger (handovers), not from sales: none handed over here.
        $this->assertSame(0.0, $summary['earnings']['gross_30d']);
        $this->assertTrue($summary['earnings']['payouts_enabled']);

        $alerts = collect($summary['needs_you'])->keyBy('type');
        $this->assertSame('1 online order not handed over', $alerts['online_overdue']['title']);
        $this->assertStringContainsString('MKT-3', $alerts['online_overdue']['subtitle']);
        $this->assertSame('2 new online orders to prepare', $alerts['online_orders']['title']);
        $this->assertSame('Paid · KSh 800 · 1 to deliver', $alerts['online_orders']['subtitle']);

        // All branches together.
        $this->assertSame(3, app(MarketplaceSummaryService::class)->summary()['orders']['to_prepare']);
    }

    public function test_home_needs_you_puts_online_orders_first(): void
    {
        $this->online('MKT-1', 'pending', '2026-10-10 07:00:00', 500);
        DB::connection('tenant')->table('customers')->insert(['name' => 'Asha', 'current_debt' => 1200]);

        $types = collect(app(NeedsYouService::class)->items(1))->pluck('type')->all();

        $this->assertSame(['online_orders', 'customers_owe'], $types);
    }

    public function test_returns_to_check_are_an_alert(): void
    {
        $make = fn (string $ref, string $status) => MarketplaceReturnCase::query()->create([
            'reference' => $ref, 'order_id' => 1, 'tenant_id' => 'test-tenant', 'customer_id' => 1, 'items' => [], 'reason_code' => 'faulty',
            'shop_at_fault' => true, 'requested_amount' => 100, 'status' => $status,
        ]);
        $a = $make('RET-'.strtoupper(substr(uniqid(), -6)), 'received');
        $b = $make('RET-'.strtoupper(substr(uniqid(), -6)), 'requested');

        try {
            $alert = collect(app(MarketplaceSummaryService::class)->needsYou(1))->firstWhere('type', 'returns_to_check');
            $this->assertSame('1 return to check within 48 hours', $alert['title']);
            $this->assertStringEndsWith('· 1 coming back', $alert['subtitle']);
        } finally {
            $a->delete();
            $b->delete();
        }
    }

    public function test_a_quiet_marketplace_has_no_alerts(): void
    {
        $this->online('MKT-5', 'delivered', '2026-10-05 10:00:00', 700);

        $this->assertSame([], app(MarketplaceSummaryService::class)->needsYou(1));
    }
}
