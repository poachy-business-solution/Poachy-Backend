<?php

namespace Tests\Feature\Central\Sync;

use App\Http\Controllers\Api\Central\Sync\MerchantReviewResponseController;
use App\Http\Requests\Central\Sync\InboundReviewResponseSyncRequest;
use App\Jobs\Central\ProcessInboundReviewResponseSync;
use App\Models\MarketplaceCustomer;
use App\Models\MarketplaceProduct;
use App\Models\ProductReview;
use App\Models\SyncQueueInbound;
use App\Models\User;
use App\Repositories\Central\ProductReviewRepository;
use App\Services\Central\Marketplace\ProductReviewService;
use App\Services\Central\Marketplace\ReviewContentChecker;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ReviewResponseSyncTest extends TestCase
{
    private string $tenantId;

    private MarketplaceProduct $product;

    private array $userIds = [];

    private array $customerIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('tenancy.database.central_connection', 'central');
        Config::set('database.connections.central.host', env('CENTRAL_DB_HOST', '127.0.0.1'));
        Config::set('database.connections.central.port', env('CENTRAL_DB_PORT', '3306'));
        Config::set('database.connections.central.database', env('CENTRAL_DB_DATABASE', 'poachy'));
        Config::set('database.connections.central.username', env('CENTRAL_DB_USERNAME', 'root'));
        Config::set('database.connections.central.password', env('CENTRAL_DB_PASSWORD', ''));
        Config::set('services.tenant_api.token', 'tenant-test-token');
        DB::purge('central');
        DB::setDefaultConnection('central');
        DB::connection('central')->statement('SET foreign_key_checks = 0');

        $this->tenantId = 'rr-sync-test-'.uniqid();
        DB::connection('central')->table('tenants')->insertOrIgnore([
            'id' => $this->tenantId, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::connection('central')->table('domains')->insert([
            'domain' => 'rr-sync-'.uniqid().'.poachy.test', 'tenant_id' => $this->tenantId,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->product = MarketplaceProduct::on('central')->create([
            'tenant_id' => $this->tenantId, 'tenant_product_id' => 1, 'name' => 'Reviewed Product',
            'slug' => 'reviewed-product-'.uniqid(), 'sku' => 'SKU-'.uniqid(), 'online_price' => 500,
            'base_uom_code' => 'pcs', 'base_uom_name' => 'Piece', 'tax_rate' => 0,
            'available_quantity' => 10, 'stock_status' => 'in_stock', 'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        DB::connection('central')->statement('SET foreign_key_checks = 0');
        SyncQueueInbound::on('central')->where('tenant_id', $this->tenantId)->delete();
        ProductReview::on('central')->where('marketplace_product_id', $this->product->id)->forceDelete();
        MarketplaceCustomer::on('central')->whereIn('id', $this->customerIds)->forceDelete();
        User::on('central')->whereIn('id', $this->userIds)->forceDelete();
        MarketplaceProduct::on('central')->where('id', $this->product->id)->forceDelete();
        DB::connection('central')->table('domains')->where('tenant_id', $this->tenantId)->delete();
        DB::connection('central')->table('tenants')->where('id', $this->tenantId)->delete();
        DB::connection('central')->statement('SET foreign_key_checks = 1');

        parent::tearDown();
    }

    private function createCustomer(): MarketplaceCustomer
    {
        $user = User::on('central')->create([
            'name' => 'Review Response Customer', 'email' => 'rr-customer-'.uniqid().'@test.com',
            'password' => bcrypt('password'), 'user_type' => 'customer',
        ]);
        $this->userIds[] = $user->id;

        $customer = MarketplaceCustomer::on('central')->create([
            'user_id' => $user->id, 'customer_number' => 'MKT-'.uniqid(),
            'phone' => '0712'.rand(100000, 999999), 'phone_verified' => true,
        ]);
        $this->customerIds[] = $customer->id;

        return $customer;
    }

    private function createReview(array $overrides = []): ProductReview
    {
        return ProductReview::on('central')->create(array_merge([
            'marketplace_product_id' => $this->product->id,
            'customer_id' => $this->createCustomer()->id,
            'rating' => 4.0,
            'review_text' => 'Solid product.',
            'status' => 'approved',
        ], $overrides));
    }

    private function makeStoreRequest(array $data, ?string $syncQueueId = null): InboundReviewResponseSyncRequest
    {
        $request = InboundReviewResponseSyncRequest::create('/', 'POST', $data);
        $request->setValidator(validator($data, (new InboundReviewResponseSyncRequest)->rules()));
        if ($syncQueueId !== null) {
            $request->headers->set('X-Sync-Queue-ID', $syncQueueId);
        }

        return $request;
    }

    // =========================================================================
    // MerchantReviewResponseController::store()
    // =========================================================================

    public function test_store_creates_sync_queue_with_tenant_sync_id_in_metadata_and_dispatches_job(): void
    {
        Queue::fake();
        $review = $this->createReview();
        $request = $this->makeStoreRequest([
            'tenant_id' => $this->tenantId, 'review_id' => $review->id, 'response_text' => 'Thanks for the great review!',
        ], syncQueueId: '701');

        $response = (new MerchantReviewResponseController)->store($request);

        $this->assertSame(202, $response->getStatusCode());
        $syncQueue = SyncQueueInbound::on('central')->where('tenant_id', $this->tenantId)->first();
        $this->assertNotNull($syncQueue);
        $this->assertSame('701', $syncQueue->metadata['sync_queue_id_from_tenant']);
        Queue::assertPushed(ProcessInboundReviewResponseSync::class);
    }

    public function test_store_returns_existing_sync_for_duplicate_idempotency_key(): void
    {
        Queue::fake();
        $review = $this->createReview();
        $data = ['tenant_id' => $this->tenantId, 'review_id' => $review->id, 'response_text' => 'Thanks for the great review!'];

        $first = (new MerchantReviewResponseController)->store($this->makeStoreRequest($data, '701'));
        $second = (new MerchantReviewResponseController)->store($this->makeStoreRequest($data, '702'));

        $firstBody = json_decode($first->getContent(), true);
        $secondBody = json_decode($second->getContent(), true);
        $this->assertSame($firstBody['data']['sync_id'], $secondBody['data']['sync_id']);
        $this->assertTrue($secondBody['data']['is_duplicate']);
        $this->assertSame(1, SyncQueueInbound::on('central')->where('tenant_id', $this->tenantId)->count());
    }

    // =========================================================================
    // ProcessInboundReviewResponseSync — inbound processing + ACK
    // =========================================================================

    private function createInboundSync(array $overrides = []): SyncQueueInbound
    {
        return SyncQueueInbound::on('central')->create(array_merge([
            'tenant_id' => $this->tenantId,
            'syncable_type' => 'ReviewResponse',
            'tenant_syncable_id' => 1,
            'action' => 'create',
            'payload' => ['tenant_id' => $this->tenantId, 'review_id' => 1, 'response_text' => 'Thanks for the review!', 'metadata' => []],
            'metadata' => ['sync_queue_id_from_tenant' => 801],
            'priority' => 2,
            'received_at' => now(),
            'scheduled_at' => now(),
            'expires_at' => now()->addHours(24),
            'status' => 'pending',
            'retry_count' => 0,
            'max_retries' => 3,
            'backoff_strategy' => 'exponential',
            'idempotency_key' => 'idem-'.uniqid(),
            'payload_hash' => 'hash-'.uniqid(),
        ], $overrides));
    }

    private function makeReviewService(): ProductReviewService
    {
        return new ProductReviewService(new ProductReviewRepository, new ReviewContentChecker);
    }

    public function test_inbound_job_adds_response_and_acks_completed(): void
    {
        Http::fake(['*/review-response-ack' => Http::response(['success' => true], 200)]);
        $review = $this->createReview();
        $sync = $this->createInboundSync(['tenant_syncable_id' => $review->id, 'payload' => [
            'tenant_id' => $this->tenantId, 'review_id' => $review->id, 'response_text' => 'Thanks for the review!', 'metadata' => [],
        ]]);

        (new ProcessInboundReviewResponseSync($sync->id))->handle($this->makeReviewService());

        $fresh = $sync->fresh();
        $this->assertSame('completed', $fresh->status);
        $this->assertSame('Thanks for the review!', $review->fresh()->merchant_response);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'review-response-ack')
                && $request['outbound_sync_queue_id'] === 801
                && $request['status'] === 'completed';
        });
    }

    public function test_inbound_job_updates_existing_response(): void
    {
        Http::fake(['*/review-response-ack' => Http::response(['success' => true], 200)]);
        $review = $this->createReview(['merchant_response' => 'Old response', 'merchant_responded_at' => now()]);
        $sync = $this->createInboundSync(['tenant_syncable_id' => $review->id, 'action' => 'update', 'payload' => [
            'tenant_id' => $this->tenantId, 'review_id' => $review->id, 'response_text' => 'Updated response text!', 'metadata' => [],
        ]]);

        (new ProcessInboundReviewResponseSync($sync->id))->handle($this->makeReviewService());

        $this->assertSame('Updated response text!', $review->fresh()->merchant_response);
    }

    public function test_inbound_job_marks_failed_and_does_not_ack_on_ownership_mismatch(): void
    {
        Http::fake();
        $review = $this->createReview();
        $sync = $this->createInboundSync(['tenant_syncable_id' => $review->id, 'payload' => [
            'tenant_id' => 'some-other-tenant', 'review_id' => $review->id, 'response_text' => 'Thanks!', 'metadata' => [],
        ]]);

        try {
            (new ProcessInboundReviewResponseSync($sync->id))->handle($this->makeReviewService());
            $this->fail('Expected exception was not thrown');
        } catch (\InvalidArgumentException $e) {
            // expected — tenant does not own this review's product
        }

        $fresh = $sync->fresh();
        $this->assertSame('pending', $fresh->status);
        $this->assertSame(1, $fresh->retry_count);
        Http::assertNothingSent();
    }

    public function test_inbound_job_acks_failed_after_max_retries(): void
    {
        Http::fake(['*/review-response-ack' => Http::response(['success' => true], 200)]);
        $review = $this->createReview();
        $sync = $this->createInboundSync([
            'tenant_syncable_id' => $review->id,
            'payload' => ['tenant_id' => 'some-other-tenant', 'review_id' => $review->id, 'response_text' => 'Thanks!', 'metadata' => []],
            'retry_count' => 3,
            'max_retries' => 3,
        ]);

        try {
            (new ProcessInboundReviewResponseSync($sync->id))->handle($this->makeReviewService());
            $this->fail('Expected exception was not thrown');
        } catch (\InvalidArgumentException $e) {
            // expected
        }

        $this->assertSame('failed', $sync->fresh()->status);
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'review-response-ack') && $request['status'] === 'failed';
        });
    }

    public function test_inbound_job_skips_when_already_completed(): void
    {
        Http::fake();
        $sync = $this->createInboundSync(['status' => 'completed']);

        (new ProcessInboundReviewResponseSync($sync->id))->handle($this->makeReviewService());

        Http::assertNothingSent();
    }

    public function test_failed_marks_sync_queue_failed_with_job_failed_message(): void
    {
        $sync = $this->createInboundSync();

        (new ProcessInboundReviewResponseSync($sync->id))->failed(new \RuntimeException('queue worker gave up'));

        $fresh = $sync->fresh();
        $this->assertSame('failed', $fresh->status);
        $this->assertStringContainsString('queue worker gave up', $fresh->error_message);
    }
}
