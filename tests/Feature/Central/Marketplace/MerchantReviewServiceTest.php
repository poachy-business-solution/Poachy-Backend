<?php

namespace Tests\Feature\Central\Marketplace;

use App\Enums\Central\OrderStatus;
use App\Enums\Central\ReviewStatus;
use App\Models\MarketplaceCustomer;
use App\Models\MarketplaceOrder;
use App\Models\MerchantReview;
use App\Models\User;
use App\Repositories\Central\MerchantReviewRepository;
use App\Services\Central\Marketplace\MerchantReviewService;
use App\Services\Central\Marketplace\ReviewContentChecker;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class MerchantReviewServiceTest extends TestCase
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

        $this->tenantId = 'merchant-review-test-'.uniqid();
        DB::connection('central')->table('tenants')->insertOrIgnore([
            'id' => $this->tenantId,
            'created_at' => now(),
            'updated_at' => now(),
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

    private function makeService(): MerchantReviewService
    {
        return new MerchantReviewService(new MerchantReviewRepository, new ReviewContentChecker);
    }

    private function createCustomer(): MarketplaceCustomer
    {
        $user = User::on('central')->create([
            'name' => 'Merchant Review Customer',
            'email' => 'merchant-review-customer-'.uniqid().'@test.com',
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

    private function createOrder(?MarketplaceCustomer $customer = null, array $overrides = []): MarketplaceOrder
    {
        return MarketplaceOrder::on('central')->create(array_merge([
            'order_number' => 'ORD-'.uniqid(),
            'customer_id' => ($customer ?? $this->customer)->id,
            'tenant_id' => $this->tenantId,
            'merchant_name' => 'Test Merchant',
            'subtotal' => 500.0,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'delivery_fee' => 0,
            'total_amount' => 500.0,
            'fulfillment_type' => 'pickup',
            'order_status' => OrderStatus::Completed,
        ], $overrides));
    }

    private function baseReviewData(array $overrides = []): array
    {
        return array_merge([
            'overall_rating' => 5,
            'product_quality_rating' => 5,
            'delivery_rating' => 4,
            'review_text' => 'Great merchant, fast delivery.',
        ], $overrides);
    }

    // =========================================================================
    // storeReview()
    // =========================================================================

    public function test_store_review_creates_pending_review_tied_to_order_tenant(): void
    {
        $order = $this->createOrder();

        $review = $this->makeService()->storeReview($this->customer, $order->id, $this->baseReviewData());

        $this->assertSame($this->tenantId, $review->tenant_id);
        $this->assertSame($order->id, $review->order_id);
        $this->assertSame(ReviewStatus::Pending, $review->status);
    }

    public function test_store_review_throws_when_order_does_not_belong_to_customer(): void
    {
        $otherCustomer = $this->createCustomer();
        $order = $this->createOrder($otherCustomer);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('does not belong to your account');

        $this->makeService()->storeReview($this->customer, $order->id, $this->baseReviewData());
    }

    public function test_store_review_throws_when_order_not_completed(): void
    {
        $order = $this->createOrder(null, ['order_status' => OrderStatus::Pending]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('completed orders');

        $this->makeService()->storeReview($this->customer, $order->id, $this->baseReviewData());
    }

    public function test_store_review_throws_when_review_window_expired(): void
    {
        $order = $this->createOrder();
        DB::connection('central')->table('marketplace_orders')
            ->where('id', $order->id)->update(['updated_at' => now()->subDays(31)]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('30-day review window');

        $this->makeService()->storeReview($this->customer, $order->id, $this->baseReviewData());
    }

    public function test_store_review_throws_on_duplicate_for_same_order(): void
    {
        $order = $this->createOrder();
        $this->makeService()->storeReview($this->customer, $order->id, $this->baseReviewData());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('already been submitted');

        $this->makeService()->storeReview($this->customer, $order->id, $this->baseReviewData());
    }

    public function test_store_review_flags_content_with_urls(): void
    {
        $order = $this->createOrder();

        $review = $this->makeService()->storeReview($this->customer, $order->id, $this->baseReviewData([
            'review_text' => 'Visit https://spam.example.com for a discount!',
        ]));

        $this->assertSame(ReviewStatus::Flagged, $review->status);
    }

    public function test_store_review_service_rating_is_optional(): void
    {
        $order = $this->createOrder();

        $review = $this->makeService()->storeReview($this->customer, $order->id, $this->baseReviewData());

        $this->assertNull($review->service_rating);
    }

    public function test_store_review_throws_after_rate_limit_exceeded(): void
    {
        $key = "review:merchant:{$this->customer->id}";
        for ($i = 0; $i < 5; $i++) {
            RateLimiter::hit($key, 3600);
        }

        $order = $this->createOrder();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('too many reviews');

        $this->makeService()->storeReview($this->customer, $order->id, $this->baseReviewData());
    }

    // =========================================================================
    // getApprovedReviewsForTenant()
    // =========================================================================

    public function test_get_approved_reviews_for_tenant_excludes_non_approved(): void
    {
        $approvedOrder = $this->createOrder();
        $approved = MerchantReview::on('central')->create([
            'tenant_id' => $this->tenantId,
            'customer_id' => $this->customer->id,
            'order_id' => $approvedOrder->id,
            'overall_rating' => 5,
            'status' => ReviewStatus::Approved,
        ]);

        $otherCustomer = $this->createCustomer();
        $pendingOrder = $this->createOrder($otherCustomer);
        $pending = MerchantReview::on('central')->create([
            'tenant_id' => $this->tenantId,
            'customer_id' => $otherCustomer->id,
            'order_id' => $pendingOrder->id,
            'overall_rating' => 3,
            'status' => ReviewStatus::Pending,
        ]);

        $result = $this->makeService()->getApprovedReviewsForTenant($this->tenantId);

        $this->assertTrue($result->getCollection()->contains('id', $approved->id));
        $this->assertFalse($result->getCollection()->contains('id', $pending->id));
    }
}
