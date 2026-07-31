<?php

namespace Tests\Feature\Central\Marketplace;

use App\Enums\Central\OrderStatus;
use App\Enums\Central\ReviewStatus;
use App\Models\MarketplaceCustomer;
use App\Models\MarketplaceOrder;
use App\Models\MarketplaceOrderItem;
use App\Models\MarketplaceProduct;
use App\Models\ProductReview;
use App\Models\User;
use App\Repositories\Central\ProductReviewRepository;
use App\Services\Central\Marketplace\ProductReviewService;
use App\Services\Central\Marketplace\ReviewContentChecker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductReviewServiceTest extends TestCase
{
    private string $tenantId;

    private MarketplaceProduct $product;

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

        Storage::fake('public');

        $this->tenantId = 'review-test-'.uniqid();
        DB::connection('central')->table('tenants')->insertOrIgnore([
            'id' => $this->tenantId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->product = MarketplaceProduct::on('central')->create([
            'tenant_id' => $this->tenantId,
            'name' => 'Reviewable Product',
            'slug' => 'reviewable-product-'.uniqid(),
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
    }

    protected function tearDown(): void
    {
        DB::connection('central')->statement('SET foreign_key_checks = 0');
        ProductReview::on('central')->where('marketplace_product_id', $this->product->id)->forceDelete();
        MarketplaceOrderItem::on('central')->where('marketplace_product_id', $this->product->id)->forceDelete();
        MarketplaceOrder::on('central')->where('tenant_id', $this->tenantId)->forceDelete();
        MarketplaceCustomer::on('central')->whereIn('id', $this->customerIds)->forceDelete();
        User::on('central')->whereIn('id', $this->userIds)->forceDelete();
        MarketplaceProduct::on('central')->where('id', $this->product->id)->forceDelete();
        DB::connection('central')->table('tenants')->where('id', $this->tenantId)->delete();
        DB::connection('central')->statement('SET foreign_key_checks = 1');

        parent::tearDown();
    }

    private function makeService(): ProductReviewService
    {
        return new ProductReviewService(new ProductReviewRepository, new ReviewContentChecker);
    }

    private function createCustomer(): MarketplaceCustomer
    {
        $user = User::on('central')->create([
            'name' => 'Review Customer',
            'email' => 'review-customer-'.uniqid().'@test.com',
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

    private function createCompletedOrderWithProduct(?MarketplaceCustomer $customer = null, array $orderOverrides = []): MarketplaceOrder
    {
        $order = MarketplaceOrder::on('central')->create(array_merge([
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
        ], $orderOverrides));

        MarketplaceOrderItem::on('central')->create([
            'order_id' => $order->id,
            'marketplace_product_id' => $this->product->id,
            'tenant_product_id' => 1,
            'product_name' => $this->product->name,
            'product_sku' => $this->product->sku,
            'uom_code' => 'pcs',
            'uom_name' => 'Piece',
            'quantity' => 1,
            'quantity_in_base_uom' => 1,
            'unit_price' => 500,
            'tax_rate' => 0,
            'tax_amount' => 0,
            'subtotal' => 500,
        ]);

        return $order;
    }

    private function createReview(array $overrides = []): ProductReview
    {
        return ProductReview::on('central')->create(array_merge([
            'marketplace_product_id' => $this->product->id,
            'customer_id' => $this->customer->id,
            'rating' => 4.0,
            'review_text' => 'Good product overall.',
            'status' => ReviewStatus::Pending,
        ], $overrides));
    }

    // =========================================================================
    // storeReview()
    // =========================================================================

    public function test_store_review_without_order_creates_unverified_pending_review(): void
    {
        $review = $this->makeService()->storeReview($this->customer, $this->product->id, [
            'rating' => 5,
            'review_text' => 'Great product, works as expected.',
        ]);

        $this->assertFalse($review->is_verified_purchase);
        $this->assertSame(ReviewStatus::Pending, $review->status);
        $this->assertNull($review->order_id);
    }

    public function test_store_review_with_valid_order_marks_verified_purchase(): void
    {
        $order = $this->createCompletedOrderWithProduct();

        $review = $this->makeService()->storeReview($this->customer, $this->product->id, [
            'order_id' => $order->id,
            'rating' => 5,
            'review_text' => 'Verified purchase review text.',
        ]);

        $this->assertTrue($review->is_verified_purchase);
        $this->assertSame($order->id, $review->order_id);
    }

    public function test_store_review_throws_when_order_does_not_belong_to_customer(): void
    {
        $otherCustomer = $this->createCustomer();
        $order = $this->createCompletedOrderWithProduct($otherCustomer);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('does not belong to your account');

        $this->makeService()->storeReview($this->customer, $this->product->id, [
            'order_id' => $order->id, 'rating' => 5, 'review_text' => 'Trying to fake a review.',
        ]);
    }

    public function test_store_review_throws_when_order_not_completed(): void
    {
        $order = $this->createCompletedOrderWithProduct(null, ['order_status' => OrderStatus::Pending]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('completed orders');

        $this->makeService()->storeReview($this->customer, $this->product->id, [
            'order_id' => $order->id, 'rating' => 5, 'review_text' => 'Too early to review this.',
        ]);
    }

    public function test_store_review_throws_when_product_not_in_order(): void
    {
        $order = MarketplaceOrder::on('central')->create([
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
            'order_status' => OrderStatus::Completed,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('not part of the specified order');

        $this->makeService()->storeReview($this->customer, $this->product->id, [
            'order_id' => $order->id, 'rating' => 5, 'review_text' => 'This product was never ordered.',
        ]);
    }

    public function test_store_review_throws_when_review_window_expired(): void
    {
        $order = $this->createCompletedOrderWithProduct();
        DB::connection('central')->table('marketplace_orders')
            ->where('id', $order->id)->update(['updated_at' => now()->subDays(31)]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('30-day review window');

        $this->makeService()->storeReview($this->customer, $this->product->id, [
            'order_id' => $order->id, 'rating' => 5, 'review_text' => 'Way too late to review.',
        ]);
    }

    public function test_store_review_throws_on_duplicate_for_same_product_and_order(): void
    {
        $order = $this->createCompletedOrderWithProduct();
        $this->makeService()->storeReview($this->customer, $this->product->id, [
            'order_id' => $order->id, 'rating' => 5, 'review_text' => 'First review for this order.',
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('already submitted a review');

        $this->makeService()->storeReview($this->customer, $this->product->id, [
            'order_id' => $order->id, 'rating' => 3, 'review_text' => 'Trying to review again.',
        ]);
    }

    public function test_store_review_flags_content_with_urls(): void
    {
        $review = $this->makeService()->storeReview($this->customer, $this->product->id, [
            'rating' => 5, 'review_text' => 'Visit https://spam.example.com for a discount!',
        ]);

        $this->assertSame(ReviewStatus::Flagged, $review->status);
    }

    public function test_store_review_stores_uploaded_images(): void
    {
        $review = $this->makeService()->storeReview($this->customer, $this->product->id, [
            'rating' => 5, 'review_text' => 'Photos attached of the product.',
        ], [UploadedFile::fake()->image('review1.jpg')]);

        $this->assertCount(1, $review->review_images);
        Storage::disk('public')->assertExists($review->review_images[0]);
    }

    public function test_store_review_throws_after_rate_limit_exceeded(): void
    {
        $key = "review:product:{$this->customer->id}";
        for ($i = 0; $i < 5; $i++) {
            RateLimiter::hit($key, 3600);
        }

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('too many reviews');

        $this->makeService()->storeReview($this->customer, $this->product->id, [
            'rating' => 5, 'review_text' => 'This should be rate limited.',
        ]);
    }

    // =========================================================================
    // getApprovedReviewsForProduct()
    // =========================================================================

    public function test_get_approved_reviews_for_product_excludes_non_approved(): void
    {
        $approved = $this->createReview(['status' => ReviewStatus::Approved]);
        $pending = $this->createReview(['status' => ReviewStatus::Pending, 'order_id' => null, 'customer_id' => $this->createCustomer()->id]);

        $result = $this->makeService()->getApprovedReviewsForProduct($this->product->id);

        $this->assertTrue($result->getCollection()->contains('id', $approved->id));
        $this->assertFalse($result->getCollection()->contains('id', $pending->id));
    }

    // =========================================================================
    // addMerchantResponse()
    // =========================================================================

    public function test_add_merchant_response_sets_text_and_timestamp(): void
    {
        $review = $this->createReview(['status' => ReviewStatus::Approved]);

        $updated = $this->makeService()->addMerchantResponse($review, $this->tenantId, 'Thank you for your feedback!');

        $this->assertSame('Thank you for your feedback!', $updated->merchant_response);
        $this->assertNotNull($updated->merchant_responded_at);
    }

    public function test_add_merchant_response_throws_for_wrong_tenant(): void
    {
        $review = $this->createReview(['status' => ReviewStatus::Approved]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('not authorised');

        $this->makeService()->addMerchantResponse($review, 'some-other-tenant', 'Trying to respond as a stranger.');
    }

    public function test_add_merchant_response_throws_when_review_not_approved(): void
    {
        $review = $this->createReview(['status' => ReviewStatus::Pending]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('not in a state');

        $this->makeService()->addMerchantResponse($review, $this->tenantId, 'Responding to an unapproved review.');
    }

    public function test_add_merchant_response_throws_when_already_responded(): void
    {
        $review = $this->createReview(['status' => ReviewStatus::Approved, 'merchant_response' => 'Already responded.']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('not in a state');

        $this->makeService()->addMerchantResponse($review, $this->tenantId, 'Second response attempt.');
    }

    public function test_add_merchant_response_throws_for_flagged_content(): void
    {
        $review = $this->createReview(['status' => ReviewStatus::Approved]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('not allowed');

        $this->makeService()->addMerchantResponse($review, $this->tenantId, 'Visit https://spam.example.com!!!');
    }

    // =========================================================================
    // updateMerchantResponse()
    // =========================================================================

    public function test_update_merchant_response_within_edit_window(): void
    {
        $review = $this->createReview([
            'status' => ReviewStatus::Approved,
            'merchant_response' => 'Original response.',
            'merchant_responded_at' => now()->subHours(2),
        ]);

        $updated = $this->makeService()->updateMerchantResponse($review, $this->tenantId, 'Edited response text.');

        $this->assertSame('Edited response text.', $updated->merchant_response);
    }

    public function test_update_merchant_response_throws_after_edit_window(): void
    {
        $review = $this->createReview([
            'status' => ReviewStatus::Approved,
            'merchant_response' => 'Original response.',
            'merchant_responded_at' => now()->subHours(25),
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('24 hours');

        $this->makeService()->updateMerchantResponse($review, $this->tenantId, 'Too late to edit this.');
    }

    public function test_update_merchant_response_throws_when_no_existing_response(): void
    {
        $review = $this->createReview(['status' => ReviewStatus::Approved]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('No existing merchant response');

        $this->makeService()->updateMerchantResponse($review, $this->tenantId, 'Nothing to update yet.');
    }

    public function test_update_merchant_response_throws_for_wrong_tenant(): void
    {
        $review = $this->createReview([
            'status' => ReviewStatus::Approved,
            'merchant_response' => 'Original response.',
            'merchant_responded_at' => now(),
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('not authorised');

        $this->makeService()->updateMerchantResponse($review, 'some-other-tenant', 'Trying to edit as a stranger.');
    }
}
