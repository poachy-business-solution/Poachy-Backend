<?php

namespace Tests\Feature\Tenant\Sync;

use App\DataTransferObjects\Sync\ReviewResponseSyncDTO;
use App\Events\Tenant\MerchantReviewResponseCreated;
use App\Jobs\Tenant\ProcessOutboundReviewResponseSync;
use App\Listeners\Tenant\EnqueueMerchantReviewResponseSync;
use App\Models\Tenant\ProductReview;
use App\Models\Tenant\SyncQueueOutbound;
use App\Services\Tenant\ReviewResponseService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Stancl\Tenancy\Contracts\Tenant as TenantContract;
use Tests\TestCase;

class ReviewResponseSyncTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'tenant');
        Config::set('database.connections.tenant.database', 'poachy_test');
        Config::set('services.central_api.url', 'https://central.test');
        Config::set('services.central_api.token', 'central-test-token');
        DB::purge('tenant');
        DB::connection('tenant')->statement('SET foreign_key_checks = 0');

        $this->dropTestTables();
        $this->createMinimalSchema();

        $fakeTenant = new \stdClass;
        $fakeTenant->id = 'test-tenant';
        app()->instance(TenantContract::class, $fakeTenant);
    }

    protected function tearDown(): void
    {
        $this->dropTestTables();
        DB::connection('tenant')->statement('SET foreign_key_checks = 1');
        parent::tearDown();
    }

    private function makeService(): ReviewResponseService
    {
        return new ReviewResponseService;
    }

    private function makeListener(): EnqueueMerchantReviewResponseSync
    {
        return new EnqueueMerchantReviewResponseSync;
    }

    private function createReview(array $overrides = []): ProductReview
    {
        return ProductReview::create(array_merge([
            'central_review_id' => 1,
            'product_id' => 1,
            'product_name' => 'Test Product',
            'customer_name' => 'Jane Doe',
            'rating' => 4.5,
            'status' => 'approved',
            'reviewed_at' => now(),
        ], $overrides));
    }

    // =========================================================================
    // ReviewResponseService::createResponse()
    // =========================================================================

    public function test_create_response_sets_text_status_and_timestamp(): void
    {
        Event::fake([MerchantReviewResponseCreated::class]);
        $review = $this->createReview();

        $updated = $this->makeService()->createResponse($review, 'Thank you so much for your feedback!');

        $this->assertSame('Thank you so much for your feedback!', $updated->merchant_response);
        $this->assertSame('pending', $updated->response_sync_status);
        $this->assertNotNull($updated->merchant_responded_at);
    }

    public function test_create_response_dispatches_sync_event_with_create_action(): void
    {
        Event::fake([MerchantReviewResponseCreated::class]);
        $review = $this->createReview();

        $this->makeService()->createResponse($review, 'Thank you so much for your feedback!');

        Event::assertDispatched(MerchantReviewResponseCreated::class, fn ($e) => $e->action === 'create');
    }

    public function test_create_response_throws_when_already_responded(): void
    {
        $review = $this->createReview(['merchant_response' => 'Already responded']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('already has a merchant response');

        $this->makeService()->createResponse($review, 'A new response attempt here.');
    }

    public function test_create_response_throws_when_too_short(): void
    {
        $review = $this->createReview();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('at least 10 characters');

        $this->makeService()->createResponse($review, 'Too short');
    }

    public function test_create_response_throws_when_too_long(): void
    {
        $review = $this->createReview();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('may not exceed 1000');

        $this->makeService()->createResponse($review, str_repeat('a', 1001));
    }

    public function test_create_response_throws_when_contains_url(): void
    {
        $review = $this->createReview();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('may not contain URLs');

        $this->makeService()->createResponse($review, 'Visit https://example.com for more info please.');
    }

    public function test_create_response_throws_when_excessive_caps(): void
    {
        $review = $this->createReview();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('excessive capital letters');

        $this->makeService()->createResponse($review, 'THANK YOU FOR THE REVIEW WE APPRECIATE IT');
    }

    // =========================================================================
    // ReviewResponseService::updateResponse()
    // =========================================================================

    public function test_update_response_within_edit_window(): void
    {
        Event::fake([MerchantReviewResponseCreated::class]);
        $review = $this->createReview([
            'merchant_response' => 'Original response text here.',
            'merchant_responded_at' => now()->subHours(2),
        ]);

        $updated = $this->makeService()->updateResponse($review, 'Updated response text here now.');

        $this->assertSame('Updated response text here now.', $updated->merchant_response);
        $this->assertSame('pending', $updated->response_sync_status);
    }

    public function test_update_response_dispatches_sync_event_with_update_action(): void
    {
        Event::fake([MerchantReviewResponseCreated::class]);
        $review = $this->createReview([
            'merchant_response' => 'Original response text here.',
            'merchant_responded_at' => now()->subHours(2),
        ]);

        $this->makeService()->updateResponse($review, 'Updated response text here now.');

        Event::assertDispatched(MerchantReviewResponseCreated::class, fn ($e) => $e->action === 'update');
    }

    public function test_update_response_throws_after_edit_window(): void
    {
        $review = $this->createReview([
            'merchant_response' => 'Original response text here.',
            'merchant_responded_at' => now()->subHours(25),
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('24 hours');

        $this->makeService()->updateResponse($review, 'Too late to edit this response.');
    }

    public function test_update_response_validates_text(): void
    {
        $review = $this->createReview([
            'merchant_response' => 'Original response text here.',
            'merchant_responded_at' => now(),
        ]);

        $this->expectException(\InvalidArgumentException::class);

        $this->makeService()->updateResponse($review, 'short');
    }

    // =========================================================================
    // ReviewResponseSyncDTO
    // =========================================================================

    public function test_dto_builds_from_review(): void
    {
        $review = $this->createReview(['central_review_id' => 42, 'merchant_response' => 'Thanks for the review!']);

        $dto = ReviewResponseSyncDTO::fromReview($review);

        $this->assertSame('test-tenant', $dto->tenantId);
        $this->assertSame(42, $dto->centralReviewId);
        $this->assertSame('Thanks for the review!', $dto->responseText);
        $this->assertSame($review->id, $dto->metadata['local_review_id']);
    }

    public function test_dto_idempotency_key_stable_for_same_payload(): void
    {
        $review = $this->createReview(['merchant_response' => 'Thanks for the review!']);
        $dto = ReviewResponseSyncDTO::fromReview($review);

        $this->assertSame($dto->generateIdempotencyKey('create'), $dto->generateIdempotencyKey('create'));
    }

    public function test_dto_idempotency_key_changes_with_response_text(): void
    {
        $reviewA = $this->createReview(['merchant_response' => 'Thanks for the review!']);
        $reviewB = $this->createReview(['central_review_id' => 2, 'merchant_response' => 'A totally different response.']);

        $keyA = ReviewResponseSyncDTO::fromReview($reviewA)->generateIdempotencyKey('create');
        $keyB = ReviewResponseSyncDTO::fromReview($reviewB)->generateIdempotencyKey('create');

        $this->assertNotSame($keyA, $keyB);
    }

    // =========================================================================
    // EnqueueMerchantReviewResponseSync listener
    // =========================================================================

    public function test_listener_creates_sync_queue_row_and_dispatches_job(): void
    {
        Queue::fake();
        $review = $this->createReview(['merchant_response' => 'Thanks for the review!']);
        $event = new MerchantReviewResponseCreated($review, 'create');

        $this->makeListener()->handle($event);

        $this->assertDatabaseHas('sync_queue_outbound', [
            'tenant_id' => 'test-tenant',
            'syncable_type' => 'ReviewResponse',
            'syncable_id' => $review->central_review_id,
            'status' => 'pending',
        ], 'tenant');
        Queue::assertPushed(ProcessOutboundReviewResponseSync::class);
    }

    public function test_listener_skips_when_identical_sync_already_pending(): void
    {
        Queue::fake();
        $review = $this->createReview(['merchant_response' => 'Thanks for the review!']);
        $event = new MerchantReviewResponseCreated($review, 'create');

        $this->makeListener()->handle($event);
        $this->makeListener()->handle($event);

        $this->assertSame(1, SyncQueueOutbound::where('syncable_id', $review->central_review_id)->count());
    }

    // =========================================================================
    // ProcessOutboundReviewResponseSync
    // =========================================================================

    private function createOutboundSync(array $overrides = []): SyncQueueOutbound
    {
        return SyncQueueOutbound::create(array_merge([
            'tenant_id' => 'test-tenant',
            'syncable_type' => 'ReviewResponse',
            'syncable_id' => 1,
            'action' => 'create',
            'payload' => ['tenant_id' => 'test-tenant', 'review_id' => 1, 'response_text' => 'Thanks!', 'metadata' => []],
            'priority' => 2,
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

    public function test_outbound_job_marks_completed_on_success(): void
    {
        Http::fake(['*/api/v1/central/sync/inbound/product-review-response' => Http::response(['success' => true, 'data' => ['sync_id' => 5]], 200)]);
        $sync = $this->createOutboundSync();

        (new ProcessOutboundReviewResponseSync($sync->id))->handle();

        $this->assertSame('completed', $sync->fresh()->status);
    }

    public function test_outbound_job_reschedules_retry_on_http_error(): void
    {
        Queue::fake();
        Http::fake(['*/api/v1/central/sync/inbound/product-review-response' => Http::response(['success' => false], 500)]);
        $sync = $this->createOutboundSync();

        try {
            (new ProcessOutboundReviewResponseSync($sync->id))->handle();
            $this->fail('Expected exception was not thrown');
        } catch (\Illuminate\Http\Client\RequestException $e) {
            // Http::retry(2, 100) defaults to $throw=true — same as the other
            // two outbound sync jobs, so the manual successful() check below it
            // never actually runs once retries are exhausted.
        }

        $fresh = $sync->fresh();
        $this->assertSame('pending', $fresh->status);
        $this->assertSame(1, $fresh->retry_count);
        Queue::assertPushed(ProcessOutboundReviewResponseSync::class);
    }

    public function test_outbound_job_skips_when_already_completed(): void
    {
        Http::fake();
        $sync = $this->createOutboundSync(['status' => 'completed']);

        (new ProcessOutboundReviewResponseSync($sync->id))->handle();

        Http::assertNothingSent();
    }

    // =========================================================================
    // Schema helpers
    // =========================================================================

    private function dropTestTables(): void
    {
        foreach (['sync_queue_outbound', 'product_reviews'] as $table) {
            Schema::connection('tenant')->dropIfExists($table);
        }
    }

    private function createMinimalSchema(): void
    {
        $conn = 'tenant';

        Schema::connection($conn)->create('product_reviews', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('central_review_id')->unique();
            $table->unsignedBigInteger('product_id');
            $table->string('product_name');
            $table->string('product_sku')->nullable();
            $table->string('customer_name');
            $table->decimal('rating', 2, 1);
            $table->string('title')->nullable();
            $table->text('review_text')->nullable();
            $table->json('review_images')->nullable();
            $table->boolean('is_verified_purchase')->default(false);
            $table->text('merchant_response')->nullable();
            $table->timestamp('merchant_responded_at')->nullable();
            $table->enum('response_sync_status', ['pending', 'synced', 'failed'])->nullable();
            $table->string('status')->default('approved');
            $table->timestamp('reviewed_at');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection($conn)->create('sync_queue_outbound', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id', 100)->index();
            $table->string('syncable_type', 100);
            $table->unsignedBigInteger('syncable_id');
            $table->string('action', 30)->default('create');
            $table->json('payload')->nullable();
            $table->json('changes')->nullable();
            $table->json('metadata')->nullable();
            $table->unsignedTinyInteger('priority')->default(5);
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('status', 30)->default('pending');
            $table->string('lock_token', 100)->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->unsignedInteger('locked_by_worker_id')->nullable();
            $table->unsignedTinyInteger('retry_count')->default(0);
            $table->unsignedTinyInteger('max_retries')->default(3);
            $table->timestamp('next_retry_at')->nullable();
            $table->string('backoff_strategy', 30)->default('exponential');
            $table->text('error_message')->nullable();
            $table->string('error_code', 50)->nullable();
            $table->json('error_details')->nullable();
            $table->json('sync_response')->nullable();
            $table->string('central_record_id')->nullable();
            $table->string('central_table', 100)->nullable();
            $table->timestamp('processing_started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('idempotency_key', 100)->unique()->nullable();
            $table->string('payload_hash', 64)->nullable();
            $table->timestamps();
        });
    }
}
