<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Receives sync requests FROM tenant databases (products, inventory, prices).
        // Processes them and updates marketplace_products table.
        Schema::connection('central')->create('sync_queue_inbound', function (Blueprint $table) {
            $table->id();

            // ============================================
            // SOURCE TENANT
            // ============================================
            $table->string('tenant_id', 100);

            // ============================================
            // SYNC ENTITY (What's being synced)
            // ============================================
            $table->string('syncable_type', 100);
            // 'Product', 'ProductVariant', 'ProductBundle', 'Inventory', 'Store'

            $table->unsignedBigInteger('tenant_syncable_id'); // ID in tenant DB
            $table->uuid('tenant_record_uuid')->nullable(); // UUID from tenant

            // ============================================
            // SYNC ACTION
            // ============================================
            $table->enum('action', [
                'create',
                'update',
                'delete',
                'activate',
                'deactivate',
                'bulk_update'
            ])->default('create');

            // ============================================
            // PAYLOAD DATA
            // ============================================
            $table->json('payload'); // Complete data from tenant
            $table->json('changes')->nullable(); // Only changed fields for updates
            $table->json('metadata')->nullable(); // Context: user_id, ip, timestamp

            // ============================================
            // PRIORITY & SCHEDULING
            // ============================================
            $table->unsignedTinyInteger('priority')->default(5);
            // 1 = Critical (inventory), 3 = High (price), 5 = Normal, 8 = Low, 10 = Bulk

            $table->timestamp('received_at')->useCurrent(); // When central received it
            $table->timestamp('scheduled_at')->nullable(); // When to process
            $table->timestamp('expires_at')->nullable(); // Don't process if stale

            // ============================================
            // PROCESSING STATUS
            // ============================================
            $table->enum('status', [
                'pending',
                'queued',
                'processing',
                'validating',
                'mapping',      // Mapping tenant categories/brands to marketplace
                'syncing',
                'completed',
                'failed',
                'cancelled',
                'stale',
                'duplicate'
            ])->default('pending');

            // ============================================
            // LOCKING (Prevent concurrent processing)
            // ============================================
            $table->string('lock_token', 100)->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->unsignedInteger('locked_by_worker_id')->nullable();

            // ============================================
            // RETRY MECHANISM
            // ============================================
            $table->unsignedTinyInteger('retry_count')->default(0);
            $table->unsignedTinyInteger('max_retries')->default(3);
            $table->timestamp('next_retry_at')->nullable();
            $table->string('backoff_strategy', 30)->default('exponential');

            // ============================================
            // RESULTS & ERRORS
            // ============================================
            $table->text('error_message')->nullable();
            $table->string('error_code', 50)->nullable();
            $table->json('error_details')->nullable();
            $table->json('sync_response')->nullable();

            // ============================================
            // CENTRAL DATABASE MAPPING RESULT
            // ============================================
            $table->unsignedBigInteger('central_record_id')->nullable(); // Created marketplace_products.id
            $table->uuid('central_record_uuid')->nullable();
            $table->string('central_table', 100)->nullable(); // 'marketplace_products'

            // ============================================
            // TIMESTAMPS
            // ============================================
            $table->timestamp('processing_started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            // ============================================
            // BATCH PROCESSING
            // ============================================
            $table->uuid('batch_id')->nullable();
            $table->unsignedInteger('batch_sequence')->nullable();
            $table->unsignedInteger('batch_total')->nullable();

            // ============================================
            // DEDUPLICATION
            // ============================================
            $table->string('idempotency_key', 100)->unique()->nullable();
            $table->string('payload_hash', 64)->nullable();

            // ============================================
            // FOREIGN KEYS
            // ============================================
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

            // ============================================
            // INDEXES
            // ============================================
            $table->index(['tenant_id', 'status', 'priority', 'scheduled_at'], 'idx_queue_processing');
            $table->index(['status', 'retry_count', 'next_retry_at'], 'idx_retry_queue');
            $table->index(['tenant_id', 'syncable_type', 'tenant_syncable_id'], 'idx_tenant_entity');
            $table->index(['status', 'failed_at'], 'idx_failed_syncs');
            $table->index(['expires_at', 'status'], 'idx_stale_cleanup');
            $table->index(['batch_id', 'batch_sequence'], 'idx_batch');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('central')->dropIfExists('sync_queue_inbound');
    }
};
