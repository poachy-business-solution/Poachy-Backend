<?php

namespace Tests\Feature\Internal;

use App\Enums\Central\OrderStatus;
use App\Models\AdminAuditLog;
use App\Models\BusinessDetail;
use App\Models\MarketplaceCustomer;
use App\Models\MarketplaceOrder;
use App\Models\MarketplacePayout;
use App\Models\MarketplacePayoutLine;
use App\Models\MarketplaceProduct;
use App\Models\MarketplaceRefund;
use App\Models\MarketplaceReturnCase;
use App\Models\MessageLog;
use App\Models\ProductReview;
use App\Models\User;
use App\Services\Central\Payouts\PayoutService;
use App\Services\Central\Refunds\ReturnCaseService;
use App\Services\Shared\Card\Gateways\FakeCardGateway;
use App\Services\Shared\Mpesa\Gateways\FakeMpesaGateway;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Item 41: Poachy staff act on marketplace money and trust through the private admin API —
 * right role only, every change audited.
 */
class InternalMarketplaceAdminTest extends TestCase
{
    private string $run;

    private string $tenantId;

    private User $user;

    private MarketplaceCustomer $customer;

    private MarketplaceProduct $product;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('tenancy.database.central_connection', 'central');
        Config::set('database.connections.central.host', env('CENTRAL_DB_HOST', '127.0.0.1'));
        Config::set('database.connections.central.database', env('CENTRAL_DB_DATABASE', 'poachy'));
        Config::set('internal_admin.token', 'internal-test-token-0123456789abcdef0123456789');
        Config::set('internal_admin.allowed_ips', ['127.0.0.1', '10.0.0.0/8']);
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

        $this->run = strtolower(uniqid());
        $this->tenantId = "admin-mk-{$this->run}";
        DB::connection('central')->table('tenants')->insert(['id' => $this->tenantId, 'created_at' => now(), 'updated_at' => now()]);
        BusinessDetail::on('central')->create(['tenant_id' => $this->tenantId, 'business_name' => 'Admin Duka '.$this->run, 'business_phone' => '0722'.random_int(100000, 999999),
            'business_type_id' => DB::connection('central')->table('business_types')->value('id'),
            'business_category_id' => DB::connection('central')->table('business_categories')->value('id'),
            'city' => 'Nairobi', 'county' => 'Nairobi', 'status' => 'active', 'is_verified' => true, 'return_window_days' => 7]);
        $this->user = User::on('central')->create(['name' => 'Admin Buyer', 'email' => "admin-mk-{$this->run}@example.com", 'password' => bcrypt('x'), 'user_type' => 'customer']);
        $this->customer = MarketplaceCustomer::on('central')->create(['user_id' => $this->user->id, 'customer_number' => 'MKT-A-'.$this->run, 'phone' => '+2547'.random_int(10000000, 99999999), 'is_active' => true]);
        $this->product = MarketplaceProduct::on('central')->create([
            'tenant_id' => $this->tenantId, 'tenant_product_id' => random_int(1000, 9999), 'name' => 'Wall clock', 'slug' => 'wall-clock-'.$this->run, 'sku' => 'S-'.uniqid(),
            'online_price' => 1000, 'base_uom_code' => 'pcs', 'base_uom_name' => 'Piece', 'tax_rate' => 0, 'available_quantity' => 5, 'stock_status' => 'in_stock', 'is_active' => true,
            'tenant_category_name' => 'Home Décor',
        ]);
    }

    protected function tearDown(): void
    {
        $orderIds = MarketplaceOrder::on('central')->where('tenant_id', $this->tenantId)->pluck('id');
        MarketplaceRefund::query()->where('tenant_id', $this->tenantId)->delete();
        MarketplaceReturnCase::query()->where('tenant_id', $this->tenantId)->delete();
        MarketplacePayoutLine::query()->where('tenant_id', $this->tenantId)->delete();
        MarketplacePayout::query()->where('tenant_id', $this->tenantId)->delete();
        ProductReview::query()->where('marketplace_product_id', $this->product->id)->forceDelete();
        foreach (['marketplace_order_payments', 'marketplace_order_items'] as $table) {
            DB::connection('central')->table($table)->whereIn('order_id', $orderIds)->delete();
        }
        MarketplaceOrder::on('central')->whereIn('id', $orderIds)->forceDelete();
        MarketplaceProduct::on('central')->where('tenant_id', $this->tenantId)->forceDelete();
        AdminAuditLog::on('central')->where('tenant_id', $this->tenantId)->delete();
        MessageLog::query()->where('tenant_id', $this->tenantId)->delete();
        BusinessDetail::on('central')->where('tenant_id', $this->tenantId)->forceDelete();
        $this->customer->forceDelete();
        $this->user->forceDelete();
        DB::connection('central')->table('tenants')->where('id', $this->tenantId)->delete();
        DB::connection('central')->statement('SET foreign_key_checks = 1');
        parent::tearDown();
    }

    /** @return array<string, string> */
    private function as(string $role): array
    {
        return ['Authorization' => 'Bearer internal-test-token-0123456789abcdef0123456789', 'X-Admin-Id' => '7', 'X-Admin-Name' => 'Staff', 'X-Admin-Role' => $role, 'Accept' => 'application/json'];
    }

    private function handedOverCardOrder(): MarketplaceOrder
    {
        $order = MarketplaceOrder::on('central')->create([
            'order_number' => 'MKT-A-'.strtoupper(uniqid()), 'customer_id' => $this->customer->id, 'tenant_id' => $this->tenantId, 'merchant_name' => 'Admin Duka',
            'subtotal' => 1000, 'tax_amount' => 0, 'discount_amount' => 0, 'delivery_fee' => 0, 'total_amount' => 1000, 'fulfillment_type' => 'pickup', 'order_status' => OrderStatus::Confirmed,
        ]);
        DB::connection('central')->table('marketplace_order_items')->insert([
            'order_id' => $order->id, 'marketplace_product_id' => $this->product->id, 'tenant_product_id' => $this->product->tenant_product_id, 'product_name' => 'Wall clock',
            'product_sku' => $this->product->sku, 'uom_code' => 'pcs', 'uom_name' => 'Piece', 'quantity' => 1, 'quantity_in_base_uom' => 1, 'unit_price' => 1000, 'tax_rate' => 0,
            'tax_amount' => 0, 'discount_amount' => 0, 'subtotal' => 1000, 'fulfillment_status' => 'pending', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $reference = 'PCO-'.uniqid();
        FakeCardGateway::put($reference, ['reference' => $reference, 'amount' => 1000, 'status' => 'success', 'refunds' => [], 'email' => 'b@example.com']);
        DB::connection('central')->table('marketplace_order_payments')->insert([
            'order_id' => $order->id, 'payment_method' => 'card', 'amount' => 1000, 'payment_status' => 'completed', 'transaction_reference' => $reference,
            'provider_reference' => $reference, 'completed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $order->update(['order_status' => OrderStatus::Completed]);

        return $order->fresh();
    }

    public function test_operations_decide_a_dispute_and_see_the_evidence_support_cannot_decide(): void
    {
        $returns = app(ReturnCaseService::class);
        $order = $this->handedOverCardOrder();
        $case = $returns->open($order, [['order_item_id' => $order->items()->value('id'), 'quantity' => 1]], 'faulty', 'Stopped', [UploadedFile::fake()->image('b.jpg')]);
        $case = $returns->dispute($returns->inspect($returns->received($case), 'reject', null, 'Dropped'), 'It arrived broken');

        $this->getJson("/api/internal/admin/marketplace/returns/{$case->reference}/photos/buyer/0", $this->as('support'))->assertOk();
        $this->getJson("/api/internal/admin/marketplace/returns/{$case->reference}/photos/shop/0", $this->as('support'))->assertNotFound();
        $this->postJson("/api/internal/admin/marketplace/returns/{$case->reference}/decide", ['decision' => 'refund_full', 'note' => 'Photos show a crack'], $this->as('support'))->assertForbidden();
        $this->postJson("/api/internal/admin/marketplace/returns/{$case->reference}/decide", ['decision' => 'refund_partial', 'note' => 'x'], $this->as('operations'))
            ->assertUnprocessable()->assertJsonValidationErrors(['refund_amount', 'note']);

        $this->postJson("/api/internal/admin/marketplace/returns/{$case->reference}/decide", ['decision' => 'refund_full', 'note' => 'Photos show a crack'], $this->as('operations'))
            ->assertOk()->assertJsonPath('data.status', 'refunded'); // card refunds at once
        $this->assertSame('admin:7', $case->fresh()->decided_by);
        $this->assertSame('marketplace.return.decide', AdminAuditLog::on('central')->where('tenant_id', $this->tenantId)->latest('id')->value('action'));
    }

    public function test_finance_resends_a_failed_refund_and_a_failed_payout_and_confirms_a_checked_one(): void
    {
        $order = $this->handedOverCardOrder();
        $failed = MarketplaceRefund::query()->create(['order_id' => $order->id, 'payment_id' => $order->payments()->value('id'), 'tenant_id' => $this->tenantId, 'kind' => 'cancellation',
            'amount' => 400, 'method' => 'card', 'status' => 'failed', 'reason' => 'gateway down', 'idempotency_key' => 'test-failed-'.$this->run]);
        $this->postJson("/api/internal/admin/marketplace/refunds/{$failed->id}/retry", ['reason' => 'Paystack is back'], $this->as('operations'))->assertForbidden();
        $this->postJson("/api/internal/admin/marketplace/refunds/{$failed->id}/retry", ['reason' => 'Paystack is back'], $this->as('finance'))
            ->assertOk()->assertJsonPath('data.status', 'completed');
        $this->postJson("/api/internal/admin/marketplace/refunds/{$failed->id}/retry", ['reason' => 'again'], $this->as('finance'))->assertOk(); // idempotent per failed refund
        $this->assertSame(2, MarketplaceRefund::query()->where('order_id', $order->id)->count());

        MarketplacePayoutLine::query()->where('order_id', $order->id)->update(['eligible_at' => now()->subMinute()]);
        app(PayoutService::class)->runBatch(now());
        $payout = MarketplacePayout::query()->where('tenant_id', $this->tenantId)->sole();
        $payout->update(['status' => 'failed_final', 'failure_reason' => 'Initiator invalid']);
        $this->postJson("/api/internal/admin/marketplace/payouts/{$payout->id}/retry", ['reason' => 'Fixed the initiator'], $this->as('finance'))
            ->assertOk()->assertJsonPath('data.status', 'processing');
        $this->assertNotNull(FakeMpesaGateway::find('b2c', $payout->fresh()->conversation_id));

        $payout->update(['status' => 'needs_check']);
        $this->postJson("/api/internal/admin/marketplace/payouts/{$payout->id}/mark-paid", ['mpesa_receipt' => 'bad', 'reason' => 'seen'], $this->as('finance'))
            ->assertUnprocessable()->assertJsonValidationErrors('mpesa_receipt');
        $this->postJson("/api/internal/admin/marketplace/payouts/{$payout->id}/mark-paid", ['mpesa_receipt' => 'SKR1234567', 'reason' => 'On the B2C statement'], $this->as('finance'))
            ->assertOk()->assertJsonPath('data.status', 'paid');
        $this->postJson("/api/internal/admin/marketplace/payouts/{$payout->id}/retry", ['reason' => 'oops'], $this->as('finance'))->assertUnprocessable(); // paid: never again
    }

    public function test_a_hold_stops_payouts_until_released(): void
    {
        $order = $this->handedOverCardOrder();
        MarketplacePayoutLine::query()->where('order_id', $order->id)->update(['eligible_at' => now()->subMinute()]);

        $this->postJson("/api/internal/admin/shops/{$this->tenantId}/payouts/hold", ['reason' => 'Checking the M-Pesa number'], $this->as('operations'))->assertOk();
        $this->assertSame('payouts on hold', app(PayoutService::class)->runBatch(now())['skipped'][$this->tenantId]);
        $this->postJson("/api/internal/admin/shops/{$this->tenantId}/payouts/release", ['reason' => 'Number confirmed'], $this->as('operations'))->assertForbidden();
        $this->postJson("/api/internal/admin/shops/{$this->tenantId}/payouts/release", ['reason' => 'Number confirmed'], $this->as('finance'))->assertOk();
        $this->assertSame(1, app(PayoutService::class)->runBatch(now())['created']);
        $this->assertSame(['marketplace.payouts.hold', 'marketplace.payouts.release'],
            AdminAuditLog::on('central')->where('tenant_id', $this->tenantId)->orderBy('id')->pluck('action')->all());
    }

    public function test_reviews_are_moderated_by_support(): void
    {
        $review = ProductReview::query()->create(['marketplace_product_id' => $this->product->id, 'customer_id' => $this->customer->id, 'rating' => 1,
            'review_text' => 'Spam spam', 'status' => 'pending', 'is_verified_purchase' => false]);
        $this->postJson("/api/internal/admin/marketplace/reviews/product/{$review->id}/moderate", ['action' => 'reject'], $this->as('support'))
            ->assertUnprocessable()->assertJsonValidationErrors('rejection_reason');
        $this->postJson("/api/internal/admin/marketplace/reviews/product/{$review->id}/moderate", ['action' => 'reject', 'rejection_reason' => 'Spam'], $this->as('support'))
            ->assertOk()->assertJsonPath('data.status', 'rejected');
        $this->assertSame(7, (int) $review->fresh()->moderated_by);
        $this->postJson("/api/internal/admin/marketplace/reviews/product/{$review->id}/moderate", ['action' => 'approve'], $this->as('finance'))->assertForbidden();
    }
}
