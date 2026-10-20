<?php

namespace Tests\Feature\Central\Marketplace;

use App\Enums\Central\OrderStatus;
use App\Models\BillingAccount;
use App\Models\BusinessDetail;
use App\Models\MarketplaceCustomer;
use App\Models\MarketplaceOrder;
use App\Models\MarketplacePayout;
use App\Models\MarketplacePayoutLine;
use App\Models\MarketplaceProduct;
use App\Models\MarketplaceRefund;
use App\Models\MarketplaceReturnCase;
use App\Models\MessageLog;
use App\Models\User;
use App\Services\Central\Payouts\PayoutService;
use App\Services\Central\Refunds\RefundService;
use App\Services\Central\Refunds\ReturnCaseService;
use App\Services\Central\Refunds\ReturnStatsService;
use App\Services\Shared\Card\Gateways\FakeCardGateway;
use App\Services\Shared\Mpesa\Gateways\FakeMpesaGateway;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Item 38: refunds through the gateway that took the money (cancellation before handover), and
 * returns after handover with the 48 h inspection / dispute clocks, admin decisions, clawbacks
 * from payouts (else the Poachy balance) and refund/dispute rates.
 */
class ReturnsAndRefundsTest extends TestCase
{
    private string $run;

    private string $tenantId;

    private User $user;

    private MarketplaceCustomer $customer;

    private MarketplaceProduct $shoes;

    private MarketplaceProduct $juice;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('tenancy.database.central_connection', 'central');
        Config::set('database.connections.central.host', env('CENTRAL_DB_HOST', '127.0.0.1'));
        Config::set('database.connections.central.database', env('CENTRAL_DB_DATABASE', 'poachy'));
        Config::set('mpesa.driver', 'fake');
        Config::set('paystack.driver', 'fake');
        Config::set('paystack.secret_key', 'sk_test_fake');
        DB::purge('central');
        DB::setDefaultConnection('central');
        DB::connection('central')->statement('SET foreign_key_checks = 0');
        Queue::fake();
        Bus::fake();
        Mail::fake();
        Storage::fake('returns');
        Carbon::setTestNow('2026-10-20 10:00:00');

        $this->run = strtolower(uniqid());
        $this->tenantId = "returns-{$this->run}";
        DB::connection('central')->table('tenants')->insert(['id' => $this->tenantId, 'created_at' => now(), 'updated_at' => now()]);
        BusinessDetail::on('central')->create(['tenant_id' => $this->tenantId, 'business_name' => 'Return Duka '.$this->run, 'business_phone' => '0722'.random_int(100000, 999999),
            'business_type_id' => DB::connection('central')->table('business_types')->value('id'),
            'business_category_id' => DB::connection('central')->table('business_categories')->value('id'),
            'city' => 'Nairobi', 'county' => 'Nairobi', 'status' => 'active', 'is_verified' => true, 'return_window_days' => 7]);
        $this->user = User::on('central')->create(['name' => 'Return Buyer', 'email' => "returns-{$this->run}@example.com", 'password' => bcrypt('x'), 'user_type' => 'customer']);
        $this->customer = MarketplaceCustomer::on('central')->create(['user_id' => $this->user->id, 'customer_number' => 'MKT-R-'.$this->run, 'phone' => '+2547'.random_int(10000000, 99999999), 'is_active' => true]);
        $product = fn (string $name, string $category) => MarketplaceProduct::on('central')->create([
            'tenant_id' => $this->tenantId, 'tenant_product_id' => random_int(1000, 9999), 'name' => $name, 'slug' => str()->slug($name).'-'.$this->run, 'sku' => 'S-'.uniqid(),
            'online_price' => 1000, 'base_uom_code' => 'pcs', 'base_uom_name' => 'Piece', 'tax_rate' => 0, 'available_quantity' => 5, 'stock_status' => 'in_stock', 'is_active' => true,
            'tenant_category_name' => $category,
        ]);
        $this->shoes = $product('Block heels', 'Shoes');
        $this->juice = $product('Mango Juice 1L', 'Beverages');
    }

    protected function tearDown(): void
    {
        $orderIds = MarketplaceOrder::on('central')->where('tenant_id', $this->tenantId)->pluck('id');
        MarketplaceRefund::query()->where('tenant_id', $this->tenantId)->delete();
        MarketplaceReturnCase::query()->where('tenant_id', $this->tenantId)->delete();
        MarketplacePayoutLine::query()->where('tenant_id', $this->tenantId)->delete();
        MarketplacePayout::query()->where('tenant_id', $this->tenantId)->delete();
        foreach (['marketplace_order_payments', 'marketplace_order_items'] as $table) {
            DB::connection('central')->table($table)->whereIn('order_id', $orderIds)->delete();
        }
        MarketplaceOrder::on('central')->whereIn('id', $orderIds)->forceDelete();
        MarketplaceProduct::on('central')->where('tenant_id', $this->tenantId)->forceDelete();
        MessageLog::query()->where('tenant_id', $this->tenantId)->delete();
        DB::connection('central')->table('billing_ledger')->where('tenant_id', $this->tenantId)->delete();
        BillingAccount::query()->where('tenant_id', $this->tenantId)->delete();
        BusinessDetail::on('central')->where('tenant_id', $this->tenantId)->forceDelete();
        $this->customer->forceDelete();
        $this->user->forceDelete();
        DB::connection('central')->table('tenants')->where('id', $this->tenantId)->delete();
        DB::connection('central')->statement('SET foreign_key_checks = 1');
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** A paid order for 2 × the product (KSh 1,000 each) + KSh 200 delivery; optionally handed over. */
    private function order(string $method = 'mpesa', bool $handedOver = true, ?MarketplaceProduct $product = null): MarketplaceOrder
    {
        $product ??= $this->shoes;
        $order = MarketplaceOrder::on('central')->create([
            'order_number' => 'MKT-R-'.strtoupper(uniqid()), 'customer_id' => $this->customer->id, 'tenant_id' => $this->tenantId, 'merchant_name' => 'Return Duka',
            'subtotal' => 2000, 'tax_amount' => 0, 'discount_amount' => 0, 'delivery_fee' => 200, 'total_amount' => 2200,
            'fulfillment_type' => 'delivery', 'order_status' => OrderStatus::Confirmed,
        ]);
        DB::connection('central')->table('marketplace_order_items')->insert([
            'order_id' => $order->id, 'marketplace_product_id' => $product->id, 'tenant_product_id' => $product->tenant_product_id, 'product_name' => $product->name, 'product_sku' => $product->sku, 'uom_code' => 'pcs', 'uom_name' => 'Piece',
            'quantity' => 2, 'quantity_in_base_uom' => 2, 'unit_price' => 1000, 'tax_rate' => 0, 'tax_amount' => 0, 'discount_amount' => 0, 'subtotal' => 2000,
            'fulfillment_status' => 'pending', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $reference = 'PCO-'.uniqid();
        if ($method === 'card') {
            FakeCardGateway::put($reference, ['reference' => $reference, 'amount' => 2200, 'status' => 'success', 'refunds' => [], 'email' => 'b@example.com']);
        }
        DB::connection('central')->table('marketplace_order_payments')->insert([
            'order_id' => $order->id, 'payment_method' => $method, 'amount' => 2200, 'payment_status' => 'completed',
            'transaction_reference' => $reference, 'provider_reference' => $method === 'mpesa' ? 'SKR'.strtoupper(substr(uniqid(), -7)) : $reference,
            'completed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        if ($handedOver) {
            $order->update(['order_status' => OrderStatus::Completed]);
        }

        return $order->fresh();
    }

    private function returns(): ReturnCaseService
    {
        return app(ReturnCaseService::class);
    }

    private function itemsOf(MarketplaceOrder $order, float $quantity = 2): array
    {
        return [['order_item_id' => $order->items()->value('id'), 'quantity' => $quantity]];
    }

    /** Safaricom's answer to the latest fake reversal or B2C. */
    private function mpesaAnswer(string $kind, int $code = 0): void
    {
        $request = FakeMpesaGateway::find($kind, FakeMpesaGateway::latestId($kind));
        $this->postJson('/api/v1/central/mpesa/refund/result/'.app(RefundService::class)->callbackToken(), ['Result' => [
            'ResultCode' => $code, 'ResultDesc' => $code ? 'Declined' : 'OK', 'ConversationID' => $request['conversation_id'], 'TransactionID' => 'RFD'.strtoupper(substr(uniqid(), -7)),
        ]])->assertOk();
    }

    public function test_cancelling_a_paid_order_before_handover_reverses_the_mpesa_payment(): void
    {
        $order = $this->order(handedOver: false);
        $order->update(['order_status' => OrderStatus::Cancelled, 'cancellation_reason' => 'Changed my mind']);

        $refund = MarketplaceRefund::query()->where('order_id', $order->id)->sole();
        $this->assertSame(['cancellation', 'mpesa_reversal', 'processing', '2200.00'], [$refund->kind, $refund->method, $refund->status, $refund->amount]);
        $this->postJson('/api/v1/central/mpesa/refund/result/wrong', [])->assertNotFound();

        $this->mpesaAnswer('reversal');
        $this->assertSame('completed', $refund->fresh()->status);
        $this->assertTrue((bool) $order->payments()->first()->is_refunded);
        $this->assertSame(0, MarketplacePayoutLine::query()->where('order_id', $order->id)->count()); // never owed to the shop
        $this->assertTrue(MessageLog::query()->where('template', 'refund_completed')->where('tenant_id', $this->tenantId)->exists());
    }

    public function test_cancelling_a_card_order_refunds_the_card_at_once(): void
    {
        $order = $this->order('card', handedOver: false);
        $order->update(['order_status' => OrderStatus::Cancelled]);

        $refund = MarketplaceRefund::query()->where('order_id', $order->id)->sole();
        $this->assertSame(['card', 'completed'], [$refund->method, $refund->status]);
        $this->assertCount(1, FakeCardGateway::find($order->payments()->value('transaction_reference'))['refunds']);
    }

    public function test_a_faulty_item_returned_and_accepted_is_refunded_in_full_and_clawed_back_from_the_payout(): void
    {
        $order = $this->order();
        $photo = UploadedFile::fake()->image('broken.jpg');
        $case = $this->returns()->open($order, $this->itemsOf($order), 'faulty', 'The heel broke', [$photo]);

        // Shop at fault, everything back: items + delivery fee.
        $this->assertSame(['requested', '2200.00', true], [$case->status, $case->requested_amount, $case->shop_at_fault]);
        Storage::disk('returns')->assertExists($case->buyer_evidence[0]);
        $this->assertTrue(MessageLog::query()->where('template', 'return_requested')->where('tenant_id', $this->tenantId)->exists());

        $case = $this->returns()->received($case);
        $this->assertTrue($case->inspection_due_at->equalTo(now()->addHours(48)));
        $case = $this->returns()->inspect($case, 'accept', null, null);
        $this->assertSame('refunding', $case->status);

        $this->mpesaAnswer('reversal');
        $this->assertSame('refunded', $case->fresh()->status);
        $clawback = MarketplacePayoutLine::query()->where('order_id', $order->id)->where('type', 'clawback')->sole();
        $this->assertSame('-2200.00', $clawback->net);

        // The next payout nets it off: the shop was owed 2200 − 12.10 fee, and owes 2200 back.
        $this->assertEqualsWithDelta(-12.10, (float) MarketplacePayoutLine::query()->where('tenant_id', $this->tenantId)->available(now()->addDay())->sum('net'), 0.001);
    }

    public function test_changed_mind_costs_the_buyer_the_delivery_and_their_share_of_the_fee_and_partial_refunds_go_by_b2c(): void
    {
        $order = $this->order();
        $case = $this->returns()->open($order, $this->itemsOf($order, 1), 'changed_mind', null);

        // One of two pairs (1,000) less its share of the 12.10 fee (1000/2200) = 994.50; no delivery fee.
        $this->assertSame(['994.50', false], [$case->requested_amount, $case->shop_at_fault]);
        $this->returns()->inspect($this->returns()->received($case), 'accept', null, null);

        $refund = MarketplaceRefund::query()->where('return_case_id', $case->id)->sole();
        $this->assertSame(['mpesa_b2c', 'processing'], [$refund->method, $refund->status]);
        $this->assertSame(994, FakeMpesaGateway::find('b2c', $refund->conversation_id)['amount']);
        $this->mpesaAnswer('b2c');
        $this->assertSame('refunded', $case->fresh()->status);
    }

    public function test_rejection_dispute_and_poachy_decision(): void
    {
        $order = $this->order('card');
        $case = $this->returns()->received($this->returns()->open($order, $this->itemsOf($order), 'not_as_described', 'Wrong colour'));

        try {
            $this->returns()->inspect($case, 'reject', null, null);
            $this->fail('A rejection needs a reason');
        } catch (ValidationException) {
        }
        $case = $this->returns()->inspect($case, 'reject', null, 'Worn outside — sole is dirty', [UploadedFile::fake()->image('sole.jpg')]);
        $this->assertSame(['rejected', '0.00'], [$case->status, $case->refund_amount]);
        $this->assertTrue($case->dispute_until->equalTo(now()->addHours(48)));

        $case = $this->returns()->dispute($case, 'It was only tried on at home');
        $this->assertSame('disputed', $case->status);

        $case = $this->returns()->decide($case, 'refund_partial', 1000, 'Half: tried outside briefly', 'admin:7');
        $this->assertSame(['refunded', '1000.00', 'admin:7'], [$case->status, $case->refund_amount, $case->decided_by]); // card: immediate
        $this->assertSame(['dispute', 'card'], [$case->refunds()->first()->kind, $case->refunds()->first()->method]);
    }

    public function test_the_clocks_escalate_settle_and_expire(): void
    {
        $silent = $this->returns()->received($this->returns()->open($order = $this->order('card'), $this->itemsOf($order), 'damaged', null));
        $partial = $this->returns()->inspect($this->returns()->received($this->returns()->open($order2 = $this->order('card'), $this->itemsOf($order2), 'damaged', null)), 'partial', 500, 'One shoe fine');
        $neverBrought = $this->returns()->open($order3 = $this->order('card'), $this->itemsOf($order3), 'changed_mind', null);

        Carbon::setTestNow(now()->addHours(49));
        $this->assertSame(['escalated' => 1, 'settled' => 1, 'expired' => 0], $this->returns()->runTimers());
        $this->assertSame('escalated', $silent->fresh()->status);
        $this->assertSame(['refunded', '500.00'], [$partial->fresh()->status, $partial->fresh()->refunds()->first()->amount]);

        Carbon::setTestNow(now()->addDays(6));
        $this->assertSame(1, $this->returns()->runTimers()['expired']);
        $this->assertSame('expired', $neverBrought->fresh()->status);

        // Escalated: Poachy decides without the shop.
        $this->assertSame('refunded', $this->returns()->decide($silent->fresh(), 'refund_full', null, 'Shop did not inspect', 'admin:1')->status);
    }

    public function test_what_cannot_be_returned(): void
    {
        $cases = [
            'not handed over yet' => fn () => $this->order(handedOver: false),
            'a beverage' => fn () => $this->order(product: $this->juice),
        ];
        foreach ($cases as $why => $make) {
            $order = $make();
            try {
                $this->returns()->open($order, $this->itemsOf($order), 'faulty', null);
                $this->fail("Should refuse: {$why}");
            } catch (ValidationException $e) {
                $this->assertNotEmpty($e->errors(), $why);
            }
        }

        $order = $this->order();
        $this->returns()->open($order, $this->itemsOf($order, 1), 'faulty', null);
        $this->expectException(ValidationException::class); // one open return per order
        $this->returns()->open($order, $this->itemsOf($order, 1), 'faulty', null);
    }

    public function test_the_shops_window_closes_and_no_window_means_no_returns(): void
    {
        $order = $this->order();
        Carbon::setTestNow(now()->addDays(8));
        try {
            $this->returns()->open($order, $this->itemsOf($order), 'faulty', null);
            $this->fail('Past the 7-day window');
        } catch (ValidationException) {
        }

        BusinessDetail::on('central')->where('tenant_id', $this->tenantId)->update(['return_window_days' => 0]);
        $fresh = $this->order();
        $this->expectException(ValidationException::class);
        $this->returns()->open($fresh, $this->itemsOf($fresh), 'faulty', null);
    }

    public function test_buyer_endpoints_are_the_buyers_own(): void
    {
        $order = $this->order();
        Sanctum::actingAs($this->user, ['*'], 'central');
        $reference = $this->post("/api/v1/central/marketplace/orders/{$order->id}/returns", [
            'items' => $this->itemsOf($order, 1), 'reason_code' => 'wrong_item', 'description' => 'Sent size 38', 'photos' => [UploadedFile::fake()->image('a.jpg')],
        ], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('data.status', 'requested')->assertJsonPath('data.buyer_photos', 1)->json('data.reference');
        $this->getJson('/api/v1/central/marketplace/returns')->assertOk()->assertJsonPath('data.0.reference', $reference);
        $this->postJson('/api/v1/central/marketplace/orders/'.$order->id.'/returns', ['items' => [], 'reason_code' => 'bored'])->assertUnprocessable()->assertJsonValidationErrors(['items', 'reason_code']);

        $other = User::on('central')->create(['name' => 'Other', 'email' => "other-r-{$this->run}@example.com", 'password' => bcrypt('x'), 'user_type' => 'customer']);
        $otherCustomer = MarketplaceCustomer::on('central')->create(['user_id' => $other->id, 'customer_number' => 'MKT-O-'.$this->run, 'phone' => '+254711'.random_int(100000, 999999), 'is_active' => true]);
        try {
            Sanctum::actingAs($other, ['*'], 'central');
            $this->getJson("/api/v1/central/marketplace/returns/{$reference}")->assertNotFound();
            $this->postJson("/api/v1/central/marketplace/returns/{$reference}/cancel")->assertNotFound();
            $this->postJson("/api/v1/central/marketplace/orders/{$order->id}/returns", ['items' => $this->itemsOf($order, 1), 'reason_code' => 'faulty'])->assertNotFound();
        } finally {
            $otherCustomer->forceDelete();
            $other->forceDelete();
        }

        Sanctum::actingAs($this->user, ['*'], 'central');
        $this->postJson("/api/v1/central/marketplace/returns/{$reference}/cancel")->assertOk()->assertJsonPath('data.status', 'cancelled');
    }

    public function test_clawbacks_payouts_cannot_cover_come_off_the_poachy_balance_after_a_week(): void
    {
        BillingAccount::query()->create(['tenant_id' => $this->tenantId, 'balance' => 1000, 'status' => 'active']);
        $order = $this->order('card');
        MarketplacePayoutLine::query()->where('order_id', $order->id)->update(['payout_id' => null, 'net' => 0, 'gross' => 0]); // already paid out elsewhere
        app(\App\Services\Central\Payouts\PayoutLedgerService::class)->recordClawback($order, 300, 'refund');

        $this->assertSame(0, app(PayoutService::class)->runBatch(now()->addDay())['created']);
        $this->assertSame('1000.00', BillingAccount::query()->where('tenant_id', $this->tenantId)->value('balance'));

        $summary = app(PayoutService::class)->runBatch(now()->addDays(8));
        $this->assertSame('refunds taken from the Poachy balance', $summary['skipped'][$this->tenantId]);
        $this->assertSame('700.00', BillingAccount::query()->where('tenant_id', $this->tenantId)->value('balance'));
        $this->assertSame(0.0, (float) MarketplacePayoutLine::query()->where('tenant_id', $this->tenantId)->available(now()->addDays(9))->sum('net'));
    }

    public function test_refund_and_dispute_rates_per_shop_and_buyer(): void
    {
        $refunded = $this->order('card');
        $this->returns()->inspect($this->returns()->received($this->returns()->open($refunded, $this->itemsOf($refunded), 'faulty', null)), 'accept', null, null);
        $disputed = $this->order('card');
        $this->returns()->dispute($this->returns()->inspect($this->returns()->received($this->returns()->open($disputed, $this->itemsOf($disputed), 'faulty', null)), 'reject', null, 'Fine'), 'Not fine');
        $this->order('card');
        $this->order('card');

        $shop = app(ReturnStatsService::class)->forShop($this->tenantId);
        $this->assertSame(['orders' => 4, 'returns' => 2, 'refunded_orders' => 1, 'disputes' => 1, 'refund_rate' => 0.25, 'dispute_rate' => 0.25], $shop);
        $this->assertSame(0.25, app(ReturnStatsService::class)->forBuyer($this->customer->id)['refund_rate']);
    }
}
