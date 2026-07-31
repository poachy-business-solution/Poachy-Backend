<?php

namespace Tests\Feature\Central\Marketplace;

use App\Enums\Central\ReviewStatus;
use App\Events\Central\ProductReviewApproved;
use App\Models\MarketplaceCustomer;
use App\Models\MarketplaceProduct;
use App\Models\MerchantReview;
use App\Models\ProductReview;
use App\Models\ReviewFlag;
use App\Models\User;
use App\Repositories\Central\MerchantReviewRepository;
use App\Repositories\Central\ProductReviewRepository;
use App\Services\Central\Marketplace\ReviewModerationService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ReviewModerationServiceTest extends TestCase
{
    private string $tenantId;

    private MarketplaceProduct $product;

    private MarketplaceCustomer $customer;

    private MarketplaceCustomer $otherCustomer;

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

        // Approving a ProductReview fires ProductReviewApproved -> the real (queued)
        // EnqueueApprovedReviewSync listener -> ProcessOutboundApprovedReviewSync job,
        // both ShouldQueue and both real production infrastructure unrelated to what
        // this file tests. Fake the queue so they don't run inline (matches the
        // Queue::fake() convention already used in MarketplaceOrderServiceTest etc.).
        Queue::fake();

        $this->tenantId = 'moderation-test-'.uniqid();
        DB::connection('central')->table('tenants')->insertOrIgnore([
            'id' => $this->tenantId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->product = MarketplaceProduct::on('central')->create([
            'tenant_id' => $this->tenantId,
            'name' => 'Moderated Product',
            'slug' => 'moderated-product-'.uniqid(),
            'sku' => 'SKU-'.uniqid(),
            'online_price' => 500,
            'base_uom_code' => 'pcs',
            'base_uom_name' => 'Piece',
            'tax_rate' => 0,
            'available_quantity' => 10,
            'stock_status' => 'in_stock',
            'is_active' => true,
        ]);

        $this->customer = $this->createCustomer();
        $this->otherCustomer = $this->createCustomer();
    }

    protected function tearDown(): void
    {
        DB::connection('central')->statement('SET foreign_key_checks = 0');
        ReviewFlag::on('central')->whereIn('customer_id', $this->customerIds)->delete();
        ProductReview::on('central')->where('marketplace_product_id', $this->product->id)->forceDelete();
        MerchantReview::on('central')->where('tenant_id', $this->tenantId)->forceDelete();
        MarketplaceCustomer::on('central')->whereIn('id', $this->customerIds)->forceDelete();
        User::on('central')->whereIn('id', $this->userIds)->forceDelete();
        MarketplaceProduct::on('central')->where('id', $this->product->id)->forceDelete();
        DB::connection('central')->table('tenants')->where('id', $this->tenantId)->delete();
        DB::connection('central')->statement('SET foreign_key_checks = 1');

        parent::tearDown();
    }

    private function makeService(): ReviewModerationService
    {
        return new ReviewModerationService(new ProductReviewRepository, new MerchantReviewRepository);
    }

    private function createCustomer(): MarketplaceCustomer
    {
        $user = User::on('central')->create([
            'name' => 'Moderation Customer',
            'email' => 'moderation-customer-'.uniqid().'@test.com',
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

    private function createProductReview(array $overrides = []): ProductReview
    {
        return ProductReview::on('central')->create(array_merge([
            'marketplace_product_id' => $this->product->id,
            'customer_id' => $this->customer->id,
            'rating' => 4.0,
            'review_text' => 'Decent product.',
            'status' => ReviewStatus::Pending,
        ], $overrides));
    }

    private function createMerchantReview(array $overrides = []): MerchantReview
    {
        $order = \App\Models\MarketplaceOrder::on('central')->create([
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
            'order_status' => \App\Enums\Central\OrderStatus::Completed,
        ]);

        return MerchantReview::on('central')->create(array_merge([
            'tenant_id' => $this->tenantId,
            'customer_id' => $this->customer->id,
            'order_id' => $order->id,
            'overall_rating' => 4,
            'status' => ReviewStatus::Pending,
        ], $overrides));
    }

    // =========================================================================
    // moderateProductReview()
    // =========================================================================

    public function test_moderate_product_review_approve(): void
    {
        $review = $this->createProductReview();

        $moderated = $this->makeService()->moderateProductReview($review, 'approve', 99);

        $this->assertSame(ReviewStatus::Approved, $moderated->status);
        $this->assertSame(99, $moderated->moderated_by);
        $this->assertNotNull($moderated->moderated_at);
    }

    public function test_moderate_product_review_approve_dispatches_approved_event(): void
    {
        Event::fake([ProductReviewApproved::class]);
        $review = $this->createProductReview();

        $this->makeService()->moderateProductReview($review, 'approve', 99);

        Event::assertDispatched(ProductReviewApproved::class, fn ($e) => $e->review->id === $review->id);
    }

    public function test_moderate_product_review_reject_requires_reason(): void
    {
        $review = $this->createProductReview();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('rejection reason is required');

        $this->makeService()->moderateProductReview($review, 'reject', 99);
    }

    public function test_moderate_product_review_reject_stores_reason(): void
    {
        $review = $this->createProductReview();

        $moderated = $this->makeService()->moderateProductReview($review, 'reject', 99, 'Inappropriate language');

        $this->assertSame(ReviewStatus::Rejected, $moderated->status);
        $this->assertSame('Inappropriate language', $moderated->rejection_reason);
    }

    public function test_moderate_product_review_flag_sets_flagged_status(): void
    {
        $review = $this->createProductReview();

        $moderated = $this->makeService()->moderateProductReview($review, 'flag', 99);

        $this->assertSame(ReviewStatus::Flagged, $moderated->status);
    }

    public function test_moderate_product_review_throws_for_invalid_action(): void
    {
        $review = $this->createProductReview();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid moderation action');

        $this->makeService()->moderateProductReview($review, 'delete', 99);
    }

    public function test_moderate_product_review_dismiss_flags_deletes_flags_and_approves(): void
    {
        $review = $this->createProductReview(['status' => ReviewStatus::Flagged]);
        ReviewFlag::on('central')->create([
            'customer_id' => $this->otherCustomer->id,
            'flaggable_type' => ProductReview::class,
            'flaggable_id' => $review->id,
            'reason' => 'Spam',
        ]);

        $moderated = $this->makeService()->moderateProductReview($review, 'dismiss_flags', 99);

        $this->assertSame(ReviewStatus::Approved, $moderated->status);
        $this->assertSame(0, $moderated->flags()->count());
    }

    // =========================================================================
    // moderateMerchantReview() — mirrors moderateProductReview(), no separate
    // approved-sync event exists for merchant reviews (confirmed as designed:
    // MerchantReviewObserver only recalculates the tenant rating aggregate,
    // unlike ProductReviewObserver which also dispatches ProductReviewApproved
    // for tenant sync — merchant reviews aren't synced back to the tenant).
    // =========================================================================

    public function test_moderate_merchant_review_approve(): void
    {
        $review = $this->createMerchantReview();

        $moderated = $this->makeService()->moderateMerchantReview($review, 'approve', 99);

        $this->assertSame(ReviewStatus::Approved, $moderated->status);
    }

    public function test_moderate_merchant_review_reject_requires_reason(): void
    {
        $review = $this->createMerchantReview();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('rejection reason is required');

        $this->makeService()->moderateMerchantReview($review, 'reject', 99);
    }

    public function test_moderate_merchant_review_dismiss_flags_without_approve(): void
    {
        $review = $this->createMerchantReview(['status' => ReviewStatus::Flagged]);
        ReviewFlag::on('central')->create([
            'customer_id' => $this->otherCustomer->id,
            'flaggable_type' => MerchantReview::class,
            'flaggable_id' => $review->id,
            'reason' => 'Spam',
        ]);

        $moderated = $this->makeService()->dismissFlags($review, 99, alsoApprove: false);

        $this->assertSame(ReviewStatus::Flagged, $moderated->status);
        $this->assertSame(0, $moderated->flags()->count());
    }

    // =========================================================================
    // flagProductReview() / flagMerchantReview()
    // =========================================================================

    public function test_flag_product_review_throws_for_own_review(): void
    {
        $review = $this->createProductReview(['status' => ReviewStatus::Approved]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot flag your own review');

        $this->makeService()->flagProductReview($review, $this->customer, 'Self-flagging attempt');
    }

    public function test_flag_product_review_creates_flag(): void
    {
        $review = $this->createProductReview(['status' => ReviewStatus::Approved]);

        $flag = $this->makeService()->flagProductReview($review, $this->otherCustomer, 'Misleading claims');

        $this->assertSame('Misleading claims', $flag->reason);
        $this->assertSame($this->otherCustomer->id, $flag->customer_id);
    }

    public function test_flag_product_review_is_idempotent_per_customer(): void
    {
        $review = $this->createProductReview(['status' => ReviewStatus::Approved]);

        $this->makeService()->flagProductReview($review, $this->otherCustomer, 'First reason');
        $this->makeService()->flagProductReview($review, $this->otherCustomer, 'Second reason');

        $this->assertSame(1, $review->flags()->count());
    }

    public function test_flag_merchant_review_throws_for_own_review(): void
    {
        $review = $this->createMerchantReview(['status' => ReviewStatus::Approved]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot flag your own review');

        $this->makeService()->flagMerchantReview($review, $this->customer, 'Self-flagging attempt');
    }

    public function test_flag_merchant_review_creates_flag(): void
    {
        $review = $this->createMerchantReview(['status' => ReviewStatus::Approved]);

        $flag = $this->makeService()->flagMerchantReview($review, $this->otherCustomer, 'Fake review');

        $this->assertSame('Fake review', $flag->reason);
    }

    // =========================================================================
    // getPending*() / getFlagged*()
    // =========================================================================

    public function test_get_pending_product_reviews_includes_pending_and_flagged(): void
    {
        $pending = $this->createProductReview(['status' => ReviewStatus::Pending]);
        $flagged = $this->createProductReview(['status' => ReviewStatus::Flagged, 'customer_id' => $this->otherCustomer->id]);
        $approved = $this->createProductReview(['status' => ReviewStatus::Approved, 'customer_id' => $this->createCustomer()->id]);

        $result = $this->makeService()->getPendingProductReviews();

        $this->assertTrue($result->getCollection()->contains('id', $pending->id));
        $this->assertTrue($result->getCollection()->contains('id', $flagged->id));
        $this->assertFalse($result->getCollection()->contains('id', $approved->id));
    }

    public function test_get_flagged_product_reviews_only_includes_reviews_with_flag_records(): void
    {
        $flaggedByCustomer = $this->createProductReview(['status' => ReviewStatus::Approved]);
        ReviewFlag::on('central')->create([
            'customer_id' => $this->otherCustomer->id,
            'flaggable_type' => ProductReview::class,
            'flaggable_id' => $flaggedByCustomer->id,
            'reason' => 'Spam',
        ]);
        $unflagged = $this->createProductReview(['status' => ReviewStatus::Approved, 'customer_id' => $this->createCustomer()->id]);

        $result = $this->makeService()->getFlaggedProductReviews();

        $this->assertTrue($result->getCollection()->contains('id', $flaggedByCustomer->id));
        $this->assertFalse($result->getCollection()->contains('id', $unflagged->id));
    }

    public function test_get_pending_merchant_reviews_includes_pending_and_flagged(): void
    {
        $pending = $this->createMerchantReview(['status' => ReviewStatus::Pending]);
        $flagged = $this->createMerchantReview(['status' => ReviewStatus::Flagged]);
        $approved = $this->createMerchantReview(['status' => ReviewStatus::Approved]);

        $result = $this->makeService()->getPendingMerchantReviews();

        $this->assertTrue($result->getCollection()->contains('id', $pending->id));
        $this->assertTrue($result->getCollection()->contains('id', $flagged->id));
        $this->assertFalse($result->getCollection()->contains('id', $approved->id));
    }

    public function test_get_flagged_merchant_reviews_only_includes_reviews_with_flag_records(): void
    {
        $flagged = $this->createMerchantReview(['status' => ReviewStatus::Approved]);
        ReviewFlag::on('central')->create([
            'customer_id' => $this->otherCustomer->id,
            'flaggable_type' => MerchantReview::class,
            'flaggable_id' => $flagged->id,
            'reason' => 'Spam',
        ]);
        $unflagged = $this->createMerchantReview(['status' => ReviewStatus::Approved]);

        $result = $this->makeService()->getFlaggedMerchantReviews();

        $this->assertTrue($result->getCollection()->contains('id', $flagged->id));
        $this->assertFalse($result->getCollection()->contains('id', $unflagged->id));
    }
}
