<?php

namespace Tests\Feature\Central\Marketplace;

use App\DataTransferObjects\Sync\MarketplaceFulfillmentSyncDTO;
use App\Enums\Central\OrderStatus;
use App\Models\MarketplaceCustomer;
use App\Models\MarketplaceOrder;
use App\Models\MerchantReview;
use App\Models\User;
use App\Repositories\Central\MerchantReviewRepository;
use App\Services\Central\Marketplace\MerchantReviewService;
use App\Services\Central\Marketplace\ReviewContentChecker;
use App\Services\Central\Sync\MarketplaceFulfillmentSyncService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Regression coverage for audit item 11b: before this feature, order_status
 * could never reach Completed in production (nothing ever advanced it past
 * Confirmed), which silently blocked all review submissions. These tests
 * prove the fulfillment sync path is what actually unblocks reviews now.
 */
class MarketplaceFulfillmentReviewIntegrationTest extends TestCase
{
    private string $tenantId;

    private MarketplaceCustomer $customer;

    private array $userIds = [];

    private array $customerIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.connections.central.host', env('CENTRAL_DB_HOST', '127.0.0.1'));
        Config::set('database.connections.central.port', env('CENTRAL_DB_PORT', '3306'));
        Config::set('database.connections.central.database', env('CENTRAL_DB_DATABASE', 'poachy'));
        Config::set('database.connections.central.username', env('CENTRAL_DB_USERNAME', 'root'));
        Config::set('database.connections.central.password', env('CENTRAL_DB_PASSWORD', ''));
        DB::purge('central');
        DB::setDefaultConnection('central');
        DB::connection('central')->statement('SET foreign_key_checks = 0');

        $this->tenantId = 'mkt-review-integration-'.uniqid();
        DB::connection('central')->table('tenants')->insertOrIgnore([
            'id' => $this->tenantId, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->customer = $this->createCustomer();
    }

    protected function tearDown(): void
    {
        DB::connection('central')->statement('SET foreign_key_checks = 0');
        MerchantReview::on('central')->where('tenant_id', $this->tenantId)->forceDelete();
        MarketplaceOrder::on('central')->where('tenant_id', $this->tenantId)->forceDelete();
        MarketplaceCustomer::on('central')->whereIn('id', $this->customerIds)->forceDelete();
        User::on('central')->whereIn('id', $this->userIds)->forceDelete();
        DB::connection('central')->table('tenants')->where('id', $this->tenantId)->delete();
        DB::connection('central')->statement('SET foreign_key_checks = 1');

        parent::tearDown();
    }

    private function createCustomer(): MarketplaceCustomer
    {
        $user = User::on('central')->create([
            'name' => 'Review Integration Customer',
            'email' => 'review-integration-'.uniqid().'@test.com',
            'password' => bcrypt('password'),
            'user_type' => 'customer',
        ]);
        $this->userIds[] = $user->id;

        $customer = MarketplaceCustomer::on('central')->create([
            'user_id' => $user->id,
            'customer_number' => 'MKT-'.uniqid(),
            'phone' => '0712'.rand(100000, 999999),
            'phone_verified' => true,
        ]);
        $this->customerIds[] = $customer->id;

        return $customer;
    }

    private function createOrder(array $overrides = []): MarketplaceOrder
    {
        return MarketplaceOrder::on('central')->create(array_merge([
            'order_number' => 'ORD-'.uniqid(),
            'customer_id' => $this->customer->id,
            'tenant_id' => $this->tenantId,
            'merchant_name' => 'Test Merchant',
            'subtotal' => 500.0,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'delivery_fee' => 0,
            'total_amount' => 500.0,
            'fulfillment_type' => 'pickup',
            'order_status' => OrderStatus::Confirmed,
        ], $overrides));
    }

    private function makeSyncService(): MarketplaceFulfillmentSyncService
    {
        return app(MarketplaceFulfillmentSyncService::class);
    }

    private function makeDto(MarketplaceOrder $order, string $status): MarketplaceFulfillmentSyncDTO
    {
        return MarketplaceFulfillmentSyncDTO::fromArray([
            'tenant_id' => $this->tenantId,
            'sale_id' => 1,
            'central_order_id' => $order->id,
            'fulfillment_status' => $status,
        ]);
    }

    private function makeReviewService(): MerchantReviewService
    {
        return new MerchantReviewService(new MerchantReviewRepository, new ReviewContentChecker);
    }

    public function test_review_is_rejected_before_order_reaches_completed_via_sync(): void
    {
        $order = $this->createOrder();

        $this->makeSyncService()->apply($this->makeDto($order, 'preparing'));
        $this->assertSame('processing', $order->fresh()->order_status->value);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Merchant reviews can only be submitted for completed orders.');

        $this->makeReviewService()->storeReview($this->customer, $order->id, [
            'overall_rating' => 5,
            'review_text' => 'Too early to review.',
        ]);
    }

    public function test_review_succeeds_once_fulfillment_sync_marches_order_to_completed(): void
    {
        $order = $this->createOrder();

        // Mirrors a real merchant workflow: PREPARING -> READY -> DELIVERED,
        // each applied exactly as the inbound sync job would.
        $this->makeSyncService()->apply($this->makeDto($order, 'preparing'));
        $this->makeSyncService()->apply($this->makeDto($order, 'ready'));
        $this->makeSyncService()->apply($this->makeDto($order, 'delivered'));

        $this->assertSame('completed', $order->fresh()->order_status->value);

        $review = $this->makeReviewService()->storeReview($this->customer, $order->id, [
            'overall_rating' => 5,
            'product_quality_rating' => 5,
            'delivery_rating' => 5,
            'review_text' => 'Order finally completed, review works now.',
        ]);

        $this->assertNotNull($review->id);
        $this->assertSame($order->id, $review->order_id);
    }
}
