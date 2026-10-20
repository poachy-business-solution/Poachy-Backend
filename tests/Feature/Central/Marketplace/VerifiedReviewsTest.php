<?php

namespace Tests\Feature\Central\Marketplace;

use App\Enums\Central\MarketplaceReservationStatus;
use App\Enums\Central\OrderStatus;
use App\Enums\Central\ReviewStatus;
use App\Http\Controllers\Api\Public\PublicReviewController;
use App\Models\BusinessDetail;
use App\Models\MarketplaceCustomer;
use App\Models\MarketplaceOrder;
use App\Models\MarketplaceOrderItem;
use App\Models\MarketplaceProduct;
use App\Models\MarketplaceReservation;
use App\Models\MerchantReview;
use App\Models\ProductReview;
use App\Models\User;
use App\Services\Central\Marketplace\MerchantReviewService;
use App\Services\Central\Marketplace\ProductReviewService;
use App\Services\Central\Marketplace\ReviewEligibility;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Item 43: only verified buyers review — a completed order, or a reserve & collect code redeemed
 * at the counter — and the product and shop pages show approved reviews with first name + initial.
 */
class VerifiedReviewsTest extends TestCase
{
    private string $tenantId;

    private string $slug;

    private MarketplaceProduct $product;

    private MarketplaceCustomer $buyer;

    /** @var array<int, int> */
    private array $userIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('tenancy.database.central_connection', 'central');
        Config::set('database.connections.central.host', env('CENTRAL_DB_HOST', '127.0.0.1'));
        Config::set('database.connections.central.database', env('CENTRAL_DB_DATABASE', 'poachy'));
        DB::purge('central');
        DB::setDefaultConnection('central');
        DB::connection('central')->statement('SET foreign_key_checks = 0');
        Queue::fake();
        Cache::flush();

        $this->tenantId = 'reviews-'.strtolower(uniqid());
        $this->slug = 'duka-'.strtolower(substr(uniqid(), -8));
        DB::connection('central')->table('tenants')->insert(['id' => $this->tenantId, 'created_at' => now(), 'updated_at' => now()]);
        BusinessDetail::on('central')->create(['tenant_id' => $this->tenantId, 'business_name' => 'Review Duka', 'slug' => $this->slug, 'business_phone' => '0722'.random_int(100000, 999999),
            'business_type_id' => DB::connection('central')->table('business_types')->value('id'),
            'business_category_id' => DB::connection('central')->table('business_categories')->value('id'),
            'city' => 'Nairobi', 'county' => 'Nairobi', 'status' => 'active', 'is_verified' => true, 'verified_at' => now()]);
        $this->product = MarketplaceProduct::on('central')->create(['tenant_id' => $this->tenantId, 'name' => 'Wall clock', 'slug' => 'wall-clock-'.strtolower(uniqid()), 'sku' => 'SKU-'.uniqid(),
            'online_price' => 1500, 'base_uom_code' => 'pcs', 'base_uom_name' => 'Piece', 'tax_rate' => 0, 'available_quantity' => 5, 'stock_status' => 'in_stock', 'is_active' => true]);
        $this->buyer = $this->customer('Wanjiru Kamau');
    }

    protected function tearDown(): void
    {
        ProductReview::query()->where('marketplace_product_id', $this->product->id)->forceDelete();
        MerchantReview::query()->where('tenant_id', $this->tenantId)->forceDelete();
        MarketplaceReservation::query()->where('tenant_id', $this->tenantId)->delete();
        MarketplaceOrderItem::on('central')->where('marketplace_product_id', $this->product->id)->forceDelete();
        MarketplaceOrder::on('central')->where('tenant_id', $this->tenantId)->forceDelete();
        MarketplaceCustomer::on('central')->whereIn('user_id', $this->userIds)->forceDelete();
        User::on('central')->whereIn('id', $this->userIds)->forceDelete();
        $this->product->forceDelete();
        BusinessDetail::on('central')->where('tenant_id', $this->tenantId)->forceDelete();
        DB::connection('central')->table('tenants')->where('id', $this->tenantId)->delete();
        DB::connection('central')->statement('SET foreign_key_checks = 1');
        parent::tearDown();
    }

    private function customer(string $name): MarketplaceCustomer
    {
        $user = User::on('central')->create(['name' => $name, 'email' => Str::random(10).'@buyer.test', 'password' => bcrypt(Str::random(20)), 'user_type' => 'customer']);
        $this->userIds[] = $user->id;
        RateLimiter::clear("review:product:{$user->id}");

        return MarketplaceCustomer::on('central')->create(['user_id' => $user->id, 'customer_number' => 'MKT-'.uniqid(), 'phone' => '0712'.random_int(100000, 999999), 'phone_verified' => true]);
    }

    private function reservation(MarketplaceReservationStatus $status, ?MarketplaceCustomer $customer = null, ?int $productId = null): MarketplaceReservation
    {
        return MarketplaceReservation::query()->create(['code' => 'PCH-'.strtoupper(Str::random(4)), 'tenant_id' => $this->tenantId, 'customer_id' => ($customer ?? $this->buyer)->id,
            'marketplace_product_id' => $productId ?? $this->product->id, 'product_name' => 'Wall clock', 'quantity' => 1, 'unit_price' => 1500, 'status' => $status,
            'expires_at' => now()->addDay(), 'redeemed_at' => $status === MarketplaceReservationStatus::Redeemed ? now() : null, 'idempotency_key' => (string) Str::uuid()]);
    }

    private function order(OrderStatus $status): MarketplaceOrder
    {
        $order = MarketplaceOrder::on('central')->create(['order_number' => 'MKT-RV-'.uniqid(), 'customer_id' => $this->buyer->id, 'tenant_id' => $this->tenantId, 'merchant_name' => 'Review Duka',
            'subtotal' => 1500, 'tax_amount' => 0, 'discount_amount' => 0, 'delivery_fee' => 0, 'total_amount' => 1500, 'fulfillment_type' => 'pickup', 'order_status' => $status]);
        MarketplaceOrderItem::on('central')->create(['order_id' => $order->id, 'marketplace_product_id' => $this->product->id, 'tenant_product_id' => 1, 'product_name' => 'Wall clock',
            'product_sku' => 'X', 'uom_code' => 'pcs', 'uom_name' => 'Piece', 'quantity' => 1, 'quantity_in_base_uom' => 1, 'unit_price' => 1500, 'tax_rate' => 0, 'tax_amount' => 0, 'subtotal' => 1500]);

        return $order;
    }

    private function refused(callable $call, string $message): void
    {
        try {
            $call();
            $this->fail("Expected refusal: {$message}");
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            $this->assertStringContainsString($message, $e->getMessage());
        }
    }

    public function test_a_collected_reservation_lets_the_buyer_review_the_product_and_the_shop_once(): void
    {
        $products = app(ProductReviewService::class);
        $shops = app(MerchantReviewService::class);
        $text = ['rating' => 4.5, 'review_text' => 'Keeps good time, well packed.'];

        $held = $this->reservation(MarketplaceReservationStatus::Active);
        $this->refused(fn () => $products->storeReview($this->buyer, $this->product->id, $text + ['reservation_code' => $held->code]), 'once you have collected');
        $this->refused(fn () => $shops->storeReviewForReservation($this->buyer, $held->code, ['overall_rating' => 5, 'review_text' => 'Friendly and quick.']), 'once you have collected');

        $collected = $this->reservation(MarketplaceReservationStatus::Redeemed);
        $this->refused(fn () => $products->storeReview($this->customer('Other Buyer'), $this->product->id, $text + ['reservation_code' => $collected->code]), 'does not belong to your account');
        $review = $products->storeReview($this->buyer, $this->product->id, $text + ['reservation_code' => strtolower($collected->code)]);
        $this->assertTrue($review->is_verified_purchase);
        $this->assertSame([$collected->id, null, ReviewStatus::Pending], [$review->reservation_id, $review->order_id, $review->status]);
        $this->refused(fn () => $products->storeReview($this->buyer, $this->product->id, $text + ['reservation_code' => $collected->code]), 'already submitted');

        $shop = $shops->storeReviewForReservation($this->buyer, $collected->code, ['overall_rating' => 5, 'review_text' => 'Friendly and quick.']);
        $this->assertSame([$this->tenantId, $collected->id], [$shop->tenant_id, $shop->reservation_id]);
        $this->refused(fn () => $shops->storeReviewForReservation($this->buyer, $collected->code, ['overall_rating' => 5, 'review_text' => 'Friendly and quick.']), 'already been submitted');

        $this->assertSame(['shop_reviewed' => true, 'product_reviewed' => true], array_intersect_key(ReviewEligibility::forReservation($collected->fresh()), ['shop_reviewed' => 1, 'product_reviewed' => 1]));
        $this->assertNull(ReviewEligibility::forReservation($held));
    }

    public function test_anyone_else_is_refused_and_orders_say_what_is_left_to_review(): void
    {
        $products = app(ProductReviewService::class);
        $text = ['rating' => 4, 'review_text' => 'Nice clock for the price.'];
        $this->refused(fn () => $products->storeReview($this->buyer, $this->product->id, $text), 'Only buyers who received this product');

        $this->assertNull(ReviewEligibility::forOrder($this->order(OrderStatus::Processing)));
        $order = $this->order(OrderStatus::Completed);
        $this->assertSame(['shop_reviewed' => false, 'reviewed_product_ids' => []], array_intersect_key(ReviewEligibility::forOrder($order), ['shop_reviewed' => 1, 'reviewed_product_ids' => 1]));
        $products->storeReview($this->buyer, $this->product->id, $text + ['order_id' => $order->id]);
        $this->assertSame([$this->product->id], ReviewEligibility::forOrder($order->fresh())['reviewed_product_ids']);

        Sanctum::actingAs($this->buyer->user, ['*'], 'central');
        $this->postJson("/api/v1/central/marketplace/products/{$this->product->id}/reviews", $text)->assertUnprocessable()->assertJsonValidationErrors(['order_id', 'reservation_code']);
        $this->postJson('/api/v1/central/marketplace/reservations/PCH-ZZZZ/merchant-review', ['overall_rating' => 5, 'review_text' => 'Never bought here at all.'])
            ->assertUnprocessable()->assertJsonPath('message', 'The specified reservation does not belong to your account.');
    }

    public function test_product_and_shop_pages_show_approved_reviews_with_first_name_and_initial(): void
    {
        $order = $this->order(OrderStatus::Completed);
        ProductReview::query()->create(['marketplace_product_id' => $this->product->id, 'customer_id' => $this->buyer->id, 'order_id' => $order->id, 'rating' => 4.5,
            'title' => 'Good', 'review_text' => 'Keeps good time.', 'is_verified_purchase' => true, 'status' => ReviewStatus::Approved, 'merchant_response' => 'Thank you!']);
        ProductReview::query()->create(['marketplace_product_id' => $this->product->id, 'customer_id' => $this->customer('Pending Person')->id, 'rating' => 1,
            'review_text' => 'Not yet moderated.', 'is_verified_purchase' => true, 'status' => ReviewStatus::Pending]);
        MerchantReview::query()->create(['tenant_id' => $this->tenantId, 'customer_id' => $this->buyer->id, 'order_id' => $order->id, 'overall_rating' => 5,
            'review_text' => 'Friendly shop.', 'status' => ReviewStatus::Approved]);

        $product = $this->getJson("/api/v1/public/market/products/{$this->product->slug}/reviews")->assertOk()->json('data');
        $this->assertSame([4.5, 1, 1], [$product['summary']['average'], $product['summary']['count'], $product['summary']['stars'][4]]);
        $this->assertSame(['Wanjiru K.', true, 'Review Duka', 'Thank you!'], [$product['reviews'][0]['name'], $product['reviews'][0]['verified'], $product['reviews'][0]['shop'], $product['reviews'][0]['shop_response']]);
        $this->assertArrayNotHasKey('customer', $product['reviews'][0]);

        $shop = $this->getJson("/api/v1/public/shops/{$this->slug}/reviews")->assertOk()->json('data');
        $this->assertSame([5.0, 'Friendly shop.', 'Wanjiru K.'], [(float) $shop['summary']['average'], $shop['reviews'][0]['text'], $shop['reviews'][0]['name']]);
        $this->getJson('/api/v1/public/shops/no-such-shop/reviews')->assertNotFound();
        $this->assertSame('Buyer', PublicReviewController::displayName('  '));
        $this->assertSame('Akinyi', PublicReviewController::displayName('Akinyi'));
    }
}
