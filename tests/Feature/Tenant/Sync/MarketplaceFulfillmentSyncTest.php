<?php

namespace Tests\Feature\Tenant\Sync;

use App\DataTransferObjects\Sync\MarketplaceFulfillmentSyncDTO;
use App\Events\Tenant\MarketplaceSaleFulfillmentSyncRequested;
use App\Jobs\Tenant\ProcessOutboundMarketplaceFulfillmentSync;
use App\Listeners\Tenant\EnqueueMarketplaceFulfillmentSync;
use App\Models\Tenant\MarketplaceSale;
use App\Models\Tenant\SyncQueueOutbound;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Stancl\Tenancy\Contracts\Tenant as TenantContract;
use Tests\TestCase;

class MarketplaceFulfillmentSyncTest extends TestCase
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

    private function makeListener(): EnqueueMarketplaceFulfillmentSync
    {
        return new EnqueueMarketplaceFulfillmentSync;
    }

    private function createSale(array $overrides = []): MarketplaceSale
    {
        return Model::withoutEvents(fn () => MarketplaceSale::create(array_merge([
            'central_order_id' => 555,
            'sale_number' => 'MKT-ORD-'.uniqid(),
            'store_id' => 1,
            'sale_date' => now(),
            'subtotal' => 1000,
            'tax_amount' => 160,
            'total_amount' => 1160,
            'payment_status' => 'paid',
            'amount_paid' => 1160,
            'amount_due' => 0,
            'fulfillment_type' => 'delivery',
            'fulfillment_status' => 'pending',
        ], $overrides)));
    }

    // =========================================================================
    // MarketplaceFulfillmentSyncDTO::fromModel()
    // =========================================================================

    public function test_dto_throws_when_sale_not_persisted(): void
    {
        $sale = new MarketplaceSale(['sale_number' => 'X', 'central_order_id' => 1]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must be persisted');

        MarketplaceFulfillmentSyncDTO::fromModel($sale);
    }

    public function test_dto_throws_when_no_central_order_id(): void
    {
        $sale = $this->createSale(['central_order_id' => null]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('central_order_id');

        MarketplaceFulfillmentSyncDTO::fromModel($sale);
    }

    public function test_dto_builds_from_sale_correctly(): void
    {
        $sale = $this->createSale(['fulfillment_status' => 'preparing']);

        $dto = MarketplaceFulfillmentSyncDTO::fromModel($sale, ['courier_name' => 'John'], 'packed it up');

        $this->assertSame('test-tenant', $dto->tenantId);
        $this->assertSame($sale->id, $dto->saleId);
        $this->assertSame(555, $dto->centralOrderId);
        $this->assertSame('preparing', $dto->fulfillmentStatus);
        $this->assertSame('John', $dto->courierName);
        $this->assertSame('packed it up', $dto->notes);
        $this->assertTrue($dto->hasDeliveryData());
    }

    public function test_dto_has_delivery_data_is_false_when_nothing_submitted(): void
    {
        $sale = $this->createSale();

        $dto = MarketplaceFulfillmentSyncDTO::fromModel($sale);

        $this->assertFalse($dto->hasDeliveryData());
    }

    // =========================================================================
    // EnqueueMarketplaceFulfillmentSync listener
    // =========================================================================

    public function test_listener_creates_sync_queue_row_and_dispatches_job(): void
    {
        Queue::fake();
        $sale = $this->createSale();
        $dto = MarketplaceFulfillmentSyncDTO::fromModel($sale);
        $event = new MarketplaceSaleFulfillmentSyncRequested($dto);

        $this->makeListener()->handle($event);

        $this->assertDatabaseHas('sync_queue_outbound', [
            'tenant_id' => 'test-tenant',
            'syncable_type' => 'MarketplaceFulfillment',
            'syncable_id' => $sale->id,
            'status' => 'pending',
        ], 'tenant');
        Queue::assertPushed(ProcessOutboundMarketplaceFulfillmentSync::class);
    }

    public function test_listener_skips_when_identical_sync_already_pending(): void
    {
        Queue::fake();
        $sale = $this->createSale();
        $dto = MarketplaceFulfillmentSyncDTO::fromModel($sale);
        $event = new MarketplaceSaleFulfillmentSyncRequested($dto);

        $this->makeListener()->handle($event);
        $this->makeListener()->handle($event);

        $this->assertSame(1, SyncQueueOutbound::where('syncable_id', $sale->id)->count());
    }

    public function test_listener_creates_new_when_payload_differs(): void
    {
        Queue::fake();
        $sale = $this->createSale(['fulfillment_status' => 'pending']);
        $this->makeListener()->handle(new MarketplaceSaleFulfillmentSyncRequested(
            MarketplaceFulfillmentSyncDTO::fromModel($sale)
        ));

        $sale->update(['fulfillment_status' => 'confirmed']);
        $this->makeListener()->handle(new MarketplaceSaleFulfillmentSyncRequested(
            MarketplaceFulfillmentSyncDTO::fromModel($sale->fresh())
        ));

        $this->assertSame(2, SyncQueueOutbound::where('syncable_id', $sale->id)->count());
    }

    // =========================================================================
    // ProcessOutboundMarketplaceFulfillmentSync
    // =========================================================================

    private function createOutboundSync(array $overrides = []): SyncQueueOutbound
    {
        return SyncQueueOutbound::create(array_merge([
            'tenant_id' => 'test-tenant',
            'syncable_type' => 'MarketplaceFulfillment',
            'syncable_id' => 1,
            'action' => 'update',
            'payload' => [
                'tenant_id' => 'test-tenant',
                'sale_id' => 1,
                'central_order_id' => 555,
                'fulfillment_status' => 'preparing',
            ],
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
        Http::fake(['*/api/v1/central/sync/inbound/marketplace-fulfillment' => Http::response(['success' => true, 'data' => ['sync_id' => 99]], 200)]);
        $sync = $this->createOutboundSync();

        (new ProcessOutboundMarketplaceFulfillmentSync($sync->id))->handle();

        $this->assertSame('completed', $sync->fresh()->status);
    }

    public function test_outbound_job_reschedules_retry_on_http_error(): void
    {
        Queue::fake();
        Http::fake(['*/api/v1/central/sync/inbound/marketplace-fulfillment' => Http::response(['success' => false], 500)]);
        $sync = $this->createOutboundSync();

        try {
            (new ProcessOutboundMarketplaceFulfillmentSync($sync->id))->handle();
            $this->fail('Expected exception was not thrown');
        } catch (RequestException $e) {
            // Http::retry() defaults to $throw=true — same as delivery zone's job.
        }

        $fresh = $sync->fresh();
        $this->assertSame('pending', $fresh->status);
        $this->assertSame(1, $fresh->retry_count);
        Queue::assertPushed(ProcessOutboundMarketplaceFulfillmentSync::class);
    }

    public function test_outbound_job_marks_stale_without_http_call_when_expired(): void
    {
        Http::fake();
        $sync = $this->createOutboundSync(['expires_at' => now()->subHour()]);

        (new ProcessOutboundMarketplaceFulfillmentSync($sync->id))->handle();

        $this->assertSame('stale', $sync->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_outbound_job_skips_when_already_completed(): void
    {
        Http::fake();
        $sync = $this->createOutboundSync(['status' => 'completed']);

        (new ProcessOutboundMarketplaceFulfillmentSync($sync->id))->handle();

        Http::assertNothingSent();
    }

    // =========================================================================
    // Schema helpers
    // =========================================================================

    private function dropTestTables(): void
    {
        foreach (['sync_queue_outbound', 'marketplace_sales'] as $table) {
            Schema::connection('tenant')->dropIfExists($table);
        }
    }

    private function createMinimalSchema(): void
    {
        $conn = 'tenant';

        Schema::connection($conn)->create('marketplace_sales', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('central_order_id')->nullable();
            $table->string('sale_number')->unique();
            $table->unsignedBigInteger('store_id');
            $table->dateTime('sale_date');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->string('payment_status')->default('paid');
            $table->decimal('amount_paid', 12, 2)->default(0);
            $table->decimal('amount_due', 12, 2)->default(0);
            $table->string('payment_method')->nullable();
            $table->string('payment_reference')->nullable();
            $table->string('fulfillment_type')->default('delivery');
            $table->string('fulfillment_status')->default('pending');
            $table->text('notes')->nullable();
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
