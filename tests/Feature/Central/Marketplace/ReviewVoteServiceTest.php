<?php

namespace Tests\Feature\Central\Marketplace;

use App\Enums\Central\OrderStatus;
use App\Enums\Central\ReviewStatus;
use App\Enums\Central\ReviewVoteType;
use App\Models\MarketplaceCustomer;
use App\Models\MarketplaceOrder;
use App\Models\MarketplaceProduct;
use App\Models\MerchantReview;
use App\Models\ProductReview;
use App\Models\ReviewVote;
use App\Models\User;
use App\Services\Central\Marketplace\ReviewVoteService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReviewVoteServiceTest extends TestCase
{
    private string $tenantId;

    private MarketplaceProduct $product;

    private MarketplaceCustomer $author;

    private MarketplaceCustomer $voter;

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

        $this->tenantId = 'review-vote-test-'.uniqid();
        DB::connection('central')->table('tenants')->insertOrIgnore([
            'id' => $this->tenantId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->product = MarketplaceProduct::on('central')->create([
            'tenant_id' => $this->tenantId,
            'name' => 'Voted Product',
            'slug' => 'voted-product-'.uniqid(),
            'sku' => 'SKU-'.uniqid(),
            'online_price' => 500,
            'base_uom_code' => 'pcs',
            'base_uom_name' => 'Piece',
            'tax_rate' => 0,
            'available_quantity' => 10,
            'stock_status' => 'in_stock',
            'is_active' => true,
        ]);

        $this->author = $this->createCustomer();
        $this->voter = $this->createCustomer();
    }

    protected function tearDown(): void
    {
        DB::connection('central')->statement('SET foreign_key_checks = 0');
        ReviewVote::on('central')->whereIn('customer_id', $this->customerIds)->delete();
        ProductReview::on('central')->where('marketplace_product_id', $this->product->id)->forceDelete();
        MerchantReview::on('central')->where('tenant_id', $this->tenantId)->forceDelete();
        MarketplaceOrder::on('central')->where('tenant_id', $this->tenantId)->forceDelete();
        MarketplaceCustomer::on('central')->whereIn('id', $this->customerIds)->forceDelete();
        User::on('central')->whereIn('id', $this->userIds)->forceDelete();
        MarketplaceProduct::on('central')->where('id', $this->product->id)->forceDelete();
        DB::connection('central')->table('tenants')->where('id', $this->tenantId)->delete();
        DB::connection('central')->statement('SET foreign_key_checks = 1');

        parent::tearDown();
    }

    private function makeService(): ReviewVoteService
    {
        return new ReviewVoteService;
    }

    private function createCustomer(): MarketplaceCustomer
    {
        $user = User::on('central')->create([
            'name' => 'Vote Customer',
            'email' => 'vote-customer-'.uniqid().'@test.com',
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
            'customer_id' => $this->author->id,
            'rating' => 4.0,
            'review_text' => 'Solid product.',
            'status' => ReviewStatus::Approved,
        ], $overrides));
    }

    private function createMerchantReview(array $overrides = []): MerchantReview
    {
        $order = MarketplaceOrder::on('central')->create([
            'order_number' => 'ORD-'.uniqid(),
            'customer_id' => $this->author->id,
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

        return MerchantReview::on('central')->create(array_merge([
            'tenant_id' => $this->tenantId,
            'customer_id' => $this->author->id,
            'order_id' => $order->id,
            'overall_rating' => 4,
            'status' => ReviewStatus::Approved,
        ], $overrides));
    }

    // =========================================================================
    // vote()
    // =========================================================================

    public function test_vote_throws_for_unapproved_review(): void
    {
        $review = $this->createProductReview(['status' => ReviewStatus::Pending]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('approved reviews');

        $this->makeService()->vote($this->voter, ReviewVoteType::Helpful, 'product', $review->id);
    }

    public function test_vote_throws_when_voting_on_own_review(): void
    {
        $review = $this->createProductReview();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot vote on your own review');

        $this->makeService()->vote($this->author, ReviewVoteType::Helpful, 'product', $review->id);
    }

    public function test_vote_throws_for_invalid_review_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid review type');

        $this->makeService()->vote($this->voter, ReviewVoteType::Helpful, 'bogus', 1);
    }

    public function test_vote_throws_for_unknown_review_id(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->makeService()->vote($this->voter, ReviewVoteType::Helpful, 'product', 999999999);
    }

    public function test_vote_helpful_on_product_review_updates_helpful_count(): void
    {
        $review = $this->createProductReview();

        $this->makeService()->vote($this->voter, ReviewVoteType::Helpful, 'product', $review->id);

        $this->assertSame(1, $review->fresh()->helpful_count);
        $this->assertSame(0, $review->fresh()->not_helpful_count);
    }

    public function test_vote_on_merchant_review_updates_helpful_count(): void
    {
        $review = $this->createMerchantReview();

        $this->makeService()->vote($this->voter, ReviewVoteType::Helpful, 'merchant', $review->id);

        $this->assertSame(1, $review->fresh()->helpful_count);
    }

    public function test_vote_switching_type_updates_counts_without_duplicate_row(): void
    {
        $review = $this->createProductReview();

        $this->makeService()->vote($this->voter, ReviewVoteType::Helpful, 'product', $review->id);
        $this->makeService()->vote($this->voter, ReviewVoteType::NotHelpful, 'product', $review->id);

        $fresh = $review->fresh();
        $this->assertSame(0, $fresh->helpful_count);
        $this->assertSame(1, $fresh->not_helpful_count);
        $this->assertSame(1, ReviewVote::on('central')->where('customer_id', $this->voter->id)->count());
    }

    public function test_vote_from_multiple_customers_accumulates_counts(): void
    {
        $review = $this->createProductReview();
        $thirdVoter = $this->createCustomer();

        $this->makeService()->vote($this->voter, ReviewVoteType::Helpful, 'product', $review->id);
        $this->makeService()->vote($thirdVoter, ReviewVoteType::Helpful, 'product', $review->id);

        $this->assertSame(2, $review->fresh()->helpful_count);
    }

    // =========================================================================
    // removeVote()
    // =========================================================================

    public function test_remove_vote_deletes_row_and_recalculates_counts(): void
    {
        $review = $this->createProductReview();
        $this->makeService()->vote($this->voter, ReviewVoteType::Helpful, 'product', $review->id);

        $this->makeService()->removeVote($this->voter, 'product', $review->id);

        $this->assertSame(0, $review->fresh()->helpful_count);
        $this->assertSame(0, ReviewVote::on('central')->where('customer_id', $this->voter->id)->count());
    }

    public function test_remove_vote_is_safe_when_no_vote_exists(): void
    {
        $review = $this->createProductReview();

        $this->makeService()->removeVote($this->voter, 'product', $review->id);

        $this->assertSame(0, $review->fresh()->helpful_count);
    }
}
