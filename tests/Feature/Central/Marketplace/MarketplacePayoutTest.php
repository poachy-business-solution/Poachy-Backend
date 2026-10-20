<?php

namespace Tests\Feature\Central\Marketplace;

use App\Enums\Central\OrderStatus;
use App\Models\AdminAuditLog;
use App\Models\BusinessDetail;
use App\Models\MarketplaceCommissionRate;
use App\Models\MarketplaceCustomer;
use App\Models\MarketplaceFeeSetting;
use App\Models\MarketplaceOrder;
use App\Models\MarketplacePayout;
use App\Models\MarketplacePayoutLine;
use App\Models\MessageLog;
use App\Models\User;
use App\Services\Central\Payouts\PayoutLedgerService;
use App\Services\Central\Payouts\PayoutService;
use App\Services\Central\Payouts\PayoutStatementService;
use App\Services\Shared\Mpesa\Gateways\FakeMpesaGateway;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Item 37: handover → ledger line (fees, commission 0% / own-link 0%) → 24 h hold → nightly
 * B2C payout per shop (fake driver) → signed result callback; retries, timeouts, alerts.
 */
class MarketplacePayoutTest extends TestCase
{
    private string $run;

    private string $tenantId;

    private User $user;

    private MarketplaceCustomer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('tenancy.database.central_connection', 'central');
        Config::set('database.connections.central.host', env('CENTRAL_DB_HOST', '127.0.0.1'));
        Config::set('database.connections.central.database', env('CENTRAL_DB_DATABASE', 'poachy'));
        Config::set('mpesa.driver', 'fake');
        Config::set('payouts.alert_email', 'finance@poachy.test');
        DB::purge('central');
        DB::setDefaultConnection('central');
        DB::connection('central')->statement('SET foreign_key_checks = 0');
        Queue::fake();
        Bus::fake();
        Mail::fake();
        Carbon::setTestNow('2026-10-20 10:00:00');

        $this->run = strtolower(uniqid());
        $this->tenantId = "payout-{$this->run}";
        DB::connection('central')->table('tenants')->insert(['id' => $this->tenantId, 'created_at' => now(), 'updated_at' => now()]);
        BusinessDetail::on('central')->create(['tenant_id' => $this->tenantId, 'business_name' => 'Payout Duka '.$this->run, 'business_phone' => '0722'.random_int(100000, 999999),
            'business_type_id' => DB::connection('central')->table('business_types')->value('id'),
            'business_category_id' => DB::connection('central')->table('business_categories')->value('id'),
            'city' => 'Nairobi', 'county' => 'Nairobi', 'status' => 'active', 'is_verified' => true]);
        $this->user = User::on('central')->create(['name' => 'Payout Buyer', 'email' => "payout-{$this->run}@example.com", 'password' => bcrypt('x'), 'user_type' => 'customer']);
        $this->customer = MarketplaceCustomer::on('central')->create(['user_id' => $this->user->id, 'customer_number' => 'MKT-P-'.$this->run, 'phone' => '+2547'.random_int(10000000, 99999999), 'is_active' => true]);
    }

    protected function tearDown(): void
    {
        $orderIds = MarketplaceOrder::on('central')->where('tenant_id', $this->tenantId)->pluck('id');
        MarketplacePayoutLine::query()->where('tenant_id', $this->tenantId)->delete();
        MarketplacePayout::query()->where('tenant_id', $this->tenantId)->delete();
        DB::connection('central')->table('marketplace_order_payments')->whereIn('order_id', $orderIds)->delete();
        MarketplaceOrder::on('central')->whereIn('id', $orderIds)->forceDelete();
        MarketplaceCommissionRate::query()->where('notes', 'test-'.$this->run)->delete();
        MessageLog::query()->where('tenant_id', $this->tenantId)->delete();
        BusinessDetail::on('central')->where('tenant_id', $this->tenantId)->forceDelete();
        $this->customer->forceDelete();
        $this->user->forceDelete();
        DB::connection('central')->table('tenants')->where('id', $this->tenantId)->delete();
        DB::connection('central')->statement('SET foreign_key_checks = 1');
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** A paid order, handed over now (order → completed, as the shop's "collected" sync does). */
    private function handedOver(float $total, string $method = 'mpesa', ?string $ref = null): MarketplaceOrder
    {
        $order = MarketplaceOrder::on('central')->create([
            'order_number' => 'MKT-P-'.uniqid(), 'customer_id' => $this->customer->id, 'tenant_id' => $this->tenantId, 'merchant_name' => 'Payout Duka',
            'subtotal' => $total, 'tax_amount' => 0, 'discount_amount' => 0, 'delivery_fee' => 0, 'total_amount' => $total,
            'fulfillment_type' => 'pickup', 'order_status' => OrderStatus::Confirmed, 'ref' => $ref,
        ]);
        DB::connection('central')->table('marketplace_order_payments')->insert([
            'order_id' => $order->id, 'payment_method' => $method, 'amount' => $total, 'payment_status' => 'completed',
            'transaction_reference' => 'T-'.uniqid(), 'completed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $order->update(['order_status' => OrderStatus::Completed]);

        return $order;
    }

    private function payouts(): PayoutService
    {
        return app(PayoutService::class);
    }

    /** What Safaricom posts back for the latest fake B2C request. */
    private function b2cResult(int $code = 0, ?int $amount = null): array
    {
        $request = FakeMpesaGateway::find('b2c', FakeMpesaGateway::latestId('b2c'));
        $result = ['ResultType' => 0, 'ResultCode' => $code, 'ResultDesc' => $code ? 'The initiator information is invalid.' : 'OK',
            'OriginatorConversationID' => $request['originator_conversation_id'], 'ConversationID' => $request['conversation_id'], 'TransactionID' => 'SKR'.strtoupper(substr(uniqid(), -7))];
        if ($code === 0) {
            $result['ResultParameters'] = ['ResultParameter' => [
                ['Key' => 'TransactionAmount', 'Value' => $amount ?? $request['amount']],
                ['Key' => 'TransactionReceipt', 'Value' => $result['TransactionID']],
            ]];
        }

        return ['Result' => $result];
    }

    private function b2cCallback(string $kind, array $payload, ?string $token = null): TestResponse
    {
        return $this->postJson("/api/v1/central/mpesa/b2c/payout/{$kind}/".($token ?? $this->payouts()->callbackToken()), $payload);
    }

    public function test_handover_earns_a_line_with_fees_and_no_commission_then_waits_24_hours(): void
    {
        $order = $this->handedOver(1000);
        $order->update(['order_status' => OrderStatus::Completed, 'customer_notes' => 'again']); // idempotent

        $line = MarketplacePayoutLine::query()->where('order_id', $order->id)->sole();
        $this->assertSame(['1000.00', '5.50', '0.00', '0.00', '994.50', 'marketplace', 'mpesa'],
            [$line->gross, $line->processing_fee, $line->commission_rate, $line->commission_amount, $line->net, $line->source, $line->payment_method]);
        $this->assertTrue($line->eligible_at->equalTo(now()->addHours(24)));

        // Inside the hold: nothing goes out.
        $this->assertSame(0, $this->payouts()->runBatch(now()->addHours(23))['created']);
        $this->assertSame(0, MarketplacePayout::query()->where('tenant_id', $this->tenantId)->count());

        $balance = app(PayoutStatementService::class)->balance($this->tenantId, null);
        $this->assertSame([994.5, 0.0], [$balance['held'], $balance['available']]);
    }

    public function test_commission_engine_follows_the_rate_table_but_own_links_stay_free(): void
    {
        MarketplaceCommissionRate::query()->create(['rate_percent' => 10, 'effective_from' => '2026-10-01', 'notes' => 'test-'.$this->run]);

        $search = $this->handedOver(2000, 'card');
        $ownLink = $this->handedOver(2000, 'card', 'wa');

        $searchLine = MarketplacePayoutLine::query()->where('order_id', $search->id)->sole();
        $this->assertSame(['10.00', '200.00', '58.00', '1742.00'], [$searchLine->commission_rate, $searchLine->commission_amount, $searchLine->processing_fee, $searchLine->net]);
        $ownLine = MarketplacePayoutLine::query()->where('order_id', $ownLink->id)->sole();
        $this->assertSame(['0.00', '0.00', 'own_link'], [$ownLine->commission_rate, $ownLine->commission_amount, $ownLine->source]);
    }

    public function test_nightly_batch_pays_each_shop_once_in_whole_shillings_and_the_result_marks_it_paid(): void
    {
        $this->handedOver(1000);
        $this->handedOver(500);
        $asOf = now()->addHours(25);

        $summary = $this->payouts()->runBatch($asOf);
        $this->assertSame([1, 1], [$summary['created'], $summary['sent']]);

        $payout = MarketplacePayout::query()->where('tenant_id', $this->tenantId)->sole();
        // 994.50 + 497.25 = 1491.75 → KSh 1,491 now, 0.75 carried to the next payout.
        $this->assertSame(['1491.00', 'processing', 1], [$payout->amount, $payout->status, $payout->attempts]);
        $request = FakeMpesaGateway::find('b2c', $payout->conversation_id);
        $this->assertSame([1491, $payout->reference], [$request['amount'], $request['occasion']]);
        $this->assertEqualsWithDelta(1491.0, (float) $payout->lines()->sum('net'), 0.001);
        $this->assertEqualsWithDelta(0.75, (float) MarketplacePayoutLine::query()->where('tenant_id', $this->tenantId)->available($asOf)->sum('net'), 0.001);

        // A second run the same night finds nothing new to send (0.75 is below the minimum).
        $this->assertSame(0, $this->payouts()->runBatch($asOf)['created']);

        // A wrong secret is a 404 and changes nothing; the right one settles the payout.
        $this->b2cCallback('result', $this->b2cResult(), 'nope')->assertNotFound();
        $this->assertSame('processing', $payout->fresh()->status);
        $this->b2cCallback('result', $this->b2cResult())->assertOk()->assertJsonPath('ResultCode', 0);
        $payout->refresh();
        $this->assertSame('paid', $payout->status);
        $this->assertNotEmpty($payout->mpesa_receipt);
        $this->assertTrue(MessageLog::query()->where('tenant_id', $this->tenantId)->where('template', 'payout_paid')->exists());

        $earnings = app(PayoutStatementService::class)->earnings($this->tenantId);
        $this->assertSame([1500.0, 8.25, 1491.75, 1491.0], [$earnings['gross_30d'], $earnings['fees_30d'], $earnings['net_30d'], $earnings['paid_out_30d']]);
    }

    public function test_failed_payouts_retry_on_a_back_off_then_alert_finance_and_the_shop(): void
    {
        $this->handedOver(1000);
        $this->payouts()->runBatch(now()->addHours(25));
        $payout = MarketplacePayout::query()->where('tenant_id', $this->tenantId)->sole();

        foreach ([60, 360, 1440] as $delay) {
            $this->b2cCallback('result', $this->b2cResult(2001))->assertOk();
            $payout->refresh();
            $this->assertSame('failed', $payout->status);
            Carbon::setTestNow($payout->next_attempt_at);
            $this->assertEqualsWithDelta($delay, $payout->updated_at->diffInMinutes($payout->next_attempt_at), 1);
            $this->assertSame(1, $this->payouts()->runBatch(now(), retriesOnly: true)['retried']);
            $this->assertSame('processing', $payout->fresh()->status);
        }

        $this->b2cCallback('result', $this->b2cResult(2001))->assertOk();
        $payout->refresh();
        $this->assertSame(['failed_final', 4], [$payout->status, $payout->attempts]);
        $this->assertSame(0, $this->payouts()->runBatch(now()->addDays(2), retriesOnly: true)['retried']);
        $this->assertTrue(MessageLog::query()->where('tenant_id', $this->tenantId)->where('template', 'payout_failed')->exists());
    }

    public function test_a_timeout_or_a_wrong_amount_waits_for_a_person_and_is_never_resent(): void
    {
        $this->handedOver(1000);
        $this->payouts()->runBatch(now()->addHours(25));
        $payout = MarketplacePayout::query()->where('tenant_id', $this->tenantId)->sole();
        $this->b2cCallback('timeout', $this->b2cResult())->assertOk();
        $this->assertSame('needs_check', $payout->fresh()->status);
        $this->assertSame(0, $this->payouts()->runBatch(now()->addDays(3), retriesOnly: true)['retried']);
        // A late "success" for a payout no longer processing is ignored.
        $this->b2cCallback('result', $this->b2cResult())->assertOk();
        $this->assertSame('needs_check', $payout->fresh()->status);

        $other = $this->handedOver(800);
        $other->refresh();
        MarketplacePayoutLine::query()->where('order_id', $other->id)->update(['eligible_at' => now()->subMinute()]);
        $this->payouts()->runBatch(now());
        $second = MarketplacePayout::query()->where('tenant_id', $this->tenantId)->latest('id')->first();
        $this->b2cCallback('result', $this->b2cResult(0, 5))->assertOk();
        $this->assertSame('needs_check', $second->fresh()->status);
    }

    public function test_no_payout_number_means_no_transfer_and_a_message_to_the_shop(): void
    {
        BusinessDetail::on('central')->where('tenant_id', $this->tenantId)->update(['business_phone' => '', 'payout_phone' => null]);
        $this->handedOver(1000);

        $summary = $this->payouts()->runBatch(now()->addHours(25));

        $this->assertSame('no payout number', $summary['skipped'][$this->tenantId]);
        $this->assertSame(0, MarketplacePayout::query()->where('tenant_id', $this->tenantId)->count());
    }

    public function test_a_clawback_is_taken_from_the_next_payout_and_the_payout_number_wins(): void
    {
        BusinessDetail::on('central')->where('tenant_id', $this->tenantId)->update(['payout_phone' => '0711000222']);
        $order = $this->handedOver(1000);
        app(PayoutLedgerService::class)->recordClawback($order, 300, 'returned');

        $this->payouts()->runBatch(now()->addHours(25));
        $payout = MarketplacePayout::query()->where('tenant_id', $this->tenantId)->sole();

        $this->assertSame(['694.00', '254711000222'], [$payout->amount, $payout->phone]);
        $this->assertSame(300.0, app(PayoutStatementService::class)->earnings($this->tenantId)['clawbacks_30d']);
    }

    public function test_refunds_taken_back_beyond_what_is_due_show_as_owed_never_a_negative_payout(): void
    {
        $order = $this->handedOver(1000); // 994.50, still held for 24 h
        app(PayoutLedgerService::class)->recordClawback($order, 1500, 'returned');

        $balance = app(PayoutStatementService::class)->balance($this->tenantId, null);
        $this->assertSame([994.5, 0.0, 1500.0], [$balance['held'], $balance['available'], $balance['owed']]);
        $this->assertSame(1500.0, app(PayoutStatementService::class)->earnings($this->tenantId)['owed']);
        $this->assertSame(0, $this->payouts()->runBatch(now())['created']);
    }

    public function test_fees_and_commission_set_in_the_admin_apply_from_their_date_and_are_audited(): void
    {
        Config::set('internal_admin.token', 'internal-test-token-0123456789abcdef0123456789');
        Config::set('internal_admin.allowed_ips', ['127.0.0.1']);
        $as = fn (string $role) => ['Authorization' => 'Bearer internal-test-token-0123456789abcdef0123456789', 'X-Admin-Id' => '7', 'X-Admin-Name' => 'Finance', 'X-Admin-Role' => $role, 'Accept' => 'application/json'];
        $fees = ['mpesa_fee_percent' => 1, 'card_fee_percent' => 3, 'transfer_fee' => 15, 'hold_hours' => 12, 'min_payout' => 50, 'effective_from' => '2026-10-21', 'reason' => 'Real Daraja tariff'];

        try {
            $this->postJson('/api/internal/admin/marketplace/fees', $fees, $as('operations'))->assertForbidden();
            $this->postJson('/api/internal/admin/marketplace/fees', ['effective_from' => '2026-10-19'] + $fees, $as('finance'))->assertUnprocessable()->assertJsonValidationErrors('effective_from');
            $this->postJson('/api/internal/admin/marketplace/fees', $fees, $as('finance'))->assertCreated();
            $this->postJson('/api/internal/admin/marketplace/commission-rates', ['rate_percent' => 5, 'effective_from' => '2026-10-21', 'reason' => 'Traffic is up'], $as('finance'))->assertCreated();
            $this->assertSame(['marketplace.commission.schedule', 'marketplace.fees.schedule'], AdminAuditLog::on('central')->where('admin_id', 7)->latest('id')->limit(2)->pluck('action')->all());

            // Today the launch fees (0.55%) and 0% commission still apply.
            $today = $this->handedOver(1000);
            $todayLine = MarketplacePayoutLine::query()->where('order_id', $today->id)->sole();
            $this->assertSame(['5.50', '0.00'], [$todayLine->processing_fee, $todayLine->commission_amount]);

            // From the 21st: 1% fee, 12 h hold, 5% commission (not on own links), 15 per transfer, KSh 50 minimum.
            Carbon::setTestNow('2026-10-21 09:00:00');
            $tomorrow = $this->handedOver(2000);
            $line = MarketplacePayoutLine::query()->where('order_id', $tomorrow->id)->sole();
            $this->assertSame(['20.00', '100.00', '1880.00'], [$line->processing_fee, $line->commission_amount, $line->net]);
            $this->assertTrue($line->eligible_at->equalTo(now()->addHours(12)));
            $ownLink = $this->handedOver(1000, 'mpesa', 'wa');
            $this->assertSame('0.00', MarketplacePayoutLine::query()->where('order_id', $ownLink->id)->sole()->commission_amount);

            $this->getJson('/api/internal/admin/marketplace/fees', $as('support'))->assertOk()
                ->assertJsonPath('data.current.fees.mpesa_fee_percent', 1)->assertJsonPath('data.current.commission_percent', 5)
                ->assertJsonPath('data.current.fees.transfer_fee', 15);

            $this->payouts()->runBatch(now()->addHours(25));
            $payout = MarketplacePayout::query()->where('tenant_id', $this->tenantId)->sole();
            $this->assertSame((int) floor(994.5 + 1880 + 990 - 15), (int) $payout->amount);
        } finally {
            MarketplaceFeeSetting::query()->where('effective_from', '2026-10-21')->delete();
            MarketplaceCommissionRate::query()->where('effective_from', '2026-10-21')->delete();
            AdminAuditLog::on('central')->where('admin_id', 7)->delete();
        }
    }
}
