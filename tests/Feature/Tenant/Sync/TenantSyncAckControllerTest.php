<?php

namespace Tests\Feature\Tenant\Sync;

use App\Http\Controllers\Api\Tenant\Sync\TenantSyncAckController;
use App\Http\Requests\Tenant\Sync\BundleSyncAckRequest;
use App\Http\Requests\Tenant\Sync\DeliveryZoneSyncAckRequest;
use App\Http\Requests\Tenant\Sync\InventoryCountSyncAckRequest;
use App\Http\Requests\Tenant\Sync\MarketplaceFulfillmentSyncAckRequest;
use App\Http\Requests\Tenant\Sync\ProductSyncAckRequest;
use App\Http\Requests\Tenant\Sync\ReviewResponseSyncAckRequest;
use App\Http\Requests\Tenant\Sync\VariantSyncAckRequest;
use App\Models\Tenant\ProductReview;
use App\Models\Tenant\SyncQueueOutbound;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Stancl\Tenancy\Contracts\Tenant as TenantContract;
use Tests\TestCase;

class TenantSyncAckControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'tenant');
        Config::set('database.connections.tenant.database', 'poachy_test');
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

    private function makeController(): TenantSyncAckController
    {
        return new TenantSyncAckController;
    }

    private function makeRequest(string $class, array $data)
    {
        $request = new $class;
        $request->merge($data);
        $request->setValidator(validator($data, $request->rules()));

        return $request;
    }

    private function createOutboundSync(array $overrides = []): SyncQueueOutbound
    {
        return SyncQueueOutbound::create(array_merge([
            'tenant_id' => 'test-tenant',
            'syncable_type' => 'DeliveryZone',
            'syncable_id' => 1,
            'action' => 'create',
            'payload' => [],
            'status' => 'processing',
            'retry_count' => 0,
            'max_retries' => 3,
            'idempotency_key' => 'idem-'.uniqid(),
        ], $overrides));
    }

    // =========================================================================
    // receiveDeliveryZoneAck()
    // =========================================================================

    public function test_delivery_zone_ack_completed_updates_central_record_id(): void
    {
        $sync = $this->createOutboundSync();

        $response = $this->makeController()->receiveDeliveryZoneAck($this->makeRequest(DeliveryZoneSyncAckRequest::class, [
            'outbound_sync_queue_id' => $sync->id, 'status' => 'completed', 'central_zone_id' => 77,
        ]));

        $this->assertSame(200, $response->getStatusCode());
        $fresh = $sync->fresh();
        $this->assertSame('77', $fresh->central_record_id);
        $this->assertSame('tenant_delivery_zones', $fresh->central_table);
        $this->assertSame('completed', $fresh->sync_response['ack_status']);
    }

    public function test_delivery_zone_ack_failed_marks_sync_failed(): void
    {
        $sync = $this->createOutboundSync();

        $this->makeController()->receiveDeliveryZoneAck($this->makeRequest(DeliveryZoneSyncAckRequest::class, [
            'outbound_sync_queue_id' => $sync->id, 'status' => 'failed', 'reason' => 'Zone name already exists',
        ]));

        $fresh = $sync->fresh();
        $this->assertSame('failed', $fresh->status);
        $this->assertSame('Zone name already exists', $fresh->error_message);
    }

    public function test_delivery_zone_ack_returns_404_for_unknown_sync(): void
    {
        $response = $this->makeController()->receiveDeliveryZoneAck($this->makeRequest(DeliveryZoneSyncAckRequest::class, [
            'outbound_sync_queue_id' => 999999, 'status' => 'completed', 'central_zone_id' => 1,
        ]));

        $this->assertSame(404, $response->getStatusCode());
    }

    // =========================================================================
    // receiveInventoryCountAck()
    // =========================================================================

    public function test_inventory_count_ack_completed_updates_central_record_id(): void
    {
        $sync = $this->createOutboundSync(['syncable_type' => 'InventoryCount']);

        $this->makeController()->receiveInventoryCountAck($this->makeRequest(InventoryCountSyncAckRequest::class, [
            'outbound_sync_queue_id' => $sync->id, 'status' => 'completed', 'central_product_id' => 55,
        ]));

        $fresh = $sync->fresh();
        $this->assertSame('55', $fresh->central_record_id);
        $this->assertSame('marketplace_products', $fresh->central_table);
    }

    public function test_inventory_count_ack_completed_allows_null_central_product_id(): void
    {
        // The product-not-yet-synced-to-marketplace case is a soft success, not a
        // failure — the ACK still arrives with status=completed but no product id.
        $sync = $this->createOutboundSync(['syncable_type' => 'InventoryCount']);

        $response = $this->makeController()->receiveInventoryCountAck($this->makeRequest(InventoryCountSyncAckRequest::class, [
            'outbound_sync_queue_id' => $sync->id, 'status' => 'completed', 'central_product_id' => null,
        ]));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNull($sync->fresh()->central_record_id);
    }

    public function test_inventory_count_ack_failed_marks_sync_failed(): void
    {
        $sync = $this->createOutboundSync(['syncable_type' => 'InventoryCount']);

        $this->makeController()->receiveInventoryCountAck($this->makeRequest(InventoryCountSyncAckRequest::class, [
            'outbound_sync_queue_id' => $sync->id, 'status' => 'failed', 'reason' => 'Central error',
        ]));

        $this->assertSame('failed', $sync->fresh()->status);
    }

    // =========================================================================
    // receiveProductAck() / receiveVariantAck() / receiveBundleAck()
    // =========================================================================

    public function test_product_ack_completed_updates_central_record_id(): void
    {
        $sync = $this->createOutboundSync(['syncable_type' => 'Product']);

        $this->makeController()->receiveProductAck($this->makeRequest(ProductSyncAckRequest::class, [
            'outbound_sync_queue_id' => $sync->id, 'status' => 'completed', 'central_product_id' => 55,
        ]));

        $fresh = $sync->fresh();
        $this->assertSame('55', $fresh->central_record_id);
        $this->assertSame('marketplace_products', $fresh->central_table);
    }

    public function test_product_ack_failed_marks_sync_failed(): void
    {
        $sync = $this->createOutboundSync(['syncable_type' => 'Product']);

        $this->makeController()->receiveProductAck($this->makeRequest(ProductSyncAckRequest::class, [
            'outbound_sync_queue_id' => $sync->id, 'status' => 'failed', 'reason' => 'Central error',
        ]));

        $this->assertSame('failed', $sync->fresh()->status);
    }

    public function test_product_ack_returns_404_for_unknown_sync(): void
    {
        $response = $this->makeController()->receiveProductAck($this->makeRequest(ProductSyncAckRequest::class, [
            'outbound_sync_queue_id' => 999999, 'status' => 'completed', 'central_product_id' => 1,
        ]));

        $this->assertSame(404, $response->getStatusCode());
    }

    public function test_variant_ack_completed_updates_central_record_id(): void
    {
        $sync = $this->createOutboundSync(['syncable_type' => 'Variant']);

        $this->makeController()->receiveVariantAck($this->makeRequest(VariantSyncAckRequest::class, [
            'outbound_sync_queue_id' => $sync->id, 'status' => 'completed', 'central_product_id' => 56,
        ]));

        $fresh = $sync->fresh();
        $this->assertSame('56', $fresh->central_record_id);
        $this->assertSame('marketplace_products', $fresh->central_table);
    }

    public function test_variant_ack_failed_marks_sync_failed(): void
    {
        $sync = $this->createOutboundSync(['syncable_type' => 'Variant']);

        $this->makeController()->receiveVariantAck($this->makeRequest(VariantSyncAckRequest::class, [
            'outbound_sync_queue_id' => $sync->id, 'status' => 'failed', 'reason' => 'Central error',
        ]));

        $this->assertSame('failed', $sync->fresh()->status);
    }

    public function test_bundle_ack_completed_updates_central_record_id(): void
    {
        $sync = $this->createOutboundSync(['syncable_type' => 'Bundle']);

        $this->makeController()->receiveBundleAck($this->makeRequest(BundleSyncAckRequest::class, [
            'outbound_sync_queue_id' => $sync->id, 'status' => 'completed', 'central_product_id' => 57,
        ]));

        $fresh = $sync->fresh();
        $this->assertSame('57', $fresh->central_record_id);
        $this->assertSame('marketplace_products', $fresh->central_table);
    }

    public function test_bundle_ack_failed_marks_sync_failed(): void
    {
        $sync = $this->createOutboundSync(['syncable_type' => 'Bundle']);

        $this->makeController()->receiveBundleAck($this->makeRequest(BundleSyncAckRequest::class, [
            'outbound_sync_queue_id' => $sync->id, 'status' => 'failed', 'reason' => 'Central error',
        ]));

        $this->assertSame('failed', $sync->fresh()->status);
    }

    // =========================================================================
    // receiveReviewResponseAck()
    // =========================================================================

    private function createReview(array $overrides = []): ProductReview
    {
        return ProductReview::create(array_merge([
            'central_review_id' => 1,
            'product_id' => 1,
            'product_name' => 'Test Product',
            'customer_name' => 'Jane Doe',
            'rating' => 4.5,
            'merchant_response' => 'Thanks for the feedback!',
            'merchant_responded_at' => now(),
            'response_sync_status' => 'pending',
            'status' => 'approved',
            'reviewed_at' => now(),
        ], $overrides));
    }

    public function test_review_response_ack_completed_updates_sync_and_review_status(): void
    {
        $review = $this->createReview();
        $sync = $this->createOutboundSync([
            'syncable_type' => 'ReviewResponse',
            'syncable_id' => 1,
            'payload' => ['metadata' => ['local_review_id' => $review->id]],
        ]);

        $this->makeController()->receiveReviewResponseAck($this->makeRequest(ReviewResponseSyncAckRequest::class, [
            'outbound_sync_queue_id' => $sync->id, 'status' => 'completed',
        ]));

        $this->assertSame('completed', $sync->fresh()->sync_response['ack_status']);
        $this->assertSame('synced', $review->fresh()->response_sync_status);
    }

    public function test_review_response_ack_failed_updates_sync_and_review_status(): void
    {
        $review = $this->createReview();
        $sync = $this->createOutboundSync([
            'syncable_type' => 'ReviewResponse',
            'syncable_id' => 1,
            'payload' => ['metadata' => ['local_review_id' => $review->id]],
        ]);

        $this->makeController()->receiveReviewResponseAck($this->makeRequest(ReviewResponseSyncAckRequest::class, [
            'outbound_sync_queue_id' => $sync->id, 'status' => 'failed', 'reason' => 'Review not found centrally',
        ]));

        $this->assertSame('failed', $sync->fresh()->status);
        $this->assertSame('failed', $review->fresh()->response_sync_status);
    }

    public function test_review_response_ack_tolerates_missing_local_review(): void
    {
        // metadata.local_review_id missing entirely — should update the sync
        // record without crashing on a null-safe review lookup.
        $sync = $this->createOutboundSync(['syncable_type' => 'ReviewResponse', 'payload' => []]);

        $response = $this->makeController()->receiveReviewResponseAck($this->makeRequest(ReviewResponseSyncAckRequest::class, [
            'outbound_sync_queue_id' => $sync->id, 'status' => 'completed',
        ]));

        $this->assertSame(200, $response->getStatusCode());
    }

    // =========================================================================
    // receiveMarketplaceFulfillmentAck()
    // =========================================================================

    public function test_marketplace_fulfillment_ack_completed_updates_central_record_id(): void
    {
        $sync = $this->createOutboundSync(['syncable_type' => 'MarketplaceFulfillment']);

        $response = $this->makeController()->receiveMarketplaceFulfillmentAck($this->makeRequest(MarketplaceFulfillmentSyncAckRequest::class, [
            'outbound_sync_queue_id' => $sync->id, 'status' => 'completed', 'central_order_id' => 555,
        ]));

        $this->assertSame(200, $response->getStatusCode());
        $fresh = $sync->fresh();
        $this->assertSame('555', $fresh->central_record_id);
        $this->assertSame('marketplace_orders', $fresh->central_table);
        $this->assertSame('completed', $fresh->sync_response['ack_status']);
    }

    public function test_marketplace_fulfillment_ack_failed_marks_sync_failed(): void
    {
        $sync = $this->createOutboundSync(['syncable_type' => 'MarketplaceFulfillment']);

        $this->makeController()->receiveMarketplaceFulfillmentAck($this->makeRequest(MarketplaceFulfillmentSyncAckRequest::class, [
            'outbound_sync_queue_id' => $sync->id, 'status' => 'failed', 'reason' => 'Order not found centrally',
        ]));

        $fresh = $sync->fresh();
        $this->assertSame('failed', $fresh->status);
        $this->assertSame('Order not found centrally', $fresh->error_message);
    }

    public function test_marketplace_fulfillment_ack_returns_404_for_unknown_sync(): void
    {
        $response = $this->makeController()->receiveMarketplaceFulfillmentAck($this->makeRequest(MarketplaceFulfillmentSyncAckRequest::class, [
            'outbound_sync_queue_id' => 999999, 'status' => 'completed', 'central_order_id' => 1,
        ]));

        $this->assertSame(404, $response->getStatusCode());
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
    }
}
